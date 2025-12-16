<?php
require_once '../config/config.php';
require_role('enseignant');

$page_title = 'Mes Documents';
$db = Database::getInstance()->getConnection();

// Traitement upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload') {
        $titre = clean_input($_POST['titre']);
        $description = clean_input($_POST['description']);
        $classe_id = !empty($_POST['classe_id']) ? intval($_POST['classe_id']) : null;
        
        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === 0) {
            $upload_dir = '../uploads/documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $file_path)) {
                try {
                    $stmt = $db->prepare("INSERT INTO documents (titre, description, fichier, type_fichier, classe_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$titre, $description, $file_name, $file_extension, $classe_id, $_SESSION['user_id']]);
                    
                    log_activity('Upload document', 'documents', $db->lastInsertId(), $titre);
                    set_flash_message('Document uploadé avec succès', 'success');
                } catch (Exception $e) {
                    set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
                }
            } else {
                set_flash_message('Erreur lors de l\'upload du fichier', 'danger');
            }
        }
        
        redirect('enseignant/documents.php');
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        
        try {
            // Récupérer le fichier
            $stmt = $db->prepare("SELECT fichier FROM documents WHERE id = ? AND uploaded_by = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $doc = $stmt->fetch();
            
            if ($doc) {
                // Supprimer le fichier
                $file_path = '../uploads/documents/' . $doc['fichier'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Supprimer de la base
                $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
                $stmt->execute([$id]);
                
                log_activity('Suppression document', 'documents', $id);
                set_flash_message('Document supprimé avec succès', 'success');
            }
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('enseignant/documents.php');
    }
}

// Vérifier si la table existe
try {
    $db->query("SELECT 1 FROM documents LIMIT 1");
} catch (Exception $e) {
    // Créer la table
    $db->exec("CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titre VARCHAR(255) NOT NULL,
        description TEXT,
        fichier VARCHAR(255) NOT NULL,
        type_fichier VARCHAR(50),
        classe_id INT,
        uploaded_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Récupérer les classes de l'enseignant
$stmt = $db->prepare("SELECT DISTINCT c.id, c.libelle, f.libelle as filiere, n.libelle as niveau
                      FROM classe_matieres cm
                      JOIN classes c ON cm.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      WHERE cm.enseignant_id = ?
                      ORDER BY f.libelle, n.ordre");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();

// Récupérer les documents
$stmt = $db->prepare("SELECT d.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau
                      FROM documents d
                      LEFT JOIN classes c ON d.classe_id = c.id
                      LEFT JOIN filieres f ON c.filiere_id = f.id
                      LEFT JOIN niveaux n ON c.niveau_id = n.id
                      WHERE d.uploaded_by = ?
                      ORDER BY d.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$documents = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-file-upload"></i> Mes Documents</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fas fa-plus"></i> Nouveau document
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($documents)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Vous n'avez pas encore uploadé de documents.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Titre</th>
                        
                        <th>Classe</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><strong><?php echo escape_html($doc['titre']); ?></strong></td>
                        
                        <td>
                            <?php if ($doc['classe']): ?>
                                <span class="badge bg-info">
                                    <?php echo escape_html($doc['filiere'] . ' - ' . $doc['niveau'] . ' - ' . $doc['classe']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Tous</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?php echo strtoupper($doc['type_fichier']); ?></span></td>
                        <td><?php echo format_date($doc['created_at']); ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/uploads/documents/<?php echo $doc['fichier']; ?>" 
                               class="btn btn-sm btn-primary" target="_blank">
                                <i class="fas fa-download"></i>
                            </a>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Supprimer ce document ?')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
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

<!-- Modal Upload -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="upload">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="titre" class="form-label">Titre *</label>
                        <input type="text" class="form-control" id="titre" name="titre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="classe_id" class="form-label">Classe (optionnel)</label>
                        <select class="form-select" id="classe_id" name="classe_id">
                            <option value="">Tous les étudiants</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>">
                                    <?php echo escape_html($classe['filiere'] . ' - ' . $classe['niveau'] . ' - ' . $classe['libelle']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fichier" class="form-label">Fichier *</label>
                        <input type="file" class="form-control" id="fichier" name="fichier" required>
                        <small class="text-muted">Formats acceptés: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Uploader</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
