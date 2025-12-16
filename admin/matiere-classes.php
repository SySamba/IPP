<?php
require_once '../config/config.php';
require_role('admin');

$page_title = 'Classes de la matière';
$db = Database::getInstance()->getConnection();

$matiere_id = intval($_GET['matiere_id'] ?? 0);

// Récupération de la matière
$stmt = $db->prepare("SELECT * FROM matieres WHERE id = ?");
$stmt->execute([$matiere_id]);
$matiere = $stmt->fetch();

if (!$matiere) {
    set_flash_message('Matière introuvable', 'danger');
    redirect('admin/matieres.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_multiple') {
        $classe_ids = $_POST['classe_ids'] ?? [];
        $coefficient = floatval($_POST['coefficient']);
        
        try {
            $db->beginTransaction();
            
            foreach ($classe_ids as $classe_id) {
                // Vérifier si déjà existant
                $stmt = $db->prepare("SELECT id FROM classe_matieres WHERE classe_id = ? AND matiere_id = ?");
                $stmt->execute([$classe_id, $matiere_id]);
                
                if (!$stmt->fetch()) {
                    $stmt = $db->prepare("INSERT INTO classe_matieres (classe_id, matiere_id, coefficient) VALUES (?, ?, ?)");
                    $stmt->execute([$classe_id, $matiere_id, $coefficient]);
                }
            }
            
            $db->commit();
            log_activity('Ajout matière à classes multiples', 'classe_matieres', $matiere_id);
            set_flash_message('Matière ajoutée aux classes sélectionnées', 'success');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/matiere-classes.php?matiere_id=' . $matiere_id);
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        
        try {
            $stmt = $db->prepare("DELETE FROM classe_matieres WHERE id = ?");
            $stmt->execute([$id]);
            
            log_activity('Retrait matière de classe', 'classe_matieres', $id);
            set_flash_message('Matière retirée de la classe', 'success');
        } catch (Exception $e) {
            set_flash_message('Impossible de retirer cette matière (données liées)', 'danger');
        }
        
        redirect('admin/matiere-classes.php?matiere_id=' . $matiere_id);
    }
}

// Classes ayant cette matière
$stmt = $db->prepare("SELECT cm.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau,
                      u.nom as enseignant_nom, u.prenom as enseignant_prenom
                      FROM classe_matieres cm
                      JOIN classes c ON cm.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      LEFT JOIN users u ON cm.enseignant_id = u.id
                      WHERE cm.matiere_id = ?
                      ORDER BY f.libelle, n.ordre, c.libelle");
$stmt->execute([$matiere_id]);
$classe_matieres = $stmt->fetchAll();

// Classes disponibles (n'ayant pas encore cette matière)
$stmt = $db->prepare("SELECT c.*, f.libelle as filiere, n.libelle as niveau
                      FROM classes c
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      WHERE c.statut = 'active' 
                      AND c.id NOT IN (SELECT classe_id FROM classe_matieres WHERE matiere_id = ?)
                      ORDER BY f.libelle, n.ordre, c.libelle");
$stmt->execute([$matiere_id]);
$classes_disponibles = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/matieres.php">Matières</a></li>
                <li class="breadcrumb-item active"><?php echo escape_html($matiere['libelle']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-school"></i> Classes - <?php echo escape_html($matiere['libelle']); ?></h2>
                <p class="text-muted mb-0">Code: <?php echo escape_html($matiere['code']); ?> | Coefficient par défaut: <?php echo $matiere['coefficient']; ?></p>
            </div>
            <?php if (!empty($classes_disponibles)): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus"></i> Ajouter à des classes
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($classe_matieres)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Cette matière n'est assignée à aucune classe.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Filière</th>
                        <th>Niveau</th>
                        <th>Classe</th>
                        <th>Coefficient</th>
                        <th>Enseignant</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classe_matieres as $cm): ?>
                    <tr>
                        <td><?php echo escape_html($cm['filiere']); ?></td>
                        <td><?php echo escape_html($cm['niveau']); ?></td>
                        <td><strong><?php echo escape_html($cm['classe']); ?></strong></td>
                        <td><span class="badge bg-info"><?php echo $cm['coefficient']; ?></span></td>
                        <td>
                            <?php if ($cm['enseignant_nom']): ?>
                                <?php echo escape_html($cm['enseignant_prenom'] . ' ' . $cm['enseignant_nom']); ?>
                            <?php else: ?>
                                <span class="text-muted">Non assigné</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Retirer cette matière de la classe ?')">
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

<!-- Modal Ajouter à plusieurs classes -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter à des classes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_multiple">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="coefficient" class="form-label">Coefficient *</label>
                        <input type="number" step="0.1" class="form-control" id="coefficient" name="coefficient" 
                               value="<?php echo $matiere['coefficient']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sélectionner les classes *</label>
                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                            <?php 
                            $current_filiere = '';
                            foreach ($classes_disponibles as $classe): 
                                if ($current_filiere !== $classe['filiere']) {
                                    if ($current_filiere !== '') echo '</div>';
                                    $current_filiere = $classe['filiere'];
                                    echo '<div class="mb-3"><h6 class="text-primary">' . escape_html($current_filiere) . '</h6>';
                                }
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="classe_ids[]" 
                                           value="<?php echo $classe['id']; ?>" id="classe_<?php echo $classe['id']; ?>">
                                    <label class="form-check-label" for="classe_<?php echo $classe['id']; ?>">
                                        <?php echo escape_html($classe['niveau'] . ' - ' . $classe['libelle']); ?>
                                    </label>
                                </div>
                            <?php 
                            endforeach; 
                            if ($current_filiere !== '') echo '</div>';
                            ?>
                        </div>
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

<?php include '../includes/footer.php'; ?>
