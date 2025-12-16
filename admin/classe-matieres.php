<?php
require_once '../config/config.php';
require_role('admin');

$page_title = 'Matières de la classe';
$db = Database::getInstance()->getConnection();

$classe_id = intval($_GET['classe_id'] ?? 0);

// Récupération des informations de la classe
$stmt = $db->prepare("SELECT c.*, f.libelle as filiere, n.libelle as niveau, aa.libelle as annee 
                      FROM classes c
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      JOIN annees_academiques aa ON c.annee_academique_id = aa.id
                      WHERE c.id = ?");
$stmt->execute([$classe_id]);
$classe = $stmt->fetch();

if (!$classe) {
    set_flash_message('Classe introuvable', 'danger');
    redirect('admin/classes.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $matiere_id = intval($_POST['matiere_id']);
        $enseignant_id = !empty($_POST['enseignant_id']) ? intval($_POST['enseignant_id']) : null;
        $coefficient = floatval($_POST['coefficient']);
        
        try {
            $stmt = $db->prepare("INSERT INTO classe_matieres (classe_id, matiere_id, enseignant_id, coefficient) VALUES (?, ?, ?, ?)");
            $stmt->execute([$classe_id, $matiere_id, $enseignant_id, $coefficient]);
            
            log_activity('Ajout matière à classe', 'classe_matieres', $db->lastInsertId());
            set_flash_message('Matière ajoutée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Erreur: Cette matière existe déjà pour cette classe', 'danger');
        }
        
        redirect('admin/classe-matieres.php?classe_id=' . $classe_id);
    }
    
    if ($action === 'update') {
        $id = intval($_POST['id']);
        $enseignant_id = !empty($_POST['enseignant_id']) ? intval($_POST['enseignant_id']) : null;
        $coefficient = floatval($_POST['coefficient']);
        
        try {
            $stmt = $db->prepare("UPDATE classe_matieres SET enseignant_id = ?, coefficient = ? WHERE id = ?");
            $stmt->execute([$enseignant_id, $coefficient, $id]);
            
            log_activity('Modification matière classe', 'classe_matieres', $id);
            set_flash_message('Matière modifiée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Erreur lors de la modification', 'danger');
        }
        
        redirect('admin/classe-matieres.php?classe_id=' . $classe_id);
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        
        try {
            $stmt = $db->prepare("DELETE FROM classe_matieres WHERE id = ?");
            $stmt->execute([$id]);
            
            log_activity('Suppression matière classe', 'classe_matieres', $id);
            set_flash_message('Matière retirée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Impossible de retirer cette matière (données liées)', 'danger');
        }
        
        redirect('admin/classe-matieres.php?classe_id=' . $classe_id);
    }
}

// Récupération des matières de la classe
$stmt = $db->prepare("SELECT cm.*, m.libelle as matiere, m.code as matiere_code, 
                      u.nom as enseignant_nom, u.prenom as enseignant_prenom
                      FROM classe_matieres cm
                      JOIN matieres m ON cm.matiere_id = m.id
                      LEFT JOIN users u ON cm.enseignant_id = u.id
                      WHERE cm.classe_id = ?
                      ORDER BY m.libelle");
$stmt->execute([$classe_id]);
$classe_matieres = $stmt->fetchAll();

// Matières disponibles (non encore ajoutées)
$stmt = $db->prepare("SELECT * FROM matieres WHERE statut = 'active' AND id NOT IN (SELECT matiere_id FROM classe_matieres WHERE classe_id = ?) ORDER BY libelle");
$stmt->execute([$classe_id]);
$matieres_disponibles = $stmt->fetchAll();

// Enseignants disponibles
$stmt = $db->query("SELECT id, nom, prenom FROM users WHERE role = 'enseignant' AND statut = 'actif' ORDER BY nom, prenom");
$enseignants = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/classes.php">Classes</a></li>
                <li class="breadcrumb-item active"><?php echo escape_html($classe['libelle']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-book"></i> Matières - <?php echo escape_html($classe['libelle']); ?></h2>
                <p class="text-muted mb-0">
                    <?php echo escape_html($classe['filiere']); ?> - 
                    <?php echo escape_html($classe['niveau']); ?> - 
                    <?php echo escape_html($classe['annee']); ?>
                </p>
            </div>
            <?php if (!empty($matieres_disponibles)): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#matiereModal">
                <i class="fas fa-plus"></i> Ajouter une matière
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($classe_matieres)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Aucune matière n'a été ajoutée à cette classe.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Matière</th>
                        <th>Coefficient</th>
                        <th>Enseignant</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classe_matieres as $cm): ?>
                    <tr>
                        <td><strong><?php echo escape_html($cm['matiere_code']); ?></strong></td>
                        <td><?php echo escape_html($cm['matiere']); ?></td>
                        <td><span class="badge bg-info"><?php echo $cm['coefficient']; ?></span></td>
                        <td>
                            <?php if ($cm['enseignant_nom']): ?>
                                <?php echo escape_html($cm['enseignant_prenom'] . ' ' . $cm['enseignant_nom']); ?>
                            <?php else: ?>
                                <span class="text-muted">Non assigné</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="editClasseMatiere(<?php echo htmlspecialchars(json_encode($cm)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Retirer cette matière ?')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $cm['id']; ?>">
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

<!-- Modal Ajouter Matière -->
<div class="modal fade" id="matiereModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une matière</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="matiere_id" class="form-label">Matière *</label>
                        <select class="form-select" id="matiere_id" name="matiere_id" required>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($matieres_disponibles as $matiere): ?>
                                <option value="<?php echo $matiere['id']; ?>">
                                    <?php echo escape_html($matiere['libelle']); ?> (Coef: <?php echo $matiere['coefficient']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="enseignant_id" class="form-label">Enseignant</label>
                        <select class="form-select" id="enseignant_id" name="enseignant_id">
                            <option value="">Non assigné</option>
                            <?php foreach ($enseignants as $enseignant): ?>
                                <option value="<?php echo $enseignant['id']; ?>">
                                    <?php echo escape_html($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="coefficient" class="form-label">Coefficient *</label>
                        <input type="number" step="0.1" class="form-control" id="coefficient" name="coefficient" value="1.0" required>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier la matière</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Matière</label>
                        <input type="text" class="form-control" id="edit_matiere" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_enseignant_id" class="form-label">Enseignant</label>
                        <select class="form-select" id="edit_enseignant_id" name="enseignant_id">
                            <option value="">Non assigné</option>
                            <?php foreach ($enseignants as $enseignant): ?>
                                <option value="<?php echo $enseignant['id']; ?>">
                                    <?php echo escape_html($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_coefficient" class="form-label">Coefficient *</label>
                        <input type="number" step="0.1" class="form-control" id="edit_coefficient" name="coefficient" required>
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
function editClasseMatiere(cm) {
    document.getElementById('edit_id').value = cm.id;
    document.getElementById('edit_matiere').value = cm.matiere;
    document.getElementById('edit_enseignant_id').value = cm.enseignant_id || '';
    document.getElementById('edit_coefficient').value = cm.coefficient;
    
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
