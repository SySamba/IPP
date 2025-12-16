<?php
require_once '../config/config.php';
require_role('admin');

$page_title = 'Gestion des matières';
$db = Database::getInstance()->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $code = clean_input($_POST['code']);
        $libelle = clean_input($_POST['libelle']);
        $coefficient = floatval($_POST['coefficient']);
        $statut = clean_input($_POST['statut']);
        
        try {
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO matieres (code, libelle, coefficient, statut) VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $libelle, $coefficient, $statut]);
                
                log_activity('Création matière', 'matieres', $db->lastInsertId(), $libelle);
                set_flash_message('Matière créée avec succès', 'success');
            } else {
                $stmt = $db->prepare("UPDATE matieres SET code = ?, libelle = ?, coefficient = ?, statut = ? WHERE id = ?");
                $stmt->execute([$code, $libelle, $coefficient, $statut, $id]);
                
                log_activity('Modification matière', 'matieres', $id, $libelle);
                set_flash_message('Matière modifiée avec succès', 'success');
            }
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/matieres.php');
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM matieres WHERE id = ?");
            $stmt->execute([$id]);
            
            log_activity('Suppression matière', 'matieres', $id);
            set_flash_message('Matière supprimée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Impossible de supprimer cette matière (données liées)', 'danger');
        }
        
        redirect('admin/matieres.php');
    }
}

// Récupération des matières avec détails
$stmt = $db->query("SELECT m.*, 
                    COUNT(DISTINCT cm.classe_id) as nb_classes,
                    COUNT(DISTINCT cm.enseignant_id) as nb_enseignants,
                    GROUP_CONCAT(DISTINCT CONCAT(c.libelle) ORDER BY c.libelle SEPARATOR ', ') as classes_list,
                    GROUP_CONCAT(DISTINCT CONCAT(u.prenom, ' ', u.nom) ORDER BY u.nom SEPARATOR ', ') as enseignants_list
                    FROM matieres m 
                    LEFT JOIN classe_matieres cm ON m.id = cm.matiere_id 
                    LEFT JOIN classes c ON cm.classe_id = c.id
                    LEFT JOIN users u ON cm.enseignant_id = u.id
                    GROUP BY m.id 
                    ORDER BY m.libelle");
$matieres = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-book"></i> Gestion des matières</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#matiereModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Nouvelle matière
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
                        <th>Coefficient</th>
                        <th>Classes</th>
                        <th>Enseignants</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matieres as $matiere): ?>
                    <tr>
                        <td><strong><?php echo escape_html($matiere['code']); ?></strong></td>
                        <td><?php echo escape_html($matiere['libelle']); ?></td>
                        <td><span class="badge bg-info"><?php echo $matiere['coefficient']; ?></span></td>
                        <td>
                            <span class="badge bg-primary"><?php echo $matiere['nb_classes']; ?> classe(s)</span>
                            <?php if ($matiere['classes_list']): ?>
                                <br><small class="text-muted"><?php echo escape_html($matiere['classes_list']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-success"><?php echo $matiere['nb_enseignants']; ?> prof(s)</span>
                            <?php if ($matiere['enseignants_list']): ?>
                                <br><small class="text-muted"><?php echo escape_html($matiere['enseignants_list']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $matiere['statut'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($matiere['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/admin/matiere-detail.php?matiere_id=<?php echo $matiere['id']; ?>" 
                               class="btn btn-sm btn-success" title="Voir les affectations">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/matiere-classes.php?matiere_id=<?php echo $matiere['id']; ?>" 
                               class="btn btn-sm btn-primary" title="Gérer les classes">
                                <i class="fas fa-school"></i>
                            </a>
                            <button class="btn btn-sm btn-info" onclick="editMatiere(<?php echo htmlspecialchars(json_encode($matiere)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Supprimer cette matière ?')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $matiere['id']; ?>">
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

<!-- Modal Matière -->
<div class="modal fade" id="matiereModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nouvelle matière</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="matiereForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="matiere_id">
                
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
                        <label for="coefficient" class="form-label">Coefficient *</label>
                        <input type="number" step="0.1" class="form-control" id="coefficient" name="coefficient" value="1.0" required>
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
    document.getElementById('matiereForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('matiere_id').value = '';
    document.getElementById('modalTitle').textContent = 'Nouvelle matière';
}

function editMatiere(matiere) {
    document.getElementById('action').value = 'edit';
    document.getElementById('matiere_id').value = matiere.id;
    document.getElementById('code').value = matiere.code;
    document.getElementById('libelle').value = matiere.libelle;
    document.getElementById('coefficient').value = matiere.coefficient;
    document.getElementById('statut').value = matiere.statut;
    document.getElementById('modalTitle').textContent = 'Modifier la matière';
    
    new bootstrap.Modal(document.getElementById('matiereModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
