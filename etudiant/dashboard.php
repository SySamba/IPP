<?php
require_once '../config/config.php';
require_role(['etudiant']);

$page_title = 'Tableau de bord Étudiant';
$db = Database::getInstance()->getConnection();

// Récupérer l'étudiant
$stmt = $db->prepare("SELECT e.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau
                      FROM etudiants e
                      JOIN classes c ON e.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      WHERE e.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$etudiant = $stmt->fetch();

if (!$etudiant) {
    set_flash_message('Profil étudiant non trouvé', 'error');
    redirect('index.php');
}

// Filtres
$annee_id = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : null;
$semestre = isset($_GET['semestre']) ? $_GET['semestre'] : null;

$annees = $db->query("SELECT * FROM annees_academiques ORDER BY libelle DESC")->fetchAll();

if (!$annee_id) {
    $stmt = $db->query("SELECT * FROM annees_academiques WHERE statut = 'active' LIMIT 1");
    $annee_active = $stmt->fetch();
    if ($annee_active) {
        $annee_id = $annee_active['id'];
    }
}

// KPIs
$stats = [];

// Moyenne générale
$sql = "SELECT AVG((n.note / n.note_sur) * 20) as moyenne
        FROM notes n
        JOIN inscriptions i ON n.etudiant_id = i.etudiant_id
        WHERE n.etudiant_id = ? AND i.annee_academique_id = ?";
$params = [$etudiant['id'], $annee_id];

if ($semestre) {
    $sql .= " AND n.semestre = ?";
    $params[] = $semestre;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$stats['moyenne_generale'] = $stmt->fetch()['moyenne'] ?? 0;

// Nombre d'absences
$sql = "SELECT COUNT(*) as total,
        SUM(CASE WHEN type = 'justifiee' THEN 1 ELSE 0 END) as justifiees,
        SUM(CASE WHEN type = 'non_justifiee' THEN 1 ELSE 0 END) as non_justifiees
        FROM absences a
        JOIN inscriptions i ON a.etudiant_id = i.etudiant_id
        WHERE a.etudiant_id = ? AND i.annee_academique_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$etudiant['id'], $annee_id]);
$stats_absences = $stmt->fetch();

// Paiements
$sql = "SELECT montant_total, montant_paye, statut
        FROM paiements
        WHERE etudiant_id = ? AND annee_academique_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$etudiant['id'], $annee_id]);
$paiement = $stmt->fetch();

// Notes par matière
$sql = "SELECT m.libelle, m.code,
        AVG((n.note / n.note_sur) * 20) as moyenne,
        COUNT(n.id) as nb_notes
        FROM notes n
        JOIN classe_matieres cm ON n.classe_matiere_id = cm.id
        JOIN matieres m ON cm.matiere_id = m.id
        JOIN inscriptions i ON n.etudiant_id = i.etudiant_id
        WHERE n.etudiant_id = ? AND i.annee_academique_id = ?";
$params_notes = [$etudiant['id'], $annee_id];

if ($semestre) {
    $sql .= " AND n.semestre = ?";
    $params_notes[] = $semestre;
}

$sql .= " GROUP BY m.id ORDER BY m.libelle";
$stmt = $db->prepare($sql);
$stmt->execute($params_notes);
$notes_matieres = $stmt->fetchAll();

include '../includes/header.php';
?>

<!-- Annonces d'absence défilantes -->
<?php
// Récupérer les annonces d'absence pour les cours de l'étudiant
$stmt = $db->prepare("SELECT aa.*, m.libelle as matiere, u.nom, u.prenom, edt.jour, edt.heure_debut
                      FROM annonces_absence aa
                      JOIN emplois_du_temps edt ON aa.cours_id = edt.id
                      JOIN matieres m ON edt.matiere_id = m.id
                      JOIN users u ON aa.enseignant_id = u.id
                      WHERE edt.classe_id = ? AND aa.date_absence >= CURDATE()
                      ORDER BY aa.date_absence ASC
                      LIMIT 5");
$stmt->execute([$etudiant['classe_id']]);
$annonces_absence = $stmt->fetchAll();
?>

<style>
/* Styles Espace Étudiant */
.student-header {
    background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}
.student-header::before {
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
.student-stat-card {
    border-radius: 16px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
}
.student-stat-card::before {
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
.student-stat-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15) !important;
}
.student-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.2);
}
.student-filter-card {
    border-radius: 16px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: 1px solid rgba(239, 28, 93, 0.1);
}
.student-chart-card {
    border-radius: 16px;
    border: none;
}
.pulse-alert {
    animation: pulseAlert 2s infinite;
}
@keyframes pulseAlert {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 28, 93, 0.4); }
    50% { box-shadow: 0 0 0 10px rgba(239, 28, 93, 0); }
}
</style>

<?php if (!empty($annonces_absence)): ?>
<div class="alert mb-4 pulse-alert" style="background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border: none; border-radius: 16px; color: white;">
    <div class="d-flex align-items-center">
        <div class="me-3">
            <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-exclamation-triangle fa-lg"></i>
            </div>
        </div>
        <div class="flex-grow-1">
            <h6 class="mb-1 fw-bold"><i class="fas fa-bell me-1"></i> Annonces d'absence</h6>
            <div id="annonceCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
                <div class="carousel-inner">
                    <?php foreach ($annonces_absence as $index => $annonce): ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <p class="mb-0" style="font-size: 0.95rem;">
                            <strong><?php echo escape_html($annonce['matiere']); ?></strong> - 
                            Prof. <?php echo escape_html($annonce['prenom'] . ' ' . $annonce['nom']); ?> |
                            <i class="fas fa-calendar-alt ms-1"></i> <?php echo date('d/m/Y', strtotime($annonce['date_absence'])); ?> 
                            (<?php echo ucfirst($annonce['jour']); ?> <?php echo substr($annonce['heure_debut'], 0, 5); ?>)
                            <?php if (!empty($annonce['message'])): ?> - <em><?php echo escape_html($annonce['message']); ?></em><?php endif; ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>

<!-- En-tête Étudiant -->
<div class="student-header mb-4 shadow-lg">
    <div class="card-body text-white p-4 position-relative" style="z-index: 1;">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center mb-3">
                    <div style="width: 70px; height: 70px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                    <div>
                        <h2 class="mb-0 fw-bold" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">Mon Espace Étudiant</h2>
                        <p class="mb-0 mt-1" style="opacity: 0.9;">Bienvenue dans votre tableau de bord personnel</p>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 8px 16px;">
                        <i class="fas fa-id-card me-1"></i> <?php echo escape_html($etudiant['matricule']); ?>
                    </span>
                    <span class="badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 8px 16px;">
                        <i class="fas fa-school me-1"></i> <?php echo escape_html($etudiant['classe']); ?>
                    </span>
                    <span class="badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 8px 16px;">
                        <i class="fas fa-graduation-cap me-1"></i> <?php echo escape_html($etudiant['filiere']); ?>
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

<!-- Filtres -->
<div class="student-filter-card mb-4 shadow-sm">
    <div class="card-header bg-transparent border-0 py-3 px-4">
        <div class="d-flex align-items-center">
            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;" class="me-3">
                <i class="fas fa-filter text-white"></i>
            </div>
            <h6 class="mb-0 fw-bold" style="color: #333;">Filtres</h6>
        </div>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold text-dark"><i class="fas fa-calendar me-1" style="color: #ef1c5d;"></i> Année académique</label>
                <select class="form-select" id="annee_filter" style="border-radius: 10px; border: 2px solid #e9ecef;">
                    <?php foreach ($annees as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $a['id'] == $annee_id ? 'selected' : ''; ?>>
                            <?php echo escape_html($a['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold text-dark"><i class="fas fa-calendar-week me-1" style="color: #f58024;"></i> Semestre</label>
                <select class="form-select" id="semestre_filter" style="border-radius: 10px; border: 2px solid #e9ecef;">
                    <option value="">Tous les semestres</option>
                    <option value="1" <?php echo $semestre == '1' ? 'selected' : ''; ?>>Semestre 1</option>
                    <option value="2" <?php echo $semestre == '2' ? 'selected' : ''; ?>>Semestre 2</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Liens Rapides -->
<div class="row mb-4 g-3">
    <div class="col-12">
        <div class="d-flex flex-wrap gap-2">
            <a href="notes.php" class="btn btn-sm" style="background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); color: white; border-radius: 20px; padding: 10px 20px;">
                <i class="fas fa-clipboard-list me-2"></i>Mes Notes
            </a>
            <a href="paiements.php" class="btn btn-sm" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 20px; padding: 10px 20px;">
                <i class="fas fa-money-bill-wave me-2"></i>Mes Paiements
            </a>
            <a href="absences.php" class="btn btn-sm" style="background: linear-gradient(135deg, #f58024 0%, #fcb628 100%); color: white; border-radius: 20px; padding: 10px 20px;">
                <i class="fas fa-calendar-times me-2"></i>Mes Absences
            </a>
            <a href="emploi-du-temps.php" class="btn btn-sm" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%); color: white; border-radius: 20px; padding: 10px 20px;">
                <i class="fas fa-calendar-alt me-2"></i>Emploi du temps
            </a>
        </div>
    </div>
</div>

<!-- KPIs Étudiants -->
<div class="row mb-4 g-4">
    <div class="col-xl-3 col-md-6">
        <div class="student-stat-card shadow" style="background: linear-gradient(135deg, #ef1c5d 0%, #c71450 100%);">
            <div class="card-body text-white p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px; opacity: 0.9;">
                            <i class="fas fa-chart-line me-1"></i> Moyenne Générale
                        </p>
                        <h2 class="mb-1 fw-bold" style="font-size: 2rem;"><?php echo number_format($stats['moyenne_generale'], 2); ?><small style="font-size: 1rem;">/20</small></h2>
                        <small style="opacity: 0.8;">
                            <?php if ($stats['moyenne_generale'] >= 14): ?>
                                <i class="fas fa-star me-1"></i> Excellent
                            <?php elseif ($stats['moyenne_generale'] >= 12): ?>
                                <i class="fas fa-thumbs-up me-1"></i> Bien
                            <?php elseif ($stats['moyenne_generale'] >= 10): ?>
                                <i class="fas fa-check me-1"></i> Passable
                            <?php else: ?>
                                <i class="fas fa-exclamation me-1"></i> À améliorer
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="student-stat-icon">
                        <i class="fas fa-chart-line fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="student-stat-card shadow" style="background: linear-gradient(135deg, #f58024 0%, #d66a1a 100%);">
            <div class="card-body text-white p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px; opacity: 0.9;">
                            <i class="fas fa-calendar-times me-1"></i> Absences
                        </p>
                        <h2 class="mb-1 fw-bold" style="font-size: 2rem;"><?php echo $stats_absences['total']; ?></h2>
                        <small style="opacity: 0.8;"><i class="fas fa-info-circle me-1"></i> Total enregistré</small>
                    </div>
                    <div class="student-stat-icon">
                        <i class="fas fa-calendar-times fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="student-stat-card shadow" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
            <div class="card-body text-white p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px; opacity: 0.9;">
                            <i class="fas fa-check-circle me-1"></i> Justifiées
                        </p>
                        <h2 class="mb-1 fw-bold" style="font-size: 2rem;"><?php echo $stats_absences['justifiees']; ?></h2>
                        <small style="opacity: 0.8;"><i class="fas fa-file-alt me-1"></i> Avec justificatif</small>
                    </div>
                    <div class="student-stat-icon">
                        <i class="fas fa-check-circle fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="student-stat-card shadow" style="background: linear-gradient(135deg, #fcb628 0%, #e09e1a 100%);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-2 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px; opacity: 0.9; color: #333;">
                            <i class="fas fa-money-bill-wave me-1"></i> Paiement
                        </p>
                        <h2 class="mb-1 fw-bold" style="font-size: 2rem; color: #333;">
                            <?php 
                            if ($paiement) {
                                $taux = ($paiement['montant_paye'] / $paiement['montant_total']) * 100;
                                echo number_format($taux, 0) . '%';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </h2>
                        <small style="opacity: 0.8; color: #333;"><i class="fas fa-wallet me-1"></i> Scolarité payée</small>
                    </div>
                    <div class="student-stat-icon" style="background: rgba(0,0,0,0.1);">
                        <i class="fas fa-money-bill-wave fa-lg" style="color: #333;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphique des moyennes -->
<div class="row mb-4">
    <div class="col-12">
        <div class="student-chart-card shadow-sm">
            <div class="card-header py-3 px-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border-bottom: 3px solid #ef1c5d; border-radius: 16px 16px 0 0;">
                <div class="d-flex align-items-center">
                    <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;" class="me-3">
                        <i class="fas fa-chart-bar text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="color: #333;">Moyennes par matière</h6>
                        <small class="text-muted">Visualisation de vos performances</small>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <canvas id="matiereChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tableau détaillé des notes -->
<div class="row mb-4">
    <div class="col-12">
        <div class="student-chart-card shadow-sm" style="border-radius: 16px; overflow: hidden;">
            <div class="card-header py-3 px-4" style="background: linear-gradient(135deg, #ef1c5d 0%, #c71450 100%);">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center text-white">
                        <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;" class="me-3">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Détail des notes</h6>
                            <small style="opacity: 0.8;">Récapitulatif par matière</small>
                        </div>
                    </div>
                    <span class="badge" style="background: rgba(255,255,255,0.2);">
                        <i class="fas fa-list me-1"></i> <?php echo count($notes_matieres); ?> matière(s)
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($notes_matieres)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Aucune note disponible pour le moment.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background: #f8f9fa;">
                                <tr>
                                    <th class="py-3 px-4" style="border: none; font-weight: 700; color: #333;">Matière</th>
                                    <th class="py-3" style="border: none; font-weight: 700; color: #333;">Code</th>
                                    <th class="py-3 text-center" style="border: none; font-weight: 700; color: #333;">Nb Notes</th>
                                    <th class="py-3 px-4" style="border: none; font-weight: 700; color: #333;">Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notes_matieres as $note): ?>
                                <tr style="transition: all 0.3s ease;" onmouseover="this.style.background='#fef5f8'" onmouseout="this.style.background='transparent'">
                                    <td class="py-3 px-4" style="border-bottom: 1px solid #eee;">
                                        <div class="d-flex align-items-center">
                                            <div style="width: 35px; height: 35px; background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                                                <i class="fas fa-book-open text-white" style="font-size: 0.8rem;"></i>
                                            </div>
                                            <span class="fw-bold" style="color: #333;"><?php echo escape_html($note['libelle']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3" style="border-bottom: 1px solid #eee;">
                                        <span class="badge" style="background: #f58024; padding: 6px 12px;"><?php echo escape_html($note['code']); ?></span>
                                    </td>
                                    <td class="py-3 text-center" style="border-bottom: 1px solid #eee;">
                                        <span class="badge" style="background: #fcb628; color: #333; padding: 6px 12px;"><?php echo $note['nb_notes']; ?></span>
                                    </td>
                                    <td class="py-3 px-4" style="border-bottom: 1px solid #eee;">
                                        <div class="d-flex align-items-center">
                                            <div style="width: 50px; height: 6px; background: #e9ecef; border-radius: 3px; margin-right: 10px; overflow: hidden;">
                                                <div style="width: <?php echo ($note['moyenne'] / 20) * 100; ?>%; height: 100%; background: linear-gradient(90deg, <?php echo $note['moyenne'] >= 10 ? '#28a745' : '#ef1c5d'; ?> 0%, <?php echo $note['moyenne'] >= 10 ? '#20c997' : '#f58024'; ?> 100%); border-radius: 3px;"></div>
                                            </div>
                                            <strong style="color: <?php echo $note['moyenne'] >= 14 ? '#28a745' : ($note['moyenne'] >= 10 ? '#f58024' : '#ef1c5d'); ?>; font-size: 1rem;">
                                                <?php echo number_format($note['moyenne'], 2); ?><small>/20</small>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Couleurs IPP
const ippColors = {
    primary: '#ef1c5d',
    secondary: '#f58024',
    accent: '#fcb628'
};

const matiereData = {
    labels: [<?php echo implode(',', array_map(function($m) { return "'" . addslashes($m['code']) . "'"; }, $notes_matieres)); ?>],
    datasets: [{
        label: 'Moyenne',
        data: [<?php echo implode(',', array_map(function($m) { return number_format($m['moyenne'], 2, '.', ''); }, $notes_matieres)); ?>],
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

new Chart(document.getElementById('matiereChart'), {
    type: 'bar',
    data: matiereData,
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
                ticks: { font: { size: 12, weight: '500' }, color: '#666' }
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
