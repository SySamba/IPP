<?php
require_once '../config/config.php';
require_role('admin');

$page_title = 'Gestion des filières';
$db = Database::getInstance()->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $code = clean_input($_POST['code']);
        $libelle = clean_input($_POST['libelle']);
        $description = clean_input($_POST['description']);
        $statut = clean_input($_POST['statut']);
        
        try {
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO filieres (code, libelle, description, statut) VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $libelle, $description, $statut]);
                
                log_activity('Création filière', 'filieres', $db->lastInsertId(), $libelle);
                set_flash_message('Filière créée avec succès', 'success');
            } else {
                $stmt = $db->prepare("UPDATE filieres SET code = ?, libelle = ?, description = ?, statut = ? WHERE id = ?");
                $stmt->execute([$code, $libelle, $description, $statut, $id]);
                
                log_activity('Modification filière', 'filieres', $id, $libelle);
                set_flash_message('Filière modifiée avec succès', 'success');
            }
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/filieres.php');
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM filieres WHERE id = ?");
            $stmt->execute([$id]);
            
            log_activity('Suppression filière', 'filieres', $id);
            set_flash_message('Filière supprimée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Impossible de supprimer cette filière (données liées)', 'danger');
        }
        
        redirect('admin/filieres.php');
    }
}

// Récupération des filières
$stmt = $db->query("SELECT f.*, COUNT(DISTINCT c.id) as nb_classes FROM filieres f LEFT JOIN classes c ON f.id = c.filiere_id GROUP BY f.id ORDER BY f.libelle");
$filieres = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-stream"></i> Gestion des filières</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#filiereModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Nouvelle filière
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Description</th>
                        <th>Nombre de classes</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filieres as $filiere): ?>
                    <tr>
                        <td><strong><?php echo escape_html($filiere['code']); ?></strong></td>
                        <td><?php echo escape_html($filiere['libelle']); ?></td>
                        <td><?php echo escape_html($filiere['description']); ?></td>
                        <td><span class="badge bg-info"><?php echo $filiere['nb_classes']; ?></span></td>
                        <td>
                            <span class="badge bg-<?php echo $filiere['statut'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($filiere['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="editFiliere(<?php echo htmlspecialchars(json_encode($filiere)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Supprimer cette filière ?')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $filiere['id']; ?>">
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
    </div>
</div>

<!-- Modal Filière -->
<div class="modal fade" id="filiereModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nouvelle filière</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="filiereForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="filiere_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="code" class="form-label">Code *</label>
                        <input type="text" class="form-control" id="code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="libelle" class="form-label">Libellé *</label>
                        <input type="text" class="form-control" id="libelle" name="libelle" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
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
    document.getElementById('filiereForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('filiere_id').value = '';
    document.getElementById('modalTitle').textContent = 'Nouvelle filière';
}

function editFiliere(filiere) {
    document.getElementById('action').value = 'edit';
    document.getElementById('filiere_id').value = filiere.id;
    document.getElementById('code').value = filiere.code;
    document.getElementById('libelle').value = filiere.libelle;
    document.getElementById('description').value = filiere.description || '';
    document.getElementById('statut').value = filiere.statut;
    document.getElementById('modalTitle').textContent = 'Modifier la filière';
    
    new bootstrap.Modal(document.getElementById('filiereModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
