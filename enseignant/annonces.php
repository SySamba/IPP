<?php
require_once '../config/config.php';
require_role('enseignant');

$page_title = 'Mes annonces d\'absence';
$db = Database::getInstance()->getConnection();

$enseignant_id = $_SESSION['user_id'];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $cours_id = intval($_POST['cours_id']);
        $date_absence = clean_input($_POST['date_absence']);
        $message = clean_input($_POST['message']);
        
        try {
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO annonces_absence (cours_id, enseignant_id, date_absence, message) VALUES (?, ?, ?, ?)");
                $stmt->execute([$cours_id, $enseignant_id, $date_absence, $message]);
                
                log_activity('Création annonce absence', 'annonces_absence', $db->lastInsertId());
                set_flash_message('Annonce d\'absence créée avec succès', 'success');
            } else {
                $stmt = $db->prepare("UPDATE annonces_absence SET cours_id = ?, date_absence = ?, message = ? WHERE id = ? AND enseignant_id = ?");
                $stmt->execute([$cours_id, $date_absence, $message, $id, $enseignant_id]);
                
                log_activity('Modification annonce absence', 'annonces_absence', $id);
                set_flash_message('Annonce d\'absence modifiée avec succès', 'success');
            }
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('enseignant/annonces.php');
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        
        try {
            $stmt = $db->prepare("DELETE FROM annonces_absence WHERE id = ? AND enseignant_id = ?");
            $stmt->execute([$id, $enseignant_id]);
            
            log_activity('Suppression annonce absence', 'annonces_absence', $id);
            set_flash_message('Annonce d\'absence supprimée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('enseignant/annonces.php');
    }
}

// Récupérer les cours de l'enseignant
$stmt = $db->prepare("SELECT edt.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau,
                      m.libelle as matiere, s.libelle as salle
                      FROM emplois_du_temps edt
                      JOIN classes c ON edt.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      JOIN matieres m ON edt.matiere_id = m.id
                      LEFT JOIN salles s ON edt.salle_id = s.id
                      WHERE edt.enseignant_id = ?
                      ORDER BY FIELD(edt.jour, 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'), edt.heure_debut");
$stmt->execute([$enseignant_id]);
$cours = $stmt->fetchAll();

// Récupérer les annonces d'absence
$stmt = $db->prepare("SELECT aa.*, edt.jour, edt.heure_debut, edt.heure_fin,
                      c.libelle as classe, m.libelle as matiere
                      FROM annonces_absence aa
                      JOIN emplois_du_temps edt ON aa.cours_id = edt.id
                      JOIN classes c ON edt.classe_id = c.id
                      JOIN matieres m ON edt.matiere_id = m.id
                      WHERE aa.enseignant_id = ?
                      ORDER BY aa.date_absence DESC");
$stmt->execute([$enseignant_id]);
$annonces = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-bullhorn"></i> Mes annonces d'absence</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#annonceModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Nouvelle annonce
            </button>
        </div>
    </div>
</div>

<!-- Liste des annonces -->
<div class="card">
    <div class="card-body">
        <?php if (empty($annonces)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Aucune annonce d'absence.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Cours</th>
                            <th>Classe</th>
                            <th>Horaire</th>
                            <th>Message</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($annonces as $annonce): ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?php echo strtotime($annonce['date_absence']) >= strtotime(date('Y-m-d')) ? 'warning' : 'secondary'; ?>">
                                    <?php echo date('d/m/Y', strtotime($annonce['date_absence'])); ?>
                                </span>
                            </td>
                            <td><strong><?php echo escape_html($annonce['matiere']); ?></strong></td>
                            <td><?php echo escape_html($annonce['classe']); ?></td>
                            <td><?php echo ucfirst($annonce['jour']) . ' ' . substr($annonce['heure_debut'], 0, 5) . '-' . substr($annonce['heure_fin'], 0, 5); ?></td>
                            <td><?php echo escape_html($annonce['message']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="editAnnonce(<?php echo htmlspecialchars(json_encode($annonce)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette annonce ?')">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $annonce['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Annonce -->
<div class="modal fade" id="annonceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nouvelle annonce d'absence</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="annonceForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="annonce_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cours_id" class="form-label">Cours *</label>
                        <select class="form-select" id="cours_id" name="cours_id" required>
                            <option value="">Sélectionner un cours...</option>
                            <?php foreach ($cours as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo escape_html($c['matiere'] . ' - ' . $c['classe'] . ' (' . ucfirst($c['jour']) . ' ' . substr($c['heure_debut'], 0, 5) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_absence" class="form-label">Date d'absence *</label>
                        <input type="date" class="form-control" id="date_absence" name="date_absence" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message aux étudiants *</label>
                        <textarea class="form-control" id="message" name="message" rows="3" required placeholder="Ex: Cours annulé pour raison de santé. Travail à faire: lire le chapitre 5."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('annonceForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('annonce_id').value = '';
    document.getElementById('modalTitle').textContent = 'Nouvelle annonce d\'absence';
}

function editAnnonce(annonce) {
    document.getElementById('action').value = 'edit';
    document.getElementById('annonce_id').value = annonce.id;
    document.getElementById('cours_id').value = annonce.cours_id;
    document.getElementById('date_absence').value = annonce.date_absence;
    document.getElementById('message').value = annonce.message;
    document.getElementById('modalTitle').textContent = 'Modifier l\'annonce';
    
    new bootstrap.Modal(document.getElementById('annonceModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
