<?php
require_once '../config/config.php';
require_role(['admin', 'scolarite']);

$page_title = 'Gestion des absences';
$db = Database::getInstance()->getConnection();

// Filtres
$classe_filter = $_GET['classe'] ?? '';
$date_filter = $_GET['date'] ?? '';
$type_filter = $_GET['type'] ?? '';

$sql = "SELECT a.*, e.matricule, u.nom, u.prenom, c.libelle as classe, m.libelle as matiere
        FROM absences a
        JOIN etudiants e ON a.etudiant_id = e.id
        JOIN users u ON e.user_id = u.id
        JOIN classe_matieres cm ON a.classe_matiere_id = cm.id
        JOIN classes c ON cm.classe_id = c.id
        JOIN matieres m ON cm.matiere_id = m.id
        WHERE 1=1";
$params = [];

if (!empty($classe_filter)) {
    $sql .= " AND cm.classe_id = ?";
    $params[] = $classe_filter;
}

if (!empty($date_filter)) {
    $sql .= " AND a.date_absence = ?";
    $params[] = $date_filter;
}

if (!empty($type_filter)) {
    $sql .= " AND a.type = ?";
    $params[] = $type_filter;
}

$sql .= " ORDER BY a.date_absence DESC, u.nom, u.prenom";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$absences = $stmt->fetchAll();

// Classes pour le filtre
$classes = $db->query("SELECT * FROM classes WHERE statut = 'active' ORDER BY libelle")->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2><i class="fas fa-calendar-times"></i> Gestion des absences</h2>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4 mt-4">
    <div class="col-md-4">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h6>Total absences</h6>
                <h2><?php echo count($absences); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h6>Justifiées</h6>
                <h2><?php echo count(array_filter($absences, fn($a) => $a['type'] === 'justifiee')); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <h6>Non justifiées</h6>
                <h2><?php echo count(array_filter($absences, fn($a) => $a['type'] === 'non_justifiee')); ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <select class="form-select" id="classe_filter" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($classe['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
            </div>
            <div class="col-md-4">
                <select class="form-select" id="type_filter" name="type">
                    <option value="">Tous les types</option>
                    <option value="justifiee" <?php echo $type_filter === 'justifiee' ? 'selected' : ''; ?>>Justifiée</option>
                    <option value="non_justifiee" <?php echo $type_filter === 'non_justifiee' ? 'selected' : ''; ?>>Non justifiée</option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Liste des absences -->
<div class="card">
    <div class="card-body">
        <?php if (empty($absences)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Aucune absence enregistrée.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Matricule</th>
                            <th>Étudiant</th>
                            <th>Classe</th>
                            <th>Matière</th>
                            <th>Horaire</th>
                            <th>Type</th>
                            <th>Motif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $absence): ?>
                        <tr>
                            <td><?php echo format_date($absence['date_absence']); ?></td>
                            <td><strong><?php echo escape_html($absence['matricule']); ?></strong></td>
                            <td><?php echo escape_html($absence['prenom'] . ' ' . $absence['nom']); ?></td>
                            <td><?php echo escape_html($absence['classe']); ?></td>
                            <td><?php echo escape_html($absence['matiere']); ?></td>
                            <td>
                                <?php if ($absence['heure_debut']): ?>
                                    <?php echo substr($absence['heure_debut'], 0, 5); ?> - <?php echo substr($absence['heure_fin'], 0, 5); ?>
                                <?php else: ?>
                                    <span class="text-muted">Journée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $absence['type'] === 'justifiee' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($absence['type']); ?>
                                </span>
                            </td>
                            <td><?php echo escape_html($absence['motif']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
