<?php
require_once '../config/config.php';
require_role(['enseignant']);

$page_title = 'Tableau de bord Enseignant';
$db = Database::getInstance()->getConnection();

// Récupérer l'ID de l'enseignant
$stmt = $db->prepare("SELECT id FROM enseignants WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$enseignant = $stmt->fetch();

if (!$enseignant) {
    set_flash_message('Profil enseignant non trouvé', 'error');
    redirect('index.php');
}

$enseignant_id = $enseignant['id'];

// Filtres automatiques
$annee_id = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : null;
$classe_id = isset($_GET['classe_id']) ? intval($_GET['classe_id']) : null;
$matiere_id = isset($_GET['matiere_id']) ? intval($_GET['matiere_id']) : null;

// Récupérer les données pour les filtres
$annees = $db->query("SELECT * FROM annees_academiques ORDER BY libelle DESC")->fetchAll();

// Année active par défaut
if (!$annee_id) {
    $stmt = $db->query("SELECT * FROM annees_academiques WHERE statut = 'active' LIMIT 1");
    $annee_active = $stmt->fetch();
    if ($annee_active) {
        $annee_id = $annee_active['id'];
    }
}

// Classes de l'enseignant
$stmt = $db->prepare("SELECT DISTINCT c.id, c.libelle, f.libelle as filiere, n.libelle as niveau
                      FROM classes c
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      JOIN classe_matieres cm ON c.id = cm.classe_id
                      WHERE cm.enseignant_id = ? AND c.statut = 'active'
                      ORDER BY f.libelle, n.ordre");
$stmt->execute([$enseignant_id]);
$mes_classes = $stmt->fetchAll();

// Matières de l'enseignant
$stmt = $db->prepare("SELECT DISTINCT m.id, m.libelle, m.code
                      FROM matieres m
                      JOIN classe_matieres cm ON m.id = cm.matiere_id
                      WHERE cm.enseignant_id = ?
                      ORDER BY m.libelle");
$stmt->execute([$enseignant_id]);
$mes_matieres = $stmt->fetchAll();

// KPIs
$stats = [];

// Nombre total de classes
$where_classe = $classe_id ? "AND c.id = ?" : "";
$params_classe = $classe_id ? [$enseignant_id, $classe_id] : [$enseignant_id];

$sql = "SELECT COUNT(DISTINCT c.id) as total
        FROM classes c
        JOIN classe_matieres cm ON c.id = cm.classe_id
        WHERE cm.enseignant_id = ? $where_classe";
$stmt = $db->prepare($sql);
$stmt->execute($params_classe);
$stats['total_classes'] = $stmt->fetch()['total'];

// Nombre total d'étudiants
$where_conditions = ["cm.enseignant_id = ?", "e.statut = 'actif'"];
$params = [$enseignant_id];

if ($classe_id) {
    $where_conditions[] = "c.id = ?";
    $params[] = $classe_id;
}

$where_clause = implode(' AND ', $where_conditions);

$sql = "SELECT COUNT(DISTINCT e.id) as total
        FROM etudiants e
        JOIN classes c ON e.classe_id = c.id
        JOIN classe_matieres cm ON c.id = cm.classe_id
        WHERE $where_clause";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$stats['total_etudiants'] = $stmt->fetch()['total'];

// Nombre de matières enseignées
$where_matiere = $matiere_id ? "AND m.id = ?" : "";
$params_matiere = $matiere_id ? [$enseignant_id, $matiere_id] : [$enseignant_id];

$sql = "SELECT COUNT(DISTINCT m.id) as total
        FROM matieres m
        JOIN classe_matieres cm ON m.id = cm.matiere_id
        WHERE cm.enseignant_id = ? $where_matiere";
$stmt = $db->prepare($sql);
$stmt->execute($params_matiere);
$stats['total_matieres'] = $stmt->fetch()['total'];

// Nombre d'absences à justifier
$sql = "SELECT COUNT(*) as total
        FROM absences a
        JOIN etudiants e ON a.etudiant_id = e.id
        JOIN classes c ON e.classe_id = c.id
        JOIN classe_matieres cm ON c.id = cm.classe_id
        WHERE cm.enseignant_id = ? AND a.type = 'non_justifiee'";
$params_abs = [$enseignant_id];

if ($classe_id) {
    $sql .= " AND c.id = ?";
    $params_abs[] = $classe_id;
}

$stmt = $db->prepare($sql);
$stmt->execute($params_abs);
$stats['absences_non_justifiees'] = $stmt->fetch()['total'];

// Statistiques de notes par matière
$sql = "SELECT m.libelle, m.code,
        COUNT(DISTINCT n.id) as nb_notes,
        AVG((n.note / n.note_sur) * 20) as moyenne,
        MIN((n.note / n.note_sur) * 20) as note_min,
        MAX((n.note / n.note_sur) * 20) as note_max
        FROM matieres m
        JOIN classe_matieres cm ON m.id = cm.matiere_id
        LEFT JOIN notes n ON cm.id = n.classe_matiere_id
        WHERE cm.enseignant_id = ?";

$params_notes = [$enseignant_id];

if ($classe_id) {
    $sql .= " AND cm.classe_id = ?";
    $params_notes[] = $classe_id;
}

if ($matiere_id) {
    $sql .= " AND m.id = ?";
    $params_notes[] = $matiere_id;
}

$sql .= " GROUP BY m.id ORDER BY m.libelle";
$stmt = $db->prepare($sql);
$stmt->execute($params_notes);
$stats_matieres = $stmt->fetchAll();

// Répartition des étudiants par classe
$sql = "SELECT c.libelle, f.libelle as filiere, n.libelle as niveau,
        COUNT(DISTINCT e.id) as nb_etudiants
        FROM classes c
        JOIN filieres f ON c.filiere_id = f.id
        JOIN niveaux n ON c.niveau_id = n.id
        JOIN classe_matieres cm ON c.id = cm.classe_id
        LEFT JOIN etudiants e ON c.id = e.classe_id AND e.statut = 'actif'
        WHERE cm.enseignant_id = ?";

$params_rep = [$enseignant_id];

if ($classe_id) {
    $sql .= " AND c.id = ?";
    $params_rep[] = $classe_id;
}

$sql .= " GROUP BY c.id ORDER BY f.libelle, n.ordre";
$stmt = $db->prepare($sql);
$stmt->execute($params_rep);
$repartition_classes = $stmt->fetchAll();

// Top 5 meilleurs étudiants
$sql = "SELECT e.matricule, u.nom, u.prenom, c.libelle as classe,
        AVG((n.note / n.note_sur) * 20) as moyenne
        FROM notes n
        JOIN classe_matieres cm ON n.classe_matiere_id = cm.id
        JOIN etudiants e ON n.etudiant_id = e.id
        JOIN users u ON e.user_id = u.id
        JOIN classes c ON e.classe_id = c.id
        WHERE cm.enseignant_id = ? AND e.statut = 'actif'";

$params_top = [$enseignant_id];

if ($classe_id) {
    $sql .= " AND c.id = ?";
    $params_top[] = $classe_id;
}

if ($matiere_id) {
    $sql .= " AND cm.matiere_id = ?";
    $params_top[] = $matiere_id;
}

$sql .= " GROUP BY e.id ORDER BY moyenne DESC LIMIT 5";
$stmt = $db->prepare($sql);
$stmt->execute($params_top);
$top_etudiants = $stmt->fetchAll();

// Absences récentes
$sql = "SELECT a.*, e.matricule, u.nom, u.prenom, c.libelle as classe, m.libelle as matiere
        FROM absences a
        JOIN etudiants e ON a.etudiant_id = e.id
        JOIN users u ON e.user_id = u.id
        JOIN classes c ON e.classe_id = c.id
        JOIN classe_matieres cm ON a.classe_matiere_id = cm.id
        JOIN matieres m ON cm.matiere_id = m.id
        WHERE cm.enseignant_id = ?";

$params_abs_rec = [$enseignant_id];

if ($classe_id) {
    $sql .= " AND c.id = ?";
    $params_abs_rec[] = $classe_id;
}

$sql .= " ORDER BY a.date_absence DESC LIMIT 10";
$stmt = $db->prepare($sql);
$stmt->execute($params_abs_rec);
$absences_recentes = $stmt->fetchAll();

include '../includes/header.php';
?>

<style>
/* Styles Espace Enseignant */
.teacher-header {
    background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}
.teacher-header::before {
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
.teacher-stat-card {
    border-radius: 16px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
}
.teacher-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    transform: translate(20%, -20%);
}
.teacher-stat-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15) !important;
}
.teacher-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.2);
}
.teacher-filter-card {
    border-radius: 16px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: 1px solid rgba(239, 28, 93, 0.1);
}
.teacher-chart-card {
    border-radius: 16px;
    border: none;
}
.teacher-table-card {
    border-radius: 16px;
    overflow: hidden;
}
</style>

<!-- En-tête Enseignant -->
<div class="teacher-header mb-4 shadow-lg">
    <div class="card-body text-white p-4 position-relative" style="z-index: 1;">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center mb-3">
                    <div style="width: 70px; height: 70px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
                    </div>
                    <div>
                        <h2 class="mb-0 fw-bold" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">Espace Enseignant</h2>
                        <p class="mb-0 mt-1" style="opacity: 0.9;">Bienvenue, <?php echo escape_html($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?></p>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 8px 16px;">
                        <i class="fas fa-school me-1"></i> <?php echo $stats['total_classes']; ?> classe(s)
                    </span>
                    <span class="badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 8px 16px;">
                        <i class="fas fa-book me-1"></i> <?php echo $stats['total_matieres']; ?> matière(s)
                    </span>
                    <span class="badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 8px 16px;">
                        <i class="fas fa-user-graduate me-1"></i> <?php echo $stats['total_etudiants']; ?> étudiant(s)
                    </span>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 12px 20px; border-radius: 12px; display: inline-block;">
                    <i class="fas fa-clock me-2"></i>
                    <span id="currentTime" style="font-weight: 600;"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres Dynamiques -->
<div class="teacher-filter-card mb-4 shadow-sm">
    <div class="card-header bg-transparent border-0 py-3 px-4">
        <div class="d-flex align-items-center">
            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;" class="me-3">
                <i class="fas fa-sliders-h text-white"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold" style="color: #333;">Filtres Dynamiques</h6>
                <small class="text-muted">Filtrez vos données</small>
            </div>
        </div>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold text-dark"><i class="fas fa-calendar me-1" style="color: #ef1c5d;"></i> Année académique</label>
                <select class="form-select" id="annee_filter" style="border-radius: 10px; border: 2px solid #e9ecef;">
                    <?php foreach ($annees as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $a['id'] == $annee_id ? 'selected' : ''; ?>>
                            <?php echo escape_html($a['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-dark"><i class="fas fa-school me-1" style="color: #f58024;"></i> Classe</label>
                <select class="form-select" id="classe_filter" style="border-radius: 10px; border: 2px solid #e9ecef;">
                    <option value="">Toutes mes classes</option>
                    <?php foreach ($mes_classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $classe_id ? 'selected' : ''; ?>>
                            <?php echo escape_html($c['filiere'] . ' - ' . $c['niveau'] . ' - ' . $c['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-dark"><i class="fas fa-book me-1" style="color: #fcb628;"></i> Matière</label>
                <select class="form-select" id="matiere_filter" style="border-radius: 10px; border: 2px solid #e9ecef;">
                    <option value="">Toutes mes matières</option>
                    <?php foreach ($mes_matieres as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $m['id'] == $matiere_id ? 'selected' : ''; ?>>
                            <?php echo escape_html($m['code'] . ' - ' . $m['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Liens Rapides Enseignant -->
<div class="row mb-4 g-3">
    <div class="col-12">
        <div class="d-flex flex-wrap gap-2">
            <a href="notes.php" class="btn btn-sm" style="background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); color: white; border-radius: 20px; padding: 10px 20px;">
                <i class="fas fa-edit me-2"></i>Saisir des Notes
            </a>
            <a href="absences.php" class="btn btn-sm" style="background: linear-gradient(135deg, #f58024 0%, #fcb628 100%); color: white; border-radius: 20px; padding: 10px 20px;">
                <i class="fas fa-user-times me-2"></i>Gérer les Absences
            </a>
            <a href="mes-classes.php" class="btn btn-sm" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 20px; padding: 10px 20px;">
                <i class="fas fa-users me-2"></i>Mes Classes
            </a>
            <a href="emploi-du-temps.php" class="btn btn-sm" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%); color: white; border-radius: 20px; padding: 10px 20px;">
                <i class="fas fa-calendar-alt me-2"></i>Emploi du temps
            </a>
        </div>
    </div>
</div>

<!-- KPIs Enseignant -->
<div class="row mb-4 g-4">
    <div class="col-xl-3 col-md-6">
        <div class="teacher-stat-card shadow" style="background: linear-gradient(135deg, #ef1c5d 0%, #c71450 100%);">
            <div class="card-body text-white p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px; opacity: 0.9;">
                            <i class="fas fa-school me-1"></i> Mes Classes
                        </p>
                        <h2 class="mb-1 fw-bold" style="font-size: 2rem;"><?php echo $stats['total_classes']; ?></h2>
                        <small style="opacity: 0.8;"><i class="fas fa-door-open me-1"></i> Classes assignées</small>
                    </div>
                    <div class="teacher-stat-icon">
                        <i class="fas fa-school fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="teacher-stat-card shadow" style="background: linear-gradient(135deg, #f58024 0%, #d66a1a 100%);">
            <div class="card-body text-white p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px; opacity: 0.9;">
                            <i class="fas fa-user-graduate me-1"></i> Étudiants
                        </p>
                        <h2 class="mb-1 fw-bold" style="font-size: 2rem;"><?php echo $stats['total_etudiants']; ?></h2>
                        <small style="opacity: 0.8;"><i class="fas fa-users me-1"></i> Total encadré</small>
                    </div>
                    <div class="teacher-stat-icon">
                        <i class="fas fa-user-graduate fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="teacher-stat-card shadow" style="background: linear-gradient(135deg, #fcb628 0%, #e09e1a 100%);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px; opacity: 0.9; color: #333;">
                            <i class="fas fa-book me-1"></i> Matières
                        </p>
                        <h2 class="mb-1 fw-bold" style="font-size: 2rem; color: #333;"><?php echo $stats['total_matieres']; ?></h2>
                        <small style="opacity: 0.8; color: #333;"><i class="fas fa-bookmark me-1"></i> Enseignées</small>
                    </div>
                    <div class="teacher-stat-icon" style="background: rgba(0,0,0,0.1);">
                        <i class="fas fa-book fa-lg" style="color: #333;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="teacher-stat-card shadow" style="background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);">
            <div class="card-body text-white p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px; opacity: 0.9;">
                            <i class="fas fa-calendar-times me-1"></i> Absences
                        </p>
                        <h2 class="mb-1 fw-bold" style="font-size: 2rem;"><?php echo $stats['absences_non_justifiees']; ?></h2>
                        <small style="opacity: 0.8;"><i class="fas fa-exclamation-triangle me-1"></i> Non justifiées</small>
                    </div>
                    <div class="teacher-stat-icon">
                        <i class="fas fa-calendar-times fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques Analytics -->
<div class="row mb-4 g-4">
    <!-- Répartition par classe -->
    <div class="col-lg-6">
        <div class="teacher-chart-card shadow-sm h-100">
            <div class="card-header py-3 px-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border-bottom: 3px solid #ef1c5d; border-radius: 16px 16px 0 0;">
                <div class="d-flex align-items-center">
                    <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;" class="me-3">
                        <i class="fas fa-chart-bar text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="color: #333;">Répartition par classe</h6>
                        <small class="text-muted">Nombre d'étudiants par classe</small>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <canvas id="classeChart" height="280"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Statistiques par matière -->
    <div class="col-lg-6">
        <div class="teacher-chart-card shadow-sm h-100">
            <div class="card-header py-3 px-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border-bottom: 3px solid #f58024; border-radius: 16px 16px 0 0;">
                <div class="d-flex align-items-center">
                    <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #f58024 0%, #fcb628 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;" class="me-3">
                        <i class="fas fa-chart-line text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="color: #333;">Moyennes par matière</h6>
                        <small class="text-muted">Performance de vos étudiants</small>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <canvas id="matiereChart" height="280"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Top 5 & Absences -->
<div class="row mb-4 g-4">
    <!-- Top 5 meilleurs étudiants -->
    <div class="col-lg-6">
        <div class="teacher-table-card shadow-sm h-100">
            <div class="card-header py-3 px-4" style="background: linear-gradient(135deg, #ef1c5d 0%, #c71450 100%);">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center text-white">
                        <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;" class="me-3">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Top 5 Étudiants</h6>
                            <small style="opacity: 0.8;">Meilleures performances</small>
                        </div>
                    </div>
                    <span class="badge" style="background: rgba(255,255,255,0.2);"><i class="fas fa-star"></i></span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($top_etudiants)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Aucune note disponible.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background: #f8f9fa;">
                                <tr>
                                    <th class="py-3 px-3" style="border: none; font-weight: 700; color: #333; width: 60px;">Rang</th>
                                    <th class="py-3" style="border: none; font-weight: 700; color: #333;">Étudiant</th>
                                    <th class="py-3" style="border: none; font-weight: 700; color: #333;">Classe</th>
                                    <th class="py-3 px-3" style="border: none; font-weight: 700; color: #333;">Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_etudiants as $index => $etudiant): ?>
                                <tr style="transition: all 0.3s ease;" onmouseover="this.style.background='#fef5f8'" onmouseout="this.style.background='transparent'">
                                    <td class="py-3 px-3" style="border-bottom: 1px solid #eee;">
                                        <?php if ($index === 0): ?>
                                            <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #ffd700 0%, #ffb800 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-crown text-white" style="font-size: 0.8rem;"></i>
                                            </div>
                                        <?php elseif ($index === 1): ?>
                                            <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #c0c0c0 0%, #a8a8a8 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-medal text-white" style="font-size: 0.8rem;"></i>
                                            </div>
                                        <?php elseif ($index === 2): ?>
                                            <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #cd7f32 0%, #b87333 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-award text-white" style="font-size: 0.8rem;"></i>
                                            </div>
                                        <?php else: ?>
                                            <div style="width: 32px; height: 32px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <span class="fw-bold" style="color: #666; font-size: 0.9rem;"><?php echo $index + 1; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3" style="border-bottom: 1px solid #eee;">
                                        <div class="d-flex align-items-center">
                                            <div style="width: 30px; height: 30px; background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 8px;">
                                                <span class="text-white fw-bold" style="font-size: 0.7rem;"><?php echo strtoupper(substr($etudiant['prenom'], 0, 1)); ?></span>
                                            </div>
                                            <div>
                                                <span class="fw-bold" style="color: #333; font-size: 0.9rem;"><?php echo escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></span>
                                                <br><small class="text-muted"><?php echo escape_html($etudiant['matricule']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3" style="border-bottom: 1px solid #eee;">
                                        <span class="badge" style="background: #f58024; padding: 5px 10px; font-size: 0.75rem;"><?php echo escape_html($etudiant['classe']); ?></span>
                                    </td>
                                    <td class="py-3 px-3" style="border-bottom: 1px solid #eee;">
                                        <strong style="color: <?php echo $etudiant['moyenne'] >= 14 ? '#28a745' : ($etudiant['moyenne'] >= 10 ? '#f58024' : '#ef1c5d'); ?>;">
                                            <?php echo number_format($etudiant['moyenne'], 2); ?><small>/20</small>
                                        </strong>
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
    
    <!-- Absences récentes -->
    <div class="col-lg-6">
        <div class="teacher-table-card shadow-sm h-100">
            <div class="card-header py-3 px-4" style="background: linear-gradient(135deg, #fcb628 0%, #e09e1a 100%);">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; background: rgba(0,0,0,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;" class="me-3">
                            <i class="fas fa-calendar-times" style="color: #333;"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold" style="color: #333;">Absences récentes</h6>
                            <small style="opacity: 0.8; color: #333;">Dernières absences enregistrées</small>
                        </div>
                    </div>
                    <span class="badge" style="background: rgba(0,0,0,0.1); color: #333;"><?php echo count($absences_recentes); ?></span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($absences_recentes)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p class="text-muted mb-0">Aucune absence enregistrée.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background: #f8f9fa;">
                                <tr>
                                    <th class="py-3 px-3" style="border: none; font-weight: 700; color: #333;">Date</th>
                                    <th class="py-3" style="border: none; font-weight: 700; color: #333;">Étudiant</th>
                                    <th class="py-3" style="border: none; font-weight: 700; color: #333;">Matière</th>
                                    <th class="py-3 px-3" style="border: none; font-weight: 700; color: #333;">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($absences_recentes, 0, 6) as $absence): ?>
                                <tr style="transition: all 0.3s ease;" onmouseover="this.style.background='#fffbf0'" onmouseout="this.style.background='transparent'">
                                    <td class="py-2 px-3" style="border-bottom: 1px solid #eee;">
                                        <small class="fw-bold" style="color: #666;"><?php echo date('d/m/Y', strtotime($absence['date_absence'])); ?></small>
                                    </td>
                                    <td class="py-2" style="border-bottom: 1px solid #eee;">
                                        <span style="font-size: 0.85rem;"><?php echo escape_html($absence['prenom'] . ' ' . $absence['nom']); ?></span>
                                    </td>
                                    <td class="py-2" style="border-bottom: 1px solid #eee;">
                                        <span class="badge" style="background: #f8f9fa; color: #333; font-size: 0.75rem;"><?php echo escape_html($absence['matiere']); ?></span>
                                    </td>
                                    <td class="py-2 px-3" style="border-bottom: 1px solid #eee;">
                                        <?php if ($absence['type'] === 'justifiee'): ?>
                                            <span class="badge" style="background: #28a745; font-size: 0.7rem;"><i class="fas fa-check me-1"></i>Justifiée</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #ef1c5d; font-size: 0.7rem;"><i class="fas fa-times me-1"></i>Non justifiée</span>
                                        <?php endif; ?>
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

<!-- Statistiques détaillées par matière -->
<div class="row mb-4">
    <div class="col-12">
        <div class="teacher-table-card shadow-sm">
            <div class="card-header py-3 px-4" style="background: linear-gradient(135deg, #f58024 0%, #d66a1a 100%);">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center text-white">
                        <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;" class="me-3">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Statistiques détaillées par matière</h6>
                            <small style="opacity: 0.8;">Analyse complète de vos enseignements</small>
                        </div>
                    </div>
                    <span class="badge" style="background: rgba(255,255,255,0.2);">
                        <i class="fas fa-table me-1"></i> <?php echo count($stats_matieres); ?> matière(s)
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($stats_matieres)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Aucune statistique disponible.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background: #f8f9fa;">
                                <tr>
                                    <th class="py-3 px-4" style="border: none; font-weight: 700; color: #333;">Matière</th>
                                    <th class="py-3" style="border: none; font-weight: 700; color: #333;">Code</th>
                                    <th class="py-3 text-center" style="border: none; font-weight: 700; color: #333;">Notes</th>
                                    <th class="py-3 text-center" style="border: none; font-weight: 700; color: #333;">Moyenne</th>
                                    <th class="py-3 text-center" style="border: none; font-weight: 700; color: #333;">Min</th>
                                    <th class="py-3 text-center" style="border: none; font-weight: 700; color: #333;">Max</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_matieres as $stat): ?>
                                <tr style="transition: all 0.3s ease;" onmouseover="this.style.background='#fff8f0'" onmouseout="this.style.background='transparent'">
                                    <td class="py-3 px-4" style="border-bottom: 1px solid #eee;">
                                        <div class="d-flex align-items-center">
                                            <div style="width: 35px; height: 35px; background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                                                <i class="fas fa-book text-white" style="font-size: 0.8rem;"></i>
                                            </div>
                                            <span class="fw-bold" style="color: #333;"><?php echo escape_html($stat['libelle']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3" style="border-bottom: 1px solid #eee;">
                                        <span class="badge" style="background: #f58024; padding: 5px 10px;"><?php echo escape_html($stat['code']); ?></span>
                                    </td>
                                    <td class="py-3 text-center" style="border-bottom: 1px solid #eee;">
                                        <span class="badge" style="background: #fcb628; color: #333; padding: 5px 12px;"><?php echo $stat['nb_notes']; ?></span>
                                    </td>
                                    <td class="py-3 text-center" style="border-bottom: 1px solid #eee;">
                                        <?php if ($stat['moyenne']): ?>
                                            <strong style="color: <?php echo $stat['moyenne'] >= 14 ? '#28a745' : ($stat['moyenne'] >= 10 ? '#f58024' : '#ef1c5d'); ?>;">
                                                <?php echo number_format($stat['moyenne'], 2); ?><small>/20</small>
                                            </strong>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-center" style="border-bottom: 1px solid #eee;">
                                        <?php if ($stat['note_min']): ?>
                                            <span style="color: #ef1c5d;"><?php echo number_format($stat['note_min'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-center" style="border-bottom: 1px solid #eee;">
                                        <?php if ($stat['note_max']): ?>
                                            <span style="color: #28a745;"><?php echo number_format($stat['note_max'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Couleurs IPP
const ippColors = {
    primary: '#ef1c5d',
    secondary: '#f58024',
    accent: '#fcb628'
};

// Graphique répartition par classe
const classeData = {
    labels: [<?php echo implode(',', array_map(function($c) { return "'" . addslashes($c['libelle']) . "'"; }, $repartition_classes)); ?>],
    datasets: [{
        label: 'Étudiants',
        data: [<?php echo implode(',', array_column($repartition_classes, 'nb_etudiants')); ?>],
        backgroundColor: function(context) {
            const chart = context.chart;
            const {ctx, chartArea} = chart;
            if (!chartArea) return ippColors.primary;
            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
            gradient.addColorStop(0, ippColors.primary);
            gradient.addColorStop(1, ippColors.secondary);
            return gradient;
        },
        borderRadius: 8,
        borderSkipped: false
    }]
};

new Chart(document.getElementById('classeChart'), {
    type: 'bar',
    data: classeData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + ' étudiant(s)';
                    }
                }
            }
        },
        scales: {
            y: { 
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                ticks: { font: { size: 12, weight: '500' }, color: '#666' }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 11, weight: '500' }, color: '#666' }
            }
        }
    }
});

// Graphique moyennes par matière
const matiereData = {
    labels: [<?php echo implode(',', array_map(function($m) { return "'" . addslashes($m['code']) . "'"; }, $stats_matieres)); ?>],
    datasets: [{
        label: 'Moyenne',
        data: [<?php echo implode(',', array_map(function($m) { return $m['moyenne'] ? number_format($m['moyenne'], 2, '.', '') : 0; }, $stats_matieres)); ?>],
        backgroundColor: 'rgba(245, 128, 36, 0.2)',
        borderColor: ippColors.secondary,
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: ippColors.primary,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 6,
        pointHoverRadius: 8
    }]
};

new Chart(document.getElementById('matiereChart'), {
    type: 'line',
    data: matiereData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        return 'Moyenne: ' + context.parsed.y + '/20';
                    }
                }
            }
        },
        scales: {
            y: { 
                beginAtZero: true,
                max: 20,
                grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                ticks: { font: { size: 12, weight: '500' }, color: '#666' }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 11, weight: '500' }, color: '#666' }
            }
        }
    }
});

// Horloge
function updateClock() {
    const now = new Date();
    const options = { hour: '2-digit', minute: '2-digit' };
    document.getElementById('currentTime').textContent = now.toLocaleTimeString('fr-FR', options);
}
updateClock();
setInterval(updateClock, 1000);
</script>

<?php include '../includes/footer.php'; ?>
