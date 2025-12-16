<?php
require_once '../config/config.php';
require_role(['admin', 'direction']);

$page_title = 'Tableau de bord BI';
$db = Database::getInstance()->getConnection();

// Filtres automatiques
$annee_id = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : null;
$filiere_id = isset($_GET['filiere_id']) ? intval($_GET['filiere_id']) : null;
$niveau_id = isset($_GET['niveau_id']) ? intval($_GET['niveau_id']) : null;
$classe_id = isset($_GET['classe_id']) ? intval($_GET['classe_id']) : null;

// Récupérer toutes les données pour les filtres
$annees = $db->query("SELECT * FROM annees_academiques ORDER BY libelle DESC")->fetchAll();
$filieres = $db->query("SELECT * FROM filieres WHERE statut = 'active' ORDER BY libelle")->fetchAll();
$niveaux = $db->query("SELECT * FROM niveaux WHERE statut = 'actif' ORDER BY ordre")->fetchAll();
$classes = $db->query("SELECT c.*, f.libelle as filiere, n.libelle as niveau FROM classes c JOIN filieres f ON c.filiere_id = f.id JOIN niveaux n ON c.niveau_id = n.id WHERE c.statut = 'active' ORDER BY f.libelle, n.ordre")->fetchAll();

// Année active par défaut
if (!$annee_id) {
    $stmt = $db->query("SELECT * FROM annees_academiques WHERE statut = 'active' LIMIT 1");
    $annee_active = $stmt->fetch();
    if ($annee_active) {
        $annee_id = $annee_active['id'];
    }
} else {
    $stmt = $db->prepare("SELECT * FROM annees_academiques WHERE id = ?");
    $stmt->execute([$annee_id]);
    $annee_active = $stmt->fetch();
}

$stats = [];

if ($annee_active) {
    // Construire les conditions de filtre
    $where_conditions = ["e.statut = 'actif'"];
    $where_params = [];
    
    if ($classe_id) {
        $where_conditions[] = "e.classe_id = ?";
        $where_params[] = $classe_id;
    } elseif ($filiere_id || $niveau_id) {
        if ($filiere_id) {
            $where_conditions[] = "c.filiere_id = ?";
            $where_params[] = $filiere_id;
        }
        if ($niveau_id) {
            $where_conditions[] = "c.niveau_id = ?";
            $where_params[] = $niveau_id;
        }
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Statistiques globales avec filtres
    $sql = "SELECT COUNT(DISTINCT e.id) as total 
            FROM etudiants e 
            JOIN classes c ON e.classe_id = c.id 
            WHERE $where_clause";
    $stmt = $db->prepare($sql);
    $stmt->execute($where_params);
    $stats['total_etudiants'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'enseignant' AND statut = 'actif'");
    $stats['total_enseignants'] = $stmt->fetch()['total'];
    
    $sql = "SELECT COUNT(DISTINCT c.id) as total FROM classes c WHERE c.statut = 'active'";
    if ($classe_id) {
        $sql .= " AND c.id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$classe_id]);
    } elseif ($filiere_id || $niveau_id) {
        $conditions = [];
        $params = [];
        if ($filiere_id) {
            $conditions[] = "c.filiere_id = ?";
            $params[] = $filiere_id;
        }
        if ($niveau_id) {
            $conditions[] = "c.niveau_id = ?";
            $params[] = $niveau_id;
        }
        $sql .= " AND " . implode(' AND ', $conditions);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $db->query($sql);
    }
    $stats['total_classes'] = $stmt->fetch()['total'];
    
    // Répartition par filière (avec filtres)
    $sql = "SELECT f.libelle, COUNT(DISTINCT e.id) as nb_etudiants
            FROM filieres f
            LEFT JOIN classes c ON f.id = c.filiere_id
            LEFT JOIN etudiants e ON c.id = e.classe_id AND e.statut = 'actif'
            WHERE f.statut = 'active'";
    if ($classe_id) {
        $sql .= " AND c.id = ?";
        $stmt = $db->prepare($sql . " GROUP BY f.id ORDER BY nb_etudiants DESC");
        $stmt->execute([$classe_id]);
    } elseif ($niveau_id) {
        $sql .= " AND c.niveau_id = ?";
        $stmt = $db->prepare($sql . " GROUP BY f.id ORDER BY nb_etudiants DESC");
        $stmt->execute([$niveau_id]);
    } else {
        $stmt = $db->query($sql . " GROUP BY f.id ORDER BY nb_etudiants DESC");
    }
    $repartition_filieres = $stmt->fetchAll();
    
    // Répartition par niveau (avec filtres)
    $sql = "SELECT n.libelle, COUNT(DISTINCT e.id) as nb_etudiants
            FROM niveaux n
            LEFT JOIN classes c ON n.id = c.niveau_id
            LEFT JOIN etudiants e ON c.id = e.classe_id AND e.statut = 'actif'
            WHERE n.statut = 'actif'";
    if ($classe_id) {
        $sql .= " AND c.id = ?";
        $stmt = $db->prepare($sql . " GROUP BY n.id ORDER BY n.ordre");
        $stmt->execute([$classe_id]);
    } elseif ($filiere_id) {
        $sql .= " AND c.filiere_id = ?";
        $stmt = $db->prepare($sql . " GROUP BY n.id ORDER BY n.ordre");
        $stmt->execute([$filiere_id]);
    } else {
        $stmt = $db->query($sql . " GROUP BY n.id ORDER BY n.ordre");
    }
    $repartition_niveaux = $stmt->fetchAll();
    
    // Statistiques de paiement
    $stmt = $db->prepare("SELECT 
                          SUM(CASE WHEN statut = 'paye' THEN 1 ELSE 0 END) as payes,
                          SUM(CASE WHEN statut = 'partiel' THEN 1 ELSE 0 END) as partiels,
                          SUM(CASE WHEN statut = 'non_paye' THEN 1 ELSE 0 END) as non_payes,
                          SUM(montant_total) as total_attendu,
                          SUM(montant_paye) as total_collecte
                          FROM paiements
                          WHERE annee_academique_id = ?");
    $stmt->execute([$annee_active['id']]);
    $stats_paiements = $stmt->fetch();
    
    // Statistiques du dernier mois - Étudiants ayant payé vs non payé
    $mois_noms = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    $mois_actuel = $mois_noms[date('n') - 1]; // Mois actuel (1-12 -> 0-11)
    
    // Total étudiants actifs
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM etudiants WHERE statut = 'actif'");
    $stmt->execute();
    $total_etudiants_actifs = $stmt->fetch()['total'];
    
    // Étudiants ayant payé le mois actuel
    $stmt = $db->prepare("SELECT COUNT(DISTINCT etudiant_id) as payes 
                          FROM paiements 
                          WHERE type_frais = ? AND statut = 'paye'");
    $stmt->execute(['Scolarité - ' . $mois_actuel]);
    $etudiants_payes_mois = $stmt->fetch()['payes'];
    
    $etudiants_non_payes_mois = $total_etudiants_actifs - $etudiants_payes_mois;
    
    // Taux d'absences
    $stmt = $db->prepare("SELECT COUNT(*) as total_absences,
                          SUM(CASE WHEN type = 'justifiee' THEN 1 ELSE 0 END) as justifiees,
                          SUM(CASE WHEN type = 'non_justifiee' THEN 1 ELSE 0 END) as non_justifiees
                          FROM absences a
                          JOIN inscriptions i ON a.etudiant_id = i.etudiant_id
                          WHERE i.annee_academique_id = ? AND i.statut = 'validee'");
    $stmt->execute([$annee_active['id']]);
    $stats_absences = $stmt->fetch();
    
    // Top 5 des meilleures moyennes avec filtres
    // Requête simplifiée pour récupérer les 5 meilleurs étudiants
    $sql_top = "SELECT e.id, e.matricule, u.nom, u.prenom, c.libelle as classe,
                AVG((n.note / n.note_sur) * 20) as moyenne
                FROM etudiants e
                INNER JOIN users u ON e.user_id = u.id
                INNER JOIN classes c ON e.classe_id = c.id
                INNER JOIN notes n ON e.id = n.etudiant_id
                WHERE e.statut = 'actif'";
    
    $params_top = [];
    
    if ($classe_id) {
        $sql_top .= " AND c.id = ?";
        $params_top[] = $classe_id;
    } elseif ($filiere_id || $niveau_id) {
        if ($filiere_id) {
            $sql_top .= " AND c.filiere_id = ?";
            $params_top[] = $filiere_id;
        }
        if ($niveau_id) {
            $sql_top .= " AND c.niveau_id = ?";
            $params_top[] = $niveau_id;
        }
    }
    
    $sql_top .= " GROUP BY e.id, e.matricule, u.nom, u.prenom, c.libelle
                  HAVING moyenne IS NOT NULL
                  ORDER BY moyenne DESC LIMIT 5";
    $stmt = $db->prepare($sql_top);
    $stmt->execute($params_top);
    $top_etudiants = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<style>
/* Dashboard BI Professional Styles */
.bi-header {
    background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}
.bi-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 60%;
    height: 200%;
    background: rgba(255,255,255,0.1);
    transform: rotate(30deg);
    pointer-events: none;
}
.bi-header::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 40%;
    height: 150%;
    background: rgba(255,255,255,0.05);
    transform: rotate(-20deg);
    pointer-events: none;
}
.bi-stat-card {
    border-radius: 16px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
}
.bi-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    transform: translate(30%, -30%);
}
.bi-stat-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0,0,0,0.2) !important;
}
.bi-stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
}
.bi-chart-card {
    border-radius: 16px;
    border: none;
    transition: all 0.3s ease;
}
.bi-chart-card:hover {
    box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important;
}
.bi-chart-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-bottom: 3px solid #ef1c5d;
    border-radius: 16px 16px 0 0 !important;
}
.bi-filter-card {
    border-radius: 16px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: 1px solid rgba(239, 28, 93, 0.1);
}
.bi-filter-select {
    border-radius: 10px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}
.bi-filter-select:focus {
    border-color: #ef1c5d;
    box-shadow: 0 0 0 3px rgba(239, 28, 93, 0.15);
}
.bi-table-card {
    border-radius: 16px;
    overflow: hidden;
}
.bi-table-header {
    background: linear-gradient(135deg, #ef1c5d 0%, #c71450 100%);
}
.bi-progress-bar {
    height: 12px;
    border-radius: 6px;
    overflow: hidden;
    background: #e9ecef;
}
.bi-progress-fill {
    height: 100%;
    border-radius: 6px;
    background: linear-gradient(90deg, #ef1c5d 0%, #fcb628 100%);
    transition: width 1s ease;
}
.pulse-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #28a745;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
}
.counter-value {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #ffffff 0%, rgba(255,255,255,0.8) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
</style>

<!-- En-tête BI Professionnel -->
<div class="bi-header mb-4 shadow-lg">
    <div class="card-body text-white p-4 position-relative" style="z-index: 1;">
        <div class="row align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center mb-3">
                    <div class="bi-stat-icon me-3">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                    <div>
                        <h2 class="mb-0 fw-bold" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
                            <i class="fas fa-analytics me-2"></i>Tableau de Bord BI
                        </h2>
                        <p class="mb-0 mt-1" style="opacity: 0.9;">Business Intelligence & Analytics</p>
                    </div>
                </div>
                <?php if ($annee_active): ?>
                <div class="d-flex align-items-center">
                    <span class="pulse-dot me-2"></span>
                    <span class="badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 8px 16px; font-size: 0.9rem;">
                        <i class="fas fa-calendar-alt me-1"></i> <?php echo escape_html($annee_active['libelle']); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-5 text-end">
                <div class="d-flex flex-column align-items-end">
                    <div class="mb-2" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 12px 20px; border-radius: 12px;">
                        <i class="fas fa-clock me-2"></i>
                        <span id="currentTime" style="font-weight: 600; font-size: 1.1rem;"></span>
                    </div>
                    <small style="opacity: 0.8;"><i class="fas fa-sync-alt me-1"></i> Données en temps réel</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres Dynamiques -->
<div class="bi-filter-card mb-4 shadow-sm">
    <div class="card-header bg-transparent border-0 py-3 px-4">
        <div class="d-flex align-items-center">
            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;" class="me-3">
                <i class="fas fa-sliders-h text-white"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-bold" style="color: #333;">Filtres Dynamiques</h5>
                <small class="text-muted">Affinez vos analyses en temps réel</small>
            </div>
        </div>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-bold text-dark"><i class="fas fa-calendar me-1" style="color: #ef1c5d;"></i> Année académique</label>
                <select class="form-select bi-filter-select" id="annee_filter">
                    <?php foreach ($annees as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $a['id'] == $annee_id ? 'selected' : ''; ?>>
                            <?php echo escape_html($a['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-dark"><i class="fas fa-graduation-cap me-1" style="color: #f58024;"></i> Filière</label>
                <select class="form-select bi-filter-select" id="filiere_filter">
                    <option value="">Toutes les filières</option>
                    <?php foreach ($filieres as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php echo $f['id'] == $filiere_id ? 'selected' : ''; ?>>
                            <?php echo escape_html($f['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-dark"><i class="fas fa-layer-group me-1" style="color: #fcb628;"></i> Niveau</label>
                <select class="form-select bi-filter-select" id="niveau_filter">
                    <option value="">Tous les niveaux</option>
                    <?php foreach ($niveaux as $n): ?>
                        <option value="<?php echo $n['id']; ?>" <?php echo $n['id'] == $niveau_id ? 'selected' : ''; ?>>
                            <?php echo escape_html($n['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-dark"><i class="fas fa-school me-1" style="color: #ef1c5d;"></i> Classe</label>
                <select class="form-select bi-filter-select" id="classe_filter">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $cl): ?>
                        <option value="<?php echo $cl['id']; ?>" <?php echo $cl['id'] == $classe_id ? 'selected' : ''; ?>>
                            <?php echo escape_html($cl['filiere'] . ' - ' . $cl['niveau'] . ' - ' . $cl['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<?php if (!$annee_active): ?>
<div class="alert alert-warning" style="border-radius: 12px; border-left: 5px solid #fcb628;">
    <i class="fas fa-exclamation-triangle me-2"></i> Aucune année académique active. Veuillez configurer une année académique.
</div>
<?php else: ?>

<!-- KPIs Professionnels -->
<div class="row mb-4 g-4">
    <div class="col-xl-3 col-md-6">
        <div class="bi-stat-card shadow-lg" style="background: linear-gradient(135deg, #ef1c5d 0%, #c71450 100%);">
            <div class="card-body text-white p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px; opacity: 0.9;">
                            <i class="fas fa-user-graduate me-1"></i> Étudiants
                        </p>
                        <h2 class="counter-value mb-1" style="color: white !important; -webkit-text-fill-color: white;"><?php echo $stats['total_etudiants']; ?></h2>
                        <small style="opacity: 0.8;"><i class="fas fa-arrow-up me-1"></i> Inscrits actifs</small>
                    </div>
                    <div class="bi-stat-icon">
                        <i class="fas fa-user-graduate fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="bi-stat-card shadow-lg" style="background: linear-gradient(135deg, #f58024 0%, #d66a1a 100%);">
            <div class="card-body text-white p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px; opacity: 0.9;">
                            <i class="fas fa-chalkboard-teacher me-1"></i> Enseignants
                        </p>
                        <h2 class="counter-value mb-1" style="color: white !important; -webkit-text-fill-color: white;"><?php echo $stats['total_enseignants']; ?></h2>
                        <small style="opacity: 0.8;"><i class="fas fa-check-circle me-1"></i> Corps professoral</small>
                    </div>
                    <div class="bi-stat-icon">
                        <i class="fas fa-chalkboard-teacher fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="bi-stat-card shadow-lg" style="background: linear-gradient(135deg, #fcb628 0%, #e09e1a 100%);">
            <div class="card-body text-white p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px; opacity: 0.9; color: #333;">
                            <i class="fas fa-school me-1"></i> Classes
                        </p>
                        <h2 class="counter-value mb-1" style="color: #333 !important; -webkit-text-fill-color: #333;"><?php echo $stats['total_classes']; ?></h2>
                        <small style="opacity: 0.8; color: #333;"><i class="fas fa-door-open me-1"></i> Salles actives</small>
                    </div>
                    <div class="bi-stat-icon" style="background: rgba(0,0,0,0.1);">
                        <i class="fas fa-school fa-lg" style="color: #333;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="bi-stat-card shadow-lg" style="background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);">
            <div class="card-body text-white p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px; opacity: 0.9;">
                            <i class="fas fa-calendar-times me-1"></i> Absences
                        </p>
                        <h2 class="counter-value mb-1" style="color: white !important; -webkit-text-fill-color: white;"><?php echo $stats_absences['total_absences']; ?></h2>
                        <small style="opacity: 0.8;"><i class="fas fa-exclamation-circle me-1"></i> Total enregistré</small>
                    </div>
                    <div class="bi-stat-icon">
                        <i class="fas fa-calendar-times fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques Analytics -->
<div class="row mb-4 g-4">
    <!-- Répartition par filière -->
    <div class="col-lg-6">
        <div class="bi-chart-card shadow-sm h-100">
            <div class="bi-chart-header py-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;" class="me-3">
                            <i class="fas fa-chart-pie text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold" style="color: #333;">Répartition par filière</h6>
                            <small class="text-muted">Distribution des étudiants</small>
                        </div>
                    </div>
                    <span class="badge" style="background: #ef1c5d; color: white;">Live</span>
                </div>
            </div>
            <div class="card-body p-4">
                <canvas id="filiereChart" height="280"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Répartition par niveau -->
    <div class="col-lg-6">
        <div class="bi-chart-card shadow-sm h-100">
            <div class="bi-chart-header py-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #f58024 0%, #fcb628 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;" class="me-3">
                            <i class="fas fa-chart-bar text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold" style="color: #333;">Répartition par niveau</h6>
                            <small class="text-muted">Progression académique</small>
                        </div>
                    </div>
                    <span class="badge" style="background: #f58024; color: white;">Analytics</span>
                </div>
            </div>
            <div class="card-body p-4">
                <canvas id="niveauChart" height="280"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Finances & Absences -->
<div class="row mb-4 g-4">
    <!-- Statistiques de paiement du mois -->
    <div class="col-lg-6">
        <div class="bi-chart-card shadow-sm h-100">
            <div class="card-header py-3 px-4" style="background: linear-gradient(135deg, #ef1c5d 0%, #c71450 100%); border-radius: 16px 16px 0 0;">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="bi-stat-icon me-3" style="width: 45px; height: 45px;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="text-white">
                            <h6 class="mb-0 fw-bold">Statistiques Financières</h6>
                            <small style="opacity: 0.8;">Paiements du mois de <?php echo $mois_actuel; ?></small>
                        </div>
                    </div>
                    <span class="badge" style="background: rgba(255,255,255,0.2);"><?php echo $mois_actuel; ?></span>
                </div>
            </div>
            <div class="card-body p-4">
                <!-- Indicateurs du mois actuel -->
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="text-center p-4" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 12px;">
                            <div style="width: 60px; height: 60px; background: #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                                <i class="fas fa-user-check text-white fa-lg"></i>
                            </div>
                            <h2 class="mb-1 fw-bold" style="color: #155724;"><?php echo $etudiants_payes_mois; ?></h2>
                            <small class="fw-bold" style="color: #155724;">Étudiants ayant payé</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-4" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-radius: 12px;">
                            <div style="width: 60px; height: 60px; background: #ef1c5d; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                                <i class="fas fa-user-times text-white fa-lg"></i>
                            </div>
                            <h2 class="mb-1 fw-bold" style="color: #721c24;"><?php echo $etudiants_non_payes_mois; ?></h2>
                            <small class="fw-bold" style="color: #721c24;">Étudiants n'ayant pas payé</small>
                        </div>
                    </div>
                </div>
                
                <!-- Barre de progression du mois -->
                <?php 
                $taux_paiement_mois = $total_etudiants_actifs > 0 ? 
                    ($etudiants_payes_mois / $total_etudiants_actifs) * 100 : 0;
                ?>
                <div class="mb-2 d-flex justify-content-between">
                    <span class="fw-bold" style="color: #333;">Taux de paiement - <?php echo $mois_actuel; ?></span>
                    <span class="fw-bold" style="color: #ef1c5d;"><?php echo number_format($taux_paiement_mois, 1); ?>%</span>
                </div>
                <div class="bi-progress-bar mb-3">
                    <div class="bi-progress-fill" style="width: <?php echo $taux_paiement_mois; ?>%;"></div>
                </div>
                
                <!-- Résumé -->
                <div class="p-3" style="background: #f8f9fa; border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Total étudiants actifs</span>
                        <span class="fw-bold"><?php echo $total_etudiants_actifs; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistiques d'absences -->
    <div class="col-lg-6">
        <div class="bi-chart-card shadow-sm h-100">
            <div class="card-header py-3 px-4" style="background: linear-gradient(135deg, #fcb628 0%, #e09e1a 100%); border-radius: 16px 16px 0 0;">
                <div class="d-flex align-items-center">
                    <div class="bi-stat-icon me-3" style="width: 45px; height: 45px; background: rgba(0,0,0,0.1);">
                        <i class="fas fa-calendar-times" style="color: #333;"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="color: #333;">Analyse des Absences</h6>
                        <small style="opacity: 0.8; color: #333;">Suivi de la présence</small>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <canvas id="absenceChart" height="280"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Top 5 des meilleurs étudiants -->
<div class="row mb-4">
    <div class="col-12">
        <div class="bi-table-card shadow-sm">
            <div class="bi-table-header py-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center text-white">
                        <div class="bi-stat-icon me-3" style="width: 45px; height: 45px;">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Top 5 - Excellence Académique</h6>
                            <small style="opacity: 0.8;">Meilleurs étudiants de l'année</small>
                        </div>
                    </div>
                    <span class="badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                        <i class="fas fa-star me-1"></i> Classement
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($top_etudiants)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Aucune note disponible pour le moment.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background: #f8f9fa;">
                                <tr>
                                    <th class="py-3 px-4" style="border: none; font-weight: 700; color: #333;">Rang</th>
                                    <th class="py-3" style="border: none; font-weight: 700; color: #333;">Matricule</th>
                                    <th class="py-3" style="border: none; font-weight: 700; color: #333;">Nom complet</th>
                                    <th class="py-3" style="border: none; font-weight: 700; color: #333;">Classe</th>
                                    <th class="py-3 px-4" style="border: none; font-weight: 700; color: #333;">Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_etudiants as $index => $etudiant): ?>
                                <tr style="transition: all 0.3s ease;" onmouseover="this.style.background='#fef5f8'" onmouseout="this.style.background='transparent'">
                                    <td class="py-3 px-4" style="border-bottom: 1px solid #eee;">
                                        <?php if ($index === 0): ?>
                                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #ffd700 0%, #ffb800 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(255,215,0,0.4);">
                                                <i class="fas fa-crown text-white"></i>
                                            </div>
                                        <?php elseif ($index === 1): ?>
                                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #c0c0c0 0%, #a8a8a8 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(192,192,192,0.4);">
                                                <i class="fas fa-medal text-white"></i>
                                            </div>
                                        <?php elseif ($index === 2): ?>
                                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #cd7f32 0%, #b87333 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(205,127,50,0.4);">
                                                <i class="fas fa-award text-white"></i>
                                            </div>
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <span class="fw-bold" style="color: #666;"><?php echo $index + 1; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3" style="border-bottom: 1px solid #eee;">
                                        <span class="badge" style="background: #ef1c5d; padding: 6px 12px;"><?php echo escape_html($etudiant['matricule']); ?></span>
                                    </td>
                                    <td class="py-3" style="border-bottom: 1px solid #eee;">
                                        <div class="d-flex align-items-center">
                                            <div style="width: 35px; height: 35px; background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                                                <span class="text-white fw-bold" style="font-size: 0.8rem;"><?php echo strtoupper(substr($etudiant['prenom'], 0, 1) . substr($etudiant['nom'], 0, 1)); ?></span>
                                            </div>
                                            <span class="fw-bold" style="color: #333;"><?php echo escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3" style="border-bottom: 1px solid #eee;">
                                        <span class="badge" style="background: #f58024; padding: 6px 12px;"><?php echo escape_html($etudiant['classe']); ?></span>
                                    </td>
                                    <td class="py-3 px-4" style="border-bottom: 1px solid #eee;">
                                        <div class="d-flex align-items-center">
                                            <div style="width: 60px; height: 8px; background: #e9ecef; border-radius: 4px; margin-right: 10px; overflow: hidden;">
                                                <div style="width: <?php echo ($etudiant['moyenne'] / 20) * 100; ?>%; height: 100%; background: linear-gradient(90deg, #ef1c5d 0%, #fcb628 100%); border-radius: 4px;"></div>
                                            </div>
                                            <strong style="color: <?php echo $etudiant['moyenne'] >= 14 ? '#28a745' : ($etudiant['moyenne'] >= 10 ? '#f58024' : '#dc3545'); ?>; font-size: 1.1rem;">
                                                <?php echo number_format($etudiant['moyenne'], 2); ?><small>/20</small>
                                            </strong>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Couleurs de l'école IPP
const ippColors = {
    primary: '#ef1c5d',
    secondary: '#f58024',
    accent: '#fcb628',
    dark: '#2c3e50',
    success: '#28a745',
    danger: '#dc3545'
};

// Palette de couleurs harmonieuse
const chartPalette = ['#ef1c5d', '#f58024', '#fcb628', '#2c3e50', '#ff6b9d', '#ffa726', '#ab47bc'];

// Graphique répartition par filière - Doughnut moderne
const filiereData = {
    labels: [<?php echo implode(',', array_map(function($f) { return "'" . addslashes($f['libelle']) . "'"; }, $repartition_filieres)); ?>],
    datasets: [{
        label: 'Étudiants',
        data: [<?php echo implode(',', array_column($repartition_filieres, 'nb_etudiants')); ?>],
        backgroundColor: chartPalette,
        borderWidth: 0,
        hoverOffset: 10
    }]
};

new Chart(document.getElementById('filiereChart'), {
    type: 'doughnut',
    data: filiereData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: { 
                position: 'bottom',
                labels: { 
                    font: { size: 12, weight: '500' },
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                titleFont: { size: 14, weight: 'bold' },
                bodyFont: { size: 13 },
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' étudiants (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Graphique répartition par niveau - Bar chart moderne
const niveauData = {
    labels: [<?php echo implode(',', array_map(function($n) { return "'" . addslashes($n['libelle']) . "'"; }, $repartition_niveaux)); ?>],
    datasets: [{
        label: 'Nombre d\'étudiants',
        data: [<?php echo implode(',', array_column($repartition_niveaux, 'nb_etudiants')); ?>],
        backgroundColor: function(context) {
            const chart = context.chart;
            const {ctx, chartArea} = chart;
            if (!chartArea) return '#ef1c5d';
            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
            gradient.addColorStop(0, '#ef1c5d');
            gradient.addColorStop(1, '#f58024');
            return gradient;
        },
        borderRadius: 8,
        borderSkipped: false,
        barThickness: 40
    }]
};

new Chart(document.getElementById('niveauChart'), {
    type: 'bar',
    data: niveauData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                titleFont: { size: 14, weight: 'bold' },
                bodyFont: { size: 13 },
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + ' étudiants';
                    }
                }
            }
        },
        scales: {
            y: { 
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.05)',
                    drawBorder: false
                },
                ticks: {
                    font: { size: 12, weight: '500' },
                    color: '#666'
                }
            },
            x: {
                grid: { display: false },
                ticks: {
                    font: { size: 12, weight: '500' },
                    color: '#666'
                }
            }
        }
    }
});

// Graphique absences - Doughnut avec centre
const absenceData = {
    labels: ['Justifiées', 'Non justifiées'],
    datasets: [{
        label: 'Absences',
        data: [<?php echo $stats_absences['justifiees']; ?>, <?php echo $stats_absences['non_justifiees']; ?>],
        backgroundColor: [ippColors.success, ippColors.primary],
        borderWidth: 0,
        hoverOffset: 8
    }]
};

const absenceChart = new Chart(document.getElementById('absenceChart'), {
    type: 'doughnut',
    data: absenceData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: { 
                position: 'bottom',
                labels: { 
                    font: { size: 12, weight: '500' },
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                titleFont: { size: 14, weight: 'bold' },
                bodyFont: { size: 13 },
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    },
    plugins: [{
        id: 'centerText',
        beforeDraw: function(chart) {
            const {ctx, chartArea: {width, height}} = chart;
            ctx.save();
            const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
            ctx.font = 'bold 24px Arial';
            ctx.fillStyle = '#333';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(total, width / 2, height / 2 - 10);
            ctx.font = '12px Arial';
            ctx.fillStyle = '#666';
            ctx.fillText('Total', width / 2, height / 2 + 15);
            ctx.restore();
        }
    }]
});

// Horloge en temps réel
function updateClock() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
    document.getElementById('currentTime').textContent = now.toLocaleDateString('fr-FR', options);
}
updateClock();
setInterval(updateClock, 1000);

// Les filtres automatiques sont gérés par filters.js
</script>

<?php include '../includes/footer.php'; ?>
