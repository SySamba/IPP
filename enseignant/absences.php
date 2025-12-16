<?php
require_once '../config/config.php';
require_role('enseignant');

$page_title = 'Gestion des absences';
$db = Database::getInstance()->getConnection();

// Traitement des absences
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_absences') {
        $classe_matiere_id = intval($_POST['classe_matiere_id']);
        $date_absence = clean_input($_POST['date_absence']);
        $heure_debut = clean_input($_POST['heure_debut']);
        $heure_fin = clean_input($_POST['heure_fin']);
        $absents = $_POST['absents'] ?? [];
        
        try {
            $db->beginTransaction();
            
            // Supprimer les absences existantes pour cette date et ce cours
            $stmt = $db->prepare("DELETE FROM absences WHERE classe_matiere_id = ? AND date_absence = ?");
            $stmt->execute([$classe_matiere_id, $date_absence]);
            
            // Ajouter les nouvelles absences
            if (!empty($absents)) {
                $stmt = $db->prepare("INSERT INTO absences (etudiant_id, classe_matiere_id, date_absence, heure_debut, heure_fin, type) VALUES (?, ?, ?, ?, ?, 'non_justifiee')");
                
                foreach ($absents as $etudiant_id) {
                    $stmt->execute([$etudiant_id, $classe_matiere_id, $date_absence, $heure_debut, $heure_fin]);
                }
            }
            
            $db->commit();
            log_activity('Enregistrement absences', 'absences', $classe_matiere_id);
            set_flash_message('Absences enregistrées avec succès', 'success');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        // Rediriger avec les mêmes filtres pour voir les absences enregistrées
        redirect('enseignant/absences.php?classe_matiere_id=' . $classe_matiere_id . '&date=' . $date_absence);
    }
}

// Récupérer les classes de l'enseignant
$enseignant_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT cm.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau, m.libelle as matiere 
                      FROM classe_matieres cm 
                      JOIN classes c ON cm.classe_id = c.id 
                      JOIN filieres f ON c.filiere_id = f.id 
                      JOIN niveaux n ON c.niveau_id = n.id 
                      JOIN matieres m ON cm.matiere_id = m.id 
                      WHERE cm.enseignant_id = ?
                      ORDER BY f.libelle, n.ordre, c.libelle, m.libelle");
$stmt->execute([$enseignant_id]);
$classes_matieres = $stmt->fetchAll();

// Filtres
$classe_matiere_filter = $_GET['classe_matiere_id'] ?? '';
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Récupérer les étudiants de la classe sélectionnée
$etudiants = [];
$classe_matiere_info = null;
$absences_existantes = [];

if (!empty($classe_matiere_filter)) {
    // Informations de la classe-matière
    $stmt = $db->prepare("SELECT cm.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau, m.libelle as matiere 
                          FROM classe_matieres cm 
                          JOIN classes c ON cm.classe_id = c.id 
                          JOIN filieres f ON c.filiere_id = f.id 
                          JOIN niveaux n ON c.niveau_id = n.id 
                          JOIN matieres m ON cm.matiere_id = m.id 
                          WHERE cm.id = ?");
    $stmt->execute([$classe_matiere_filter]);
    $classe_matiere_info = $stmt->fetch();
    
    if ($classe_matiere_info) {
        // Récupérer les étudiants de cette classe
        $stmt = $db->prepare("SELECT e.*, u.nom, u.prenom, u.email 
                              FROM etudiants e 
                              JOIN users u ON e.user_id = u.id 
                              WHERE e.classe_id = ? AND e.statut = 'actif'
                              ORDER BY u.nom, u.prenom");
        $stmt->execute([$classe_matiere_info['classe_id']]);
        $etudiants = $stmt->fetchAll();
        
        // Récupérer les IDs des étudiants absents pour cette date (pour pré-cocher)
        $stmt = $db->prepare("SELECT etudiant_id FROM absences WHERE classe_matiere_id = ? AND date_absence = ?");
        $stmt->execute([$classe_matiere_filter, $date_filter]);
        $absences_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-calendar-times"></i> Gestion des absences</h2>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-8">
                <label for="classe_matiere_id" class="form-label">Classe - Matière *</label>
                <select class="form-select" name="classe_matiere_id" id="classe_matiere_id" required onchange="this.form.submit()">
                    <option value="">Sélectionner une classe...</option>
                    <?php foreach ($classes_matieres as $cm): ?>
                        <option value="<?php echo $cm['id']; ?>" <?php echo $classe_matiere_filter == $cm['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($cm['filiere'] . ' - ' . $cm['niveau'] . ' - ' . $cm['classe'] . ' | ' . $cm['matiere']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="date" class="form-label">Date *</label>
                <input type="date" class="form-control" name="date" id="date" value="<?php echo $date_filter; ?>" required onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<?php if (!empty($etudiants) && $classe_matiere_info): ?>

<!-- Historique des absences déjà enregistrées -->
<?php
$stmt = $db->prepare("SELECT a.*, e.matricule, u.nom, u.prenom 
                      FROM absences a
                      JOIN etudiants e ON a.etudiant_id = e.id
                      JOIN users u ON e.user_id = u.id
                      WHERE a.classe_matiere_id = ? AND a.date_absence = ?
                      ORDER BY u.nom, u.prenom");
$stmt->execute([$classe_matiere_filter, $date_filter]);
$absences_existantes = $stmt->fetchAll();

if (!empty($absences_existantes)):
?>
<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-history"></i> Absences déjà enregistrées pour cette date</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Étudiant</th>
                        <th>Heure début</th>
                        <th>Heure fin</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($absences_existantes as $absence): ?>
                    <tr>
                        <td><?php echo escape_html($absence['matricule']); ?></td>
                        <td><?php echo escape_html($absence['prenom'] . ' ' . $absence['nom']); ?></td>
                        <td><?php echo substr($absence['heure_debut'], 0, 5); ?></td>
                        <td><?php echo substr($absence['heure_fin'], 0, 5); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $absence['type'] === 'justifiee' ? 'success' : 'danger'; ?>">
                                <?php echo $absence['type'] === 'justifiee' ? 'Justifiée' : 'Non justifiée'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="alert alert-info mb-0 mt-3">
            <i class="fas fa-info-circle"></i> Si vous enregistrez de nouvelles absences, elles remplaceront celles ci-dessus.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Formulaire des absences -->
<form method="POST" class="no-confirm">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save_absences">
    <input type="hidden" name="classe_matiere_id" value="<?php echo $classe_matiere_filter; ?>">
    <input type="hidden" name="date_absence" value="<?php echo $date_filter; ?>">
    
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-chalkboard"></i> 
                <?php echo escape_html($classe_matiere_info['filiere'] . ' - ' . $classe_matiere_info['niveau'] . ' - ' . $classe_matiere_info['classe']); ?>
                | <?php echo escape_html($classe_matiere_info['matiere']); ?>
            </h5>
            <small>Date: <?php echo date('d/m/Y', strtotime($date_filter)); ?></small>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="heure_debut" class="form-label">Heure de début *</label>
                    <input type="time" class="form-control" name="heure_debut" id="heure_debut" value="08:00" required>
                </div>
                <div class="col-md-6">
                    <label for="heure_fin" class="form-label">Heure de fin *</label>
                    <input type="time" class="form-control" name="heure_fin" id="heure_fin" value="10:00" required>
                </div>
            </div>
            
            <hr>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Liste des étudiants (<?php echo count($etudiants); ?>)</h6>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="checkAll()">
                        <i class="fas fa-check-square"></i> Tout cocher
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="uncheckAll()">
                        <i class="fas fa-square"></i> Tout décocher
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">Absent</th>
                            <th>Matricule</th>
                            <th>Nom complet</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etudiants as $etudiant): ?>
                        <tr>
                            <td class="text-center">
                                <div class="form-check">
                                    <input class="form-check-input absence-checkbox" type="checkbox" 
                                           name="absents[]" 
                                           value="<?php echo $etudiant['id']; ?>"
                                           id="etudiant_<?php echo $etudiant['id']; ?>"
                                           <?php echo in_array($etudiant['id'], $absences_ids) ? 'checked' : ''; ?>>
                                </div>
                            </td>
                            <td><?php echo escape_html($etudiant['matricule']); ?></td>
                            <td><strong><?php echo escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></strong></td>
                            <td><?php echo escape_html($etudiant['email']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-save"></i> Enregistrer les absences
                </button>
            </div>
        </div>
    </div>
</form>
<?php else: ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Veuillez sélectionner une classe et une date pour gérer les absences.
</div>
<?php endif; ?>

<script>
function checkAll() {
    document.querySelectorAll('.absence-checkbox').forEach(cb => cb.checked = true);
}

function uncheckAll() {
    document.querySelectorAll('.absence-checkbox').forEach(cb => cb.checked = false);
}
</script>

<?php include '../includes/footer.php'; ?>
