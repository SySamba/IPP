<?php
require_once '../config/config.php';
require_role('enseignant');

$page_title = 'Mon emploi du temps';
$db = Database::getInstance()->getConnection();

// Récupérer l'enseignant
$enseignant_id = $_SESSION['user_id'];

// Filtres
$annee_filter = $_GET['annee_id'] ?? '';

// Année active par défaut
if (!$annee_filter) {
    $stmt = $db->query("SELECT id FROM annees_academiques WHERE statut = 'active' LIMIT 1");
    $annee_active = $stmt->fetch();
    if ($annee_active) {
        $annee_filter = $annee_active['id'];
    }
}

// Récupérer les années
$annees = $db->query("SELECT * FROM annees_academiques ORDER BY libelle DESC")->fetchAll();

// Récupérer l'emploi du temps de l'enseignant
$stmt = $db->prepare("SELECT edt.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau,
                      m.libelle as matiere, m.code as matiere_code, s.libelle as salle
                      FROM emplois_du_temps edt
                      JOIN classes c ON edt.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      JOIN matieres m ON edt.matiere_id = m.id
                      LEFT JOIN salles s ON edt.salle_id = s.id
                      WHERE edt.enseignant_id = ? AND edt.annee_academique_id = ?
                      ORDER BY FIELD(edt.jour, 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'), edt.heure_debut");
$stmt->execute([$enseignant_id, $annee_filter]);
$emplois = $stmt->fetchAll();

// Organiser par jour
$jours = ['lundi' => [], 'mardi' => [], 'mercredi' => [], 'jeudi' => [], 'vendredi' => [], 'samedi' => []];
foreach ($emplois as $edt) {
    $jours[$edt['jour']][] = $edt;
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-calendar-alt"></i> Mon emploi du temps</h2>
    </div>
</div>

<!-- Filtre année -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">Année académique</label>
                <select class="form-select" name="annee_id" onchange="this.form.submit()">
                    <?php foreach ($annees as $annee): ?>
                        <option value="<?php echo $annee['id']; ?>" <?php echo $annee_filter == $annee['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($annee['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if (empty($emplois)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Aucun cours dans votre emploi du temps.
    </div>
<?php else: ?>
    <!-- Emploi du temps en grille -->
    <div class="row">
        <?php foreach ($jours as $jour => $cours): ?>
            <?php if (!empty($cours)): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-day"></i> <?php echo ucfirst($jour); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($cours as $edt): ?>
                        <div class="border-start border-4 border-info ps-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0"><?php echo escape_html($edt['matiere']); ?></h6>
                                <span class="badge bg-secondary"><?php echo substr($edt['heure_debut'], 0, 5); ?> - <?php echo substr($edt['heure_fin'], 0, 5); ?></span>
                            </div>
                            <p class="mb-1 text-muted small">
                                <i class="fas fa-users"></i> <?php echo escape_html($edt['filiere'] . ' - ' . $edt['niveau'] . ' - ' . $edt['classe']); ?>
                            </p>
                            <?php if ($edt['salle']): ?>
                            <p class="mb-0 text-muted small">
                                <i class="fas fa-door-open"></i> <?php echo escape_html($edt['salle']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Vue tableau -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-table"></i> Vue détaillée</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Jour</th>
                            <th>Horaire</th>
                            <th>Matière</th>
                            <th>Classe</th>
                            <th>Salle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emplois as $edt): ?>
                        <tr>
                            <td><span class="badge bg-primary"><?php echo ucfirst($edt['jour']); ?></span></td>
                            <td><?php echo substr($edt['heure_debut'], 0, 5); ?> - <?php echo substr($edt['heure_fin'], 0, 5); ?></td>
                            <td><strong><?php echo escape_html($edt['matiere']); ?></strong></td>
                            <td><?php echo escape_html($edt['filiere'] . ' - ' . $edt['niveau'] . ' - ' . $edt['classe']); ?></td>
                            <td><?php echo escape_html($edt['salle'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bouton imprimer -->
    <div class="text-center mt-4">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Imprimer l'emploi du temps
        </button>
    </div>
<?php endif; ?>

<style>
@media print {
    .btn, .nav, .sidebar, .navbar, .card-body > .card:first-child {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
        page-break-inside: avoid;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
