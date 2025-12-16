<?php
require_once 'config/config.php';

// Rediriger les admins vers le dashboard BI
if (has_role(['admin', 'direction'])) {
    redirect('bi/dashboard.php');
}

$page_title = 'Tableau de bord';
include 'includes/header.php';

$db = Database::getInstance()->getConnection();
$user_role = $_SESSION['user_role'];

// Statistiques selon le rôle
$stats = [];

if (has_role(['scolarite'])) {
    // Nombre total d'étudiants
    $stmt = $db->query("SELECT COUNT(*) as total FROM etudiants WHERE statut = 'actif'");
    $stats['etudiants'] = $stmt->fetch()['total'];
    
    // Nombre d'enseignants
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'enseignant' AND statut = 'actif'");
    $stats['enseignants'] = $stmt->fetch()['total'];
    
    // Nombre de classes
    $stmt = $db->query("SELECT COUNT(*) as total FROM classes WHERE statut = 'active'");
    $stats['classes'] = $stmt->fetch()['total'];
    
    // Nombre de matières
    $stmt = $db->query("SELECT COUNT(*) as total FROM matieres WHERE statut = 'active'");
    $stats['matieres'] = $stmt->fetch()['total'];
    
    // Année académique active
    $stmt = $db->query("SELECT * FROM annees_academiques WHERE statut = 'active' LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $annee_active = $result ? $result : null;
    
    // Classes avec détails
    $classes_list = $db->query("SELECT c.libelle, f.libelle as filiere, n.libelle as niveau, COUNT(e.id) as nb_etudiants 
                                FROM classes c 
                                JOIN filieres f ON c.filiere_id = f.id 
                                JOIN niveaux n ON c.niveau_id = n.id 
                                LEFT JOIN etudiants e ON c.id = e.classe_id AND e.statut = 'actif'
                                WHERE c.statut = 'active' 
                                GROUP BY c.id 
                                ORDER BY f.libelle, n.ordre")->fetchAll();
    
    // Matières avec détails
    $matieres_list = $db->query("SELECT m.libelle, m.code, COUNT(DISTINCT cm.classe_id) as nb_classes 
                                 FROM matieres m 
                                 LEFT JOIN classe_matieres cm ON m.id = cm.matiere_id 
                                 WHERE m.statut = 'active' 
                                 GROUP BY m.id 
                                 ORDER BY m.libelle")->fetchAll();
}

if (has_role('enseignant')) {
    // Classes de l'enseignant
    $stmt = $db->prepare("SELECT COUNT(DISTINCT classe_id) as total FROM classe_matieres WHERE enseignant_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['mes_classes'] = $stmt->fetch()['total'];
    
    // Matières enseignées
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM classe_matieres WHERE enseignant_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['mes_matieres'] = $stmt->fetch()['total'];
    
    // Liste détaillée des classes
    $stmt = $db->prepare("SELECT DISTINCT c.id, c.libelle, f.libelle as filiere, n.libelle as niveau,
                          COUNT(DISTINCT e.id) as nb_etudiants
                          FROM classes c
                          JOIN filieres f ON c.filiere_id = f.id
                          JOIN niveaux n ON c.niveau_id = n.id
                          JOIN classe_matieres cm ON c.id = cm.classe_id
                          LEFT JOIN etudiants e ON c.id = e.classe_id AND e.statut = 'actif'
                          WHERE cm.enseignant_id = ? AND c.statut = 'active'
                          GROUP BY c.id
                          ORDER BY f.libelle, n.ordre");
    $stmt->execute([$_SESSION['user_id']]);
    $mes_classes_list = $stmt->fetchAll();
    
    // Liste détaillée des matières
    $stmt = $db->prepare("SELECT DISTINCT m.id, m.libelle, m.code,
                          COUNT(DISTINCT cm.classe_id) as nb_classes
                          FROM matieres m
                          JOIN classe_matieres cm ON m.id = cm.matiere_id
                          WHERE cm.enseignant_id = ?
                          GROUP BY m.id
                          ORDER BY m.libelle");
    $stmt->execute([$_SESSION['user_id']]);
    $mes_matieres_list = $stmt->fetchAll();
}

if (has_role('etudiant')) {
    // Récupérer les infos de l'étudiant
    $stmt = $db->prepare("SELECT e.*, u.nom, u.prenom, c.libelle as classe, c.id as classe_id, 
                          f.libelle as filiere, n.libelle as niveau, 
                          COALESCE(aa.libelle, aa2.libelle) as annee 
                          FROM etudiants e
                          JOIN users u ON e.user_id = u.id
                          JOIN classes c ON e.classe_id = c.id
                          JOIN filieres f ON c.filiere_id = f.id
                          JOIN niveaux n ON c.niveau_id = n.id
                          LEFT JOIN annees_academiques aa ON e.annee_academique_id = aa.id
                          LEFT JOIN annees_academiques aa2 ON aa2.statut = 'active'
                          WHERE e.user_id = ?
                          LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $etudiant = $stmt->fetch();
    
    // Debug: vérifier si l'année est récupérée
    if ($etudiant && empty($etudiant['annee'])) {
        // Si pas d'année, récupérer l'année active
        $stmt_annee = $db->query("SELECT libelle FROM annees_academiques WHERE statut = 'active' LIMIT 1");
        $annee_active = $stmt_annee->fetch();
        if ($annee_active) {
            $etudiant['annee'] = $annee_active['libelle'];
        }
    }
    
    // Récupérer les annonces d'absence pour les cours de l'étudiant
    if ($etudiant) {
        // TEMPORAIRE : Afficher toutes les annonces pour debug
        $stmt = $db->prepare("SELECT aa.*, m.libelle as matiere, u.nom, u.prenom, edt.jour, edt.heure_debut, edt.heure_fin,
                              c.libelle as classe_libelle,
                              CONCAT(aa.date_absence, ' ', edt.heure_fin) as datetime_fin,
                              NOW() as current_datetime
                              FROM annonces_absence aa
                              JOIN emplois_du_temps edt ON aa.cours_id = edt.id
                              JOIN classes c ON edt.classe_id = c.id
                              JOIN matieres m ON edt.matiere_id = m.id
                              JOIN users u ON aa.enseignant_id = u.id
                              WHERE edt.classe_id = ?
                              ORDER BY aa.date_absence DESC, edt.heure_debut ASC
                              LIMIT 10");
        $stmt->execute([$etudiant['classe_id']]);
        $annonces_absence = $stmt->fetchAll();
    }
    
    if ($etudiant) {
        // Nombre d'absences
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM absences WHERE etudiant_id = ?");
        $stmt->execute([$etudiant['id']]);
        $stats['absences'] = $stmt->fetch()['total'];
        
        // Liste des absences récentes
        $stmt = $db->prepare("SELECT a.*, m.libelle as matiere, m.code as matiere_code
                              FROM absences a
                              JOIN classe_matieres cm ON a.classe_matiere_id = cm.id
                              JOIN matieres m ON cm.matiere_id = m.id
                              WHERE a.etudiant_id = ?
                              ORDER BY a.date_absence DESC
                              LIMIT 5");
        $stmt->execute([$etudiant['id']]);
        $absences_recentes = $stmt->fetchAll();
        
        // Nombre de mois payés (compter les paiements de scolarité uniquement)
        $stmt = $db->prepare("SELECT COUNT(*) as mois_payes 
                              FROM paiements 
                              WHERE etudiant_id = ? AND statut = 'paye' AND type_frais LIKE 'Scolarité -%'");
        $stmt->execute([$etudiant['id']]);
        $stats['mois_payes'] = $stmt->fetch()['mois_payes'] ?? 0;
        
        // Liste détaillée des paiements
        $stmt = $db->prepare("SELECT p.id, p.type_frais, p.montant_paye, p.montant_total, p.date_paiement, p.statut
                              FROM paiements p
                              WHERE p.etudiant_id = ? AND p.type_frais LIKE 'Scolarité -%'
                              ORDER BY p.date_paiement DESC
                              LIMIT 12");
        $stmt->execute([$etudiant['id']]);
        $paiements_details = $stmt->fetchAll();
        
        // Moyenne générale (dernière période)
        $stmt = $db->prepare("
            SELECT AVG(moyenne_matiere * coef_matiere) / AVG(coef_matiere) as moyenne_gen
            FROM (
                SELECT cm.coefficient as coef_matiere,
                       AVG((n.note / n.note_sur) * 20 * n.coefficient) / AVG(n.coefficient) as moyenne_matiere
                FROM notes n
                JOIN classe_matieres cm ON n.classe_matiere_id = cm.id
                WHERE n.etudiant_id = ?
                GROUP BY cm.id
            ) as moyennes_matieres
        ");
        $stmt->execute([$etudiant['id']]);
        $result = $stmt->fetch();
        $stats['moyenne'] = $result['moyenne_gen'] ? number_format($result['moyenne_gen'], 2) : 'N/A';
    }
}
?>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4">
            <i class="fas fa-home"></i> Tableau de bord
        </h2>
    </div>
</div>

<?php if (has_role(['admin', 'scolarite', 'direction'])): ?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card bg-gradient-pink position-relative">
            <i class="fas fa-user-graduate"></i>
            <h3 style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo $stats['etudiants']; ?></h3>
            <p style="color: white; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Étudiants actifs</p>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card bg-gradient-green position-relative">
            <i class="fas fa-chalkboard-teacher"></i>
            <h3 style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo $stats['enseignants']; ?></h3>
            <p style="color: white; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Enseignants</p>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card bg-gradient-blue position-relative">
            <i class="fas fa-school"></i>
            <h3 style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo $stats['classes']; ?></h3>
            <p style="color: white; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Classes actives</p>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card bg-gradient-yellow position-relative">
            <i class="fas fa-book"></i>
            <h3 style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo $stats['matieres']; ?></h3>
            <p style="color: white; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Matières actives</p>
        </div>
    </div>
</div>

<!-- Classes et Matières -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-school"></i> Classes actives</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Filière</th>
                                <th>Niveau</th>
                                <th class="text-center">Étudiants</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes_list as $classe): ?>
                            <tr>
                                <td><strong><?php echo escape_html($classe['libelle']); ?></strong></td>
                                <td><?php echo escape_html($classe['filiere']); ?></td>
                                <td><?php echo escape_html($classe['niveau']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo $classe['nb_etudiants']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-book"></i> Matières actives</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Matière</th>
                                <th class="text-center">Classes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matieres_list as $matiere): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?php echo escape_html($matiere['code']); ?></span></td>
                                <td><strong><?php echo escape_html($matiere['libelle']); ?></strong></td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo $matiere['nb_classes']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php if (has_role('enseignant')): ?>
<!-- En-tête Enseignant -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-2 text-center">
                <div class="avatar-circle" style="width: 80px; height: 80px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    <i class="fas fa-chalkboard-teacher fa-3x text-primary"></i>
                </div>
            </div>
            <div class="col-md-10">
                <h3 class="mb-3 text-dark">Bienvenue, <?php echo escape_html($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?></h3>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1 text-muted"><i class="fas fa-school text-primary"></i> <strong>Mes Classes:</strong></p>
                        <h5 class="text-dark"><?php echo $stats['mes_classes']; ?> classe(s)</h5>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1 text-muted"><i class="fas fa-book text-success"></i> <strong>Mes Matières:</strong></p>
                        <h5 class="text-dark"><?php echo $stats['mes_matieres']; ?> matière(s)</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mes Classes et Matières -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-school"></i> Mes Classes</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($mes_classes_list)): ?>
                    <p class="text-muted">Aucune classe assignée.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Filière</th>
                                    <th>Niveau</th>
                                    <th class="text-center">Étudiants</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mes_classes_list as $classe): ?>
                                <tr>
                                    <td><strong><?php echo escape_html($classe['libelle']); ?></strong></td>
                                    <td><?php echo escape_html($classe['filiere']); ?></td>
                                    <td><?php echo escape_html($classe['niveau']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $classe['nb_etudiants']; ?></span>
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
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-book"></i> Mes Matières</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($mes_matieres_list)): ?>
                    <p class="text-muted">Aucune matière assignée.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Matière</th>
                                    <th class="text-center">Classes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mes_matieres_list as $matiere): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?php echo escape_html($matiere['code']); ?></span></td>
                                    <td><strong><?php echo escape_html($matiere['libelle']); ?></strong></td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $matiere['nb_classes']; ?></span>
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


</div>
<?php endif; ?>

<?php if (has_role('etudiant')): ?>
<!-- Bandeau défilant pour les annonces d'absence -->
<?php if (!empty($annonces_absence)): ?>
<div class="alert-ticker mb-4" style="background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%); border: none; box-shadow: 0 4px 15px rgba(255,107,107,0.3); overflow: hidden; position: relative; padding: 15px 0; border-radius: 10px;">
    <div class="d-flex align-items-center" style="height: 60px;">
        <div class="ticker-icon" style="min-width: 80px; text-align: center; font-size: 2rem; color: white;">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="ticker-content" style="flex: 1; overflow: hidden; position: relative; max-width: calc(100% - 80px);">
            <div class="ticker-wrapper" style="overflow: hidden; width: 100%;">
                <div class="ticker-text" style="display: inline-block; white-space: nowrap; padding-left: 100%; animation: ticker 30s linear infinite; will-change: transform;">
                    <?php foreach ($annonces_absence as $index => $annonce): ?>
                        <span style="display: inline-block; margin-right: 50px; color: white; font-size: 1.2rem; font-weight: 700;">
                            <i class="fas fa-circle" style="font-size: 0.5rem; vertical-align: middle;"></i>
                            <strong style="font-weight: 800;">⚠️ COURS ANNULÉ</strong> - 
                            <span style="background: rgba(255,255,255,0.3); padding: 3px 10px; border-radius: 15px; margin: 0 5px;">
                                <?php echo escape_html($annonce['matiere']); ?>
                            </span>
                            <?php echo escape_html($annonce['classe_libelle']); ?> - 
                            Prof. <?php echo escape_html($annonce['prenom'] . ' ' . $annonce['nom']); ?> - 
                            <?php echo date('d/m/Y', strtotime($annonce['date_absence'])); ?> 
                            (<?php echo ucfirst($annonce['jour']); ?> <?php echo substr($annonce['heure_debut'], 0, 5); ?>)
                            <?php if (!empty($annonce['message'])): ?>
                                - <em><?php echo escape_html($annonce['message']); ?></em>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
body {
    overflow-x: hidden;
}

@keyframes ticker {
    0% {
        transform: translate3d(0, 0, 0);
    }
    100% {
        transform: translate3d(-100%, 0, 0);
    }
}

.ticker-text {
    animation-timing-function: linear;
    animation-iteration-count: infinite;
}

.alert-ticker:hover .ticker-text {
    animation-play-state: paused;
}

.ticker-wrapper {
    overflow: hidden !important;
}

.ticker-content {
    overflow: hidden !important;
}
</style>
<?php endif; ?>

<!-- En-tête avec informations étudiant -->
<div class="card mb-3 border-0 shadow-sm">
    <div class="card-body p-3">
        <div class="row align-items-center">
            <div class="col-auto">
                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user-graduate fa-2x text-white"></i>
                </div>
            </div>
            <div class="col">
                <h4 class="mb-2 text-dark" style="font-weight: 700;">
                    <?php echo isset($etudiant) ? escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']) : 'Étudiant'; ?>
                </h4>
                <div class="row g-3">
                    <div class="col-md-3 col-6">
                        <div style="background: #e3f2fd; padding: 12px 15px; border-radius: 8px; border-left: 4px solid #2196F3;">
                            <div style="font-size: 0.75rem; color: #666; margin-bottom: 4px; font-weight: 600;">Matricule</div>
                            <div style="font-weight: 700; font-size: 1.1rem; color: #2196F3;"><?php echo isset($etudiant) ? escape_html($etudiant['matricule']) : 'N/A'; ?></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div style="background: #f3e5f5; padding: 12px 15px; border-radius: 8px; border-left: 4px solid #9c27b0;">
                            <div style="font-size: 0.75rem; color: #666; margin-bottom: 4px; font-weight: 600;">Classe</div>
                            <div style="font-weight: 700; font-size: 1.1rem; color: #9c27b0;"><?php echo isset($etudiant) ? escape_html($etudiant['classe']) : 'N/A'; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div style="background: #e8f5e9; padding: 12px 15px; border-radius: 8px; border-left: 4px solid #4caf50;">
                            <div style="font-size: 0.75rem; color: #666; margin-bottom: 4px; font-weight: 600;">Filière</div>
                            <div style="font-weight: 700; font-size: 1.1rem; color: #4caf50;"><?php echo isset($etudiant) ? escape_html($etudiant['filiere']) : 'N/A'; ?></div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques - Version compacte -->
<div class="row mb-3">
    <div class="col-md-3 col-6 mb-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <i class="fas fa-chart-line fa-2x text-primary mb-2"></i>
                <h3 class="mb-1"><?php echo $stats['moyenne'] ?? 'N/A'; ?><small class="text-muted">/20</small></h3>
                <small class="text-muted">Moyenne Générale</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <i class="fas fa-calendar-times fa-2x text-warning mb-2"></i>
                <h3 class="mb-1"><?php echo $stats['absences'] ?? 0; ?></h3>
                <small class="text-muted">Absences</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <i class="fas fa-money-check-alt fa-2x text-success mb-2"></i>
                <h3 class="mb-1"><?php echo $stats['mois_payes'] ?? 0; ?><small class="text-muted">/12</small></h3>
                <small class="text-muted">Mois Payés</small>
            </div>
        </div>
    </div>
</div>

<!-- Détails des absences et paiements -->
<div class="row mb-3">
    <!-- Absences récentes -->
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-warning text-dark py-2">
                <h6 class="mb-0" style="font-size: 0.9rem;"><i class="fas fa-calendar-times"></i> Absences</h6>
            </div>
            <div class="card-body p-2">
                <?php if (empty($absences_recentes)): ?>
                    <p class="text-center text-muted my-2" style="font-size: 0.85rem;"><i class="fas fa-check-circle text-success"></i> Aucune</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="font-size: 0.8rem;">
                            <thead>
                                <tr>
                                    <th style="padding: 4px;">Date</th>
                                    <th style="padding: 4px; text-align: center;">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $count = 0;
                                foreach ($absences_recentes as $abs): 
                                    if ($count >= 5) break;
                                    $count++;
                                ?>
                                <tr>
                                    <td style="padding: 4px;"><?php echo date('d/m', strtotime($abs['date_absence'])); ?></td>
                                    <td style="padding: 4px; text-align: center;">
                                        <?php if ($abs['type'] === 'justifiee'): ?>
                                            <span class="badge bg-success" style="font-size: 0.65rem; padding: 2px 5px;">✓</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger" style="font-size: 0.65rem; padding: 2px 5px;">✗</span>
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
    
    <!-- Paiements - Tableau unique -->
    <div class="col-md-9 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-success text-white py-2">
                <h6 class="mb-0" style="font-size: 0.95rem;"><i class="fas fa-money-bill-wave"></i> Mes paiements de scolarité (12 mois)</h6>
            </div>
            <div class="card-body p-2">
                <?php if (empty($paiements_details)): ?>
                    <p class="text-center text-muted my-2" style="font-size: 0.9rem;">Aucun paiement enregistré</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" style="font-size: 0.85rem;">
                            <thead style="background: #f8f9fa;">
                                <tr>
                                    <th style="padding: 6px;">Mois</th>
                                    <th style="padding: 6px; text-align: center;">Statut</th>
                                    <th style="padding: 6px;">Mois</th>
                                    <th style="padding: 6px; text-align: center;">Statut</th>
                                    <th style="padding: 6px;">Mois</th>
                                    <th style="padding: 6px; text-align: center;">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rows = ceil(count($paiements_details) / 3);
                                for ($i = 0; $i < $rows; $i++): 
                                ?>
                                <tr>
                                    <?php for ($col = 0; $col < 3; $col++): 
                                        $index = $i + ($col * $rows);
                                        if (isset($paiements_details[$index])):
                                            $pmt = $paiements_details[$index];
                                    ?>
                                        <td style="padding: 6px;"><strong><?php echo str_replace('Scolarité - ', '', escape_html($pmt['type_frais'])); ?></strong></td>
                                        <td style="padding: 6px; text-align: center;">
                                            <?php if ($pmt['statut'] === 'paye'): ?>
                                                <span class="badge bg-success" style="font-size: 0.75rem; padding: 3px 8px;">✓ Payé</span>
                                            <?php elseif ($pmt['statut'] === 'partiel'): ?>
                                                <span class="badge bg-warning text-dark" style="font-size: 0.75rem; padding: 3px 8px;">⚠ Partiel</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger" style="font-size: 0.75rem; padding: 3px 8px;">✗ Impayé</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php else: ?>
                                        <td style="padding: 6px;"></td>
                                        <td style="padding: 6px;"></td>
                                    <?php endif; endfor; ?>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>




<style>
.icon-circle-sm {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.hover-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
</style>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
