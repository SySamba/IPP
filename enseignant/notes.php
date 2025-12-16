<?php
require_once '../config/config.php';
require_role('enseignant');

$page_title = 'Saisie des notes';
$db = Database::getInstance()->getConnection();

// Traitement des notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_notes') {
        $classe_matiere_id = intval($_POST['classe_matiere_id']);
        $type_evaluation_id = intval($_POST['type_evaluation_id']);
        $date_evaluation = clean_input($_POST['date_evaluation']);
        $semestre = intval($_POST['semestre']);
        $notes = $_POST['notes'] ?? [];
        
        try {
            $db->beginTransaction();
            
            // Récupérer le type d'évaluation
            $stmt = $db->prepare("SELECT * FROM types_evaluation WHERE id = ?");
            $stmt->execute([$type_evaluation_id]);
            $type_eval = $stmt->fetch();
            
            if (!$type_eval) {
                throw new Exception('Type d\'évaluation introuvable');
            }
            
            // Vérifier si la colonne semestre existe dans notes
            try {
                $db->query("SELECT semestre FROM notes LIMIT 1");
            } catch (Exception $e) {
                // Ajouter la colonne semestre si elle n'existe pas
                $db->exec("ALTER TABLE notes ADD COLUMN semestre INT DEFAULT 1 AFTER coefficient");
            }
            
            // Enregistrer les notes avec semestre - vérifier si une note existe déjà
            $stmt_check = $db->prepare("SELECT id FROM notes WHERE etudiant_id = ? AND classe_matiere_id = ? AND type_evaluation_id = ? AND semestre = ?");
            $stmt_insert = $db->prepare("INSERT INTO notes (etudiant_id, classe_matiere_id, type_evaluation_id, note, note_sur, date_evaluation, coefficient, semestre) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_update = $db->prepare("UPDATE notes SET note = ?, note_sur = ?, date_evaluation = ? WHERE id = ?");
            
            foreach ($notes as $etudiant_id => $note) {
                if ($note !== '' && $note !== null) {
                    // Vérifier si une note existe déjà pour cet étudiant/matière/type/semestre
                    $stmt_check->execute([$etudiant_id, $classe_matiere_id, $type_evaluation_id, $semestre]);
                    $existing = $stmt_check->fetch();
                    
                    if ($existing) {
                        // Mettre à jour la note existante
                        $stmt_update->execute([floatval($note), $type_eval['note_sur'], $date_evaluation, $existing['id']]);
                    } else {
                        // Insérer une nouvelle note
                        $stmt_insert->execute([$etudiant_id, $classe_matiere_id, $type_evaluation_id, floatval($note), $type_eval['note_sur'], $date_evaluation, $type_eval['coefficient'], $semestre]);
                    }
                }
            }
            
            $db->commit();
            log_activity('Saisie notes', 'notes', $classe_matiere_id);
            set_flash_message('Notes enregistrées avec succès', 'success');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        // Rediriger en conservant les filtres
        redirect('enseignant/notes.php?classe_matiere_id=' . $classe_matiere_id . '&type_evaluation_id=' . $type_evaluation_id . '&semestre_override=' . $semestre);
    }
    
    // Modifier une note individuelle
    if ($action === 'edit_note') {
        $note_id = intval($_POST['note_id']);
        $new_note = floatval($_POST['new_note']);
        $classe_matiere_id = intval($_POST['classe_matiere_id']);
        $type_evaluation_id = intval($_POST['type_evaluation_id']);
        $semestre = intval($_POST['semestre']);
        
        try {
            $stmt = $db->prepare("UPDATE notes SET note = ? WHERE id = ?");
            $stmt->execute([$new_note, $note_id]);
            
            log_activity('Modification note', 'notes', $note_id);
            set_flash_message('Note modifiée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('enseignant/notes.php?classe_matiere_id=' . $classe_matiere_id . '&type_evaluation_id=' . $type_evaluation_id . '&semestre_override=' . $semestre);
    }
    
    // Supprimer une note
    if ($action === 'delete_note') {
        $note_id = intval($_POST['note_id']);
        $classe_matiere_id = intval($_POST['classe_matiere_id']);
        $type_evaluation_id = intval($_POST['type_evaluation_id']);
        $semestre = intval($_POST['semestre']);
        
        try {
            $stmt = $db->prepare("DELETE FROM notes WHERE id = ?");
            $stmt->execute([$note_id]);
            
            log_activity('Suppression note', 'notes', $note_id);
            set_flash_message('Note supprimée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('enseignant/notes.php?classe_matiere_id=' . $classe_matiere_id . '&type_evaluation_id=' . $type_evaluation_id . '&semestre_override=' . $semestre);
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

// Types d'évaluation
$types_evaluation = $db->query("SELECT * FROM types_evaluation ORDER BY type, libelle")->fetchAll();

// Filtres
$classe_matiere_filter = $_GET['classe_matiere_id'] ?? '';
$type_eval_filter = $_GET['type_evaluation_id'] ?? '';

// Auto-sélection si paramètres fournis
$auto_select = $_GET['auto_select'] ?? 0;
if ($auto_select && !empty($_GET['classe_id']) && !empty($_GET['matiere_id'])) {
    $classe_id = intval($_GET['classe_id']);
    $matiere_id = intval($_GET['matiere_id']);
    
    // Trouver le classe_matiere_id correspondant
    $stmt = $db->prepare("SELECT id FROM classe_matieres WHERE classe_id = ? AND matiere_id = ? AND enseignant_id = ? LIMIT 1");
    $stmt->execute([$classe_id, $matiere_id, $enseignant_id]);
    $cm = $stmt->fetch();
    
    if ($cm) {
        $classe_matiere_filter = $cm['id'];
    }
} elseif ($auto_select && !empty($_GET['matiere_id'])) {
    $matiere_id = intval($_GET['matiere_id']);
    
    // Trouver le premier classe_matiere_id pour cette matière
    $stmt = $db->prepare("SELECT id FROM classe_matieres WHERE matiere_id = ? AND enseignant_id = ? LIMIT 1");
    $stmt->execute([$matiere_id, $enseignant_id]);
    $cm = $stmt->fetch();
    
    if ($cm) {
        $classe_matiere_filter = $cm['id'];
    }
}

// Récupérer les étudiants et leurs notes
$etudiants = [];
$classe_matiere_info = null;
$type_eval_info = null;

if (!empty($classe_matiere_filter) && !empty($type_eval_filter)) {
    // Informations
    $stmt = $db->prepare("SELECT cm.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau, m.libelle as matiere 
                          FROM classe_matieres cm 
                          JOIN classes c ON cm.classe_id = c.id 
                          JOIN filieres f ON c.filiere_id = f.id 
                          JOIN niveaux n ON c.niveau_id = n.id 
                          JOIN matieres m ON cm.matiere_id = m.id 
                          WHERE cm.id = ?");
    $stmt->execute([$classe_matiere_filter]);
    $classe_matiere_info = $stmt->fetch();
    
    $stmt = $db->prepare("SELECT * FROM types_evaluation WHERE id = ?");
    $stmt->execute([$type_eval_filter]);
    $type_eval_info = $stmt->fetch();
    
    if ($classe_matiere_info && $type_eval_info) {
        // Déterminer le semestre à utiliser (priorité à l'override, sinon du type d'évaluation)
        $semestre_eval = isset($_GET['semestre_override']) ? intval($_GET['semestre_override']) : ($type_eval_info['semestre'] ?? 1);
        
        // Récupérer les étudiants avec leurs notes du même semestre
        $stmt = $db->prepare("SELECT e.*, u.nom, u.prenom, u.email,
                              (SELECT note FROM notes WHERE etudiant_id = e.id AND classe_matiere_id = ? AND type_evaluation_id = ? AND semestre = ? LIMIT 1) as note_actuelle,
                              (SELECT semestre FROM notes WHERE etudiant_id = e.id AND classe_matiere_id = ? AND type_evaluation_id = ? AND semestre = ? LIMIT 1) as note_semestre
                              FROM etudiants e 
                              JOIN users u ON e.user_id = u.id 
                              WHERE e.classe_id = ? AND e.statut = 'actif'
                              ORDER BY u.nom, u.prenom");
        $stmt->execute([$classe_matiere_filter, $type_eval_filter, $semestre_eval, $classe_matiere_filter, $type_eval_filter, $semestre_eval, $classe_matiere_info['classe_id']]);
        $etudiants = $stmt->fetchAll();
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-edit"></i> Saisie des notes</h2>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label for="classe_matiere_id" class="form-label">Classe - Matière *</label>
                <select class="form-select" name="classe_matiere_id" id="classe_matiere_id" required onchange="this.form.submit()">
                    <option value="">Sélectionner...</option>
                    <?php foreach ($classes_matieres as $cm): ?>
                        <option value="<?php echo $cm['id']; ?>" <?php echo $classe_matiere_filter == $cm['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($cm['filiere'] . ' - ' . $cm['niveau'] . ' - ' . $cm['classe'] . ' | ' . $cm['matiere']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-6">
                <label for="type_evaluation_id" class="form-label">Type d'évaluation *</label>
                <select class="form-select" name="type_evaluation_id" id="type_evaluation_id" required onchange="this.form.submit()">
                    <option value="">Sélectionner...</option>
                    <?php foreach ($types_evaluation as $te): ?>
                        <option value="<?php echo $te['id']; ?>" <?php echo $type_eval_filter == $te['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($te['libelle'] . ' (Coef: ' . $te['coefficient'] . ', /' . $te['note_sur'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($etudiants) && $classe_matiere_info && $type_eval_info): ?>

<!-- Historique des notes déjà saisies -->
<?php
// Utiliser le même semestre que pour les étudiants
$stmt = $db->prepare("SELECT n.*, e.matricule, u.nom, u.prenom 
                      FROM notes n
                      JOIN etudiants e ON n.etudiant_id = e.id
                      JOIN users u ON e.user_id = u.id
                      WHERE n.classe_matiere_id = ? AND n.type_evaluation_id = ? AND n.semestre = ?
                      ORDER BY n.date_evaluation DESC, u.nom, u.prenom");
$stmt->execute([$classe_matiere_filter, $type_eval_filter, $semestre_eval]);
$notes_existantes = $stmt->fetchAll();

if (!empty($notes_existantes)):
?>
<div class="card mb-4">
    <div class="card-header" style="background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%); color: white;">
        <div class="d-flex align-items-center justify-content-between">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i> Notes déjà saisies</h5>
            <span class="badge" style="background: rgba(255,255,255,0.2);"><?php echo count($notes_existantes); ?> note(s)</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3">Semestre</th>
                        <th class="py-3">Matricule</th>
                        <th class="py-3">Étudiant</th>
                        <th class="py-3">Note</th>
                        <th class="py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes_existantes as $note): ?>
                    <tr>
                        <td class="py-2 px-3"><?php echo format_date($note['date_evaluation']); ?></td>
                        <td class="py-2">
                            <span class="badge bg-<?php echo ($note['semestre'] ?? 1) == 1 ? 'primary' : 'success'; ?>">
                                S<?php echo $note['semestre'] ?? 1; ?>
                            </span>
                        </td>
                        <td class="py-2"><?php echo escape_html($note['matricule']); ?></td>
                        <td class="py-2"><?php echo escape_html($note['prenom'] . ' ' . $note['nom']); ?></td>
                        <td class="py-2"><strong style="color: <?php echo $note['note'] >= ($note['note_sur']/2) ? '#28a745' : '#ef1c5d'; ?>;"><?php echo $note['note']; ?>/<?php echo $note['note_sur']; ?></strong></td>
                        <td class="py-2 text-center">
                            <button type="button" class="btn btn-sm btn-warning" onclick="editNote(<?php echo $note['id']; ?>, <?php echo $note['note']; ?>, <?php echo $note['note_sur']; ?>, '<?php echo escape_html($note['prenom'] . ' ' . $note['nom']); ?>')" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette note ?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_note">
                                <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                <input type="hidden" name="classe_matiere_id" value="<?php echo $classe_matiere_filter; ?>">
                                <input type="hidden" name="type_evaluation_id" value="<?php echo $type_eval_filter; ?>">
                                <input type="hidden" name="semestre" value="<?php echo $semestre_eval; ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de modification de note -->
<div class="modal fade" id="editNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f58024 0%, #fcb628 100%); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier la note</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit_note">
                <input type="hidden" name="note_id" id="edit_note_id">
                <input type="hidden" name="classe_matiere_id" value="<?php echo $classe_matiere_filter; ?>">
                <input type="hidden" name="type_evaluation_id" value="<?php echo $type_eval_filter; ?>">
                <input type="hidden" name="semestre" value="<?php echo $semestre_eval; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Étudiant</label>
                        <p id="edit_student_name" class="form-control-plaintext"></p>
                    </div>
                    <div class="mb-3">
                        <label for="new_note" class="form-label fw-bold">Nouvelle note</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control form-control-lg" name="new_note" id="edit_new_note" required>
                            <span class="input-group-text" id="edit_note_sur">/20</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Formulaire de saisie -->
<form method="POST" class="no-confirm">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save_notes">
    <input type="hidden" name="classe_matiere_id" value="<?php echo $classe_matiere_filter; ?>">
    <input type="hidden" name="type_evaluation_id" value="<?php echo $type_eval_filter; ?>">
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <?php echo escape_html($classe_matiere_info['filiere'] . ' - ' . $classe_matiere_info['niveau'] . ' - ' . $classe_matiere_info['classe']); ?>
                | <?php echo escape_html($classe_matiere_info['matiere']); ?>
            </h5>
            <small><?php echo escape_html($type_eval_info['libelle']); ?> - Coefficient: <?php echo $type_eval_info['coefficient']; ?> - Note sur: <?php echo $type_eval_info['note_sur']; ?></small>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="date_evaluation" class="form-label">Date de l'évaluation *</label>
                    <input type="date" class="form-control" name="date_evaluation" id="date_evaluation" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="semestre" class="form-label">Semestre *</label>
                    <select class="form-select" name="semestre" id="semestre" required onchange="reloadWithSemestre(this.value)">
                        <option value="1" <?php echo $semestre_eval == 1 ? 'selected' : ''; ?>>Semestre 1</option>
                        <option value="2" <?php echo $semestre_eval == 2 ? 'selected' : ''; ?>>Semestre 2</option>
                    </select>
                </div>
            </div>
            
            <hr>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Matricule</th>
                            <th>Nom complet</th>
                            <th style="width: 150px;">Note / <?php echo $type_eval_info['note_sur']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etudiants as $etudiant): ?>
                        <tr>
                            <td><?php echo escape_html($etudiant['matricule']); ?></td>
                            <td><strong><?php echo escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></strong></td>
                            <td>
                                <input type="number" step="0.01" min="0" max="<?php echo $type_eval_info['note_sur']; ?>" 
                                       class="form-control" name="notes[<?php echo $etudiant['id']; ?>]" 
                                       value="<?php echo $etudiant['note_actuelle'] ?? ''; ?>" 
                                       placeholder="0.00">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-save"></i> Enregistrer les notes
                </button>
            </div>
        </div>
    </div>
</form>
<?php else: ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Veuillez sélectionner une classe et un type d'évaluation pour saisir les notes.
</div>
<?php endif; ?>

<script>
function reloadWithSemestre(semestre) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('semestre_override', semestre);
    window.location.href = window.location.pathname + '?' + urlParams.toString();
}

function editNote(noteId, currentNote, noteSur, studentName) {
    document.getElementById('edit_note_id').value = noteId;
    document.getElementById('edit_new_note').value = currentNote;
    document.getElementById('edit_new_note').max = noteSur;
    document.getElementById('edit_note_sur').textContent = '/' + noteSur;
    document.getElementById('edit_student_name').textContent = studentName;
    
    new bootstrap.Modal(document.getElementById('editNoteModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
