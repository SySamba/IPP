<?php
require_once '../config/config.php';
require_role('admin');

$page_title = 'Gestion des évaluations';
$db = Database::getInstance()->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $libelle = clean_input($_POST['libelle']);
        $type = clean_input($_POST['type']);
        $coefficient = floatval($_POST['coefficient']);
        $note_sur = floatval($_POST['note_sur']);
        $semestre = intval($_POST['semestre']);
        $classe_id = !empty($_POST['classe_id']) ? intval($_POST['classe_id']) : null;
        $filiere_id = !empty($_POST['filiere_id']) ? intval($_POST['filiere_id']) : null;
        $matiere_id = !empty($_POST['matiere_id']) ? intval($_POST['matiere_id']) : null;
        
        try {
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO types_evaluation (libelle, type, coefficient, note_sur, semestre, classe_id, filiere_id, matiere_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$libelle, $type, $coefficient, $note_sur, $semestre, $classe_id, $filiere_id, $matiere_id]);
                
                log_activity('Création type évaluation', 'types_evaluation', $db->lastInsertId(), $libelle);
                set_flash_message('Type d\'évaluation créé avec succès', 'success');
            } else {
                $stmt = $db->prepare("UPDATE types_evaluation SET libelle = ?, type = ?, coefficient = ?, note_sur = ?, semestre = ?, classe_id = ?, filiere_id = ?, matiere_id = ? WHERE id = ?");
                $stmt->execute([$libelle, $type, $coefficient, $note_sur, $semestre, $classe_id, $filiere_id, $matiere_id, $id]);
                
                log_activity('Modification type évaluation', 'types_evaluation', $id, $libelle);
                set_flash_message('Type d\'évaluation modifié avec succès', 'success');
            }
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/evaluations.php');
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        
        try {
            $stmt = $db->prepare("DELETE FROM types_evaluation WHERE id = ?");
            $stmt->execute([$id]);
            
            log_activity('Suppression type évaluation', 'types_evaluation', $id);
            set_flash_message('Type d\'évaluation supprimé avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Impossible de supprimer ce type (données liées)', 'danger');
        }
        
        redirect('admin/evaluations.php');
    }
}

// Vérifier si la table existe, sinon la créer
try {
    $db->query("SELECT 1 FROM types_evaluation LIMIT 1");
    
    // Vérifier si la colonne semestre existe, sinon l'ajouter
    try {
        $db->query("SELECT semestre FROM types_evaluation LIMIT 1");
    } catch (Exception $e) {
        // Ajouter la colonne semestre
        $db->exec("ALTER TABLE types_evaluation ADD COLUMN semestre INT DEFAULT 1 AFTER note_sur");
    }
    
    // Vérifier si la colonne matiere_id existe, sinon l'ajouter
    try {
        $db->query("SELECT matiere_id FROM types_evaluation LIMIT 1");
    } catch (Exception $e) {
        // Ajouter la colonne matiere_id
        $db->exec("ALTER TABLE types_evaluation ADD COLUMN matiere_id INT AFTER filiere_id");
        $db->exec("ALTER TABLE types_evaluation ADD FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE SET NULL");
    }
} catch (Exception $e) {
    // Créer la table
    $db->exec("CREATE TABLE IF NOT EXISTS types_evaluation (
        id INT AUTO_INCREMENT PRIMARY KEY,
        libelle VARCHAR(100) NOT NULL,
        type ENUM('devoir', 'composition', 'examen', 'tp', 'projet') NOT NULL,
        coefficient DECIMAL(3,1) DEFAULT 1.0,
        note_sur DECIMAL(5,2) DEFAULT 20.00,
        semestre INT DEFAULT 1,
        classe_id INT,
        filiere_id INT,
        matiere_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL,
        FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE SET NULL,
        FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Insérer des types par défaut
    $db->exec("INSERT INTO types_evaluation (libelle, type, coefficient, note_sur, semestre) VALUES
        ('Devoir 1', 'devoir', 1.0, 20.00, 1),
        ('Devoir 2', 'devoir', 1.0, 20.00, 1),
        ('Composition', 'composition', 2.0, 20.00, 1),
        ('Examen Final', 'examen', 3.0, 20.00, 1),
        ('Travaux Pratiques', 'tp', 1.0, 20.00, 1)");
}

// Classes, filières et matières
$classes = $db->query("SELECT c.*, f.libelle as filiere, n.libelle as niveau FROM classes c JOIN filieres f ON c.filiere_id = f.id JOIN niveaux n ON c.niveau_id = n.id WHERE c.statut = 'active' ORDER BY f.libelle, n.ordre")->fetchAll();
$filieres = $db->query("SELECT * FROM filieres WHERE statut = 'active' ORDER BY libelle")->fetchAll();
$matieres = $db->query("SELECT * FROM matieres WHERE statut = 'active' ORDER BY libelle")->fetchAll();

// Filtres
$semestre_filter = $_GET['semestre'] ?? '1'; // Par défaut semestre 1
$type_filter = $_GET['type'] ?? '';

// Récupération des types d'évaluation avec filtres
$sql = "SELECT te.*, c.libelle as classe, f.libelle as filiere, m.libelle as matiere 
        FROM types_evaluation te 
        LEFT JOIN classes c ON te.classe_id = c.id 
        LEFT JOIN filieres f ON te.filiere_id = f.id 
        LEFT JOIN matieres m ON te.matiere_id = m.id 
        WHERE 1=1";
$params = [];

if (!empty($semestre_filter)) {
    $sql .= " AND te.semestre = ?";
    $params[] = $semestre_filter;
}

if (!empty($type_filter)) {
    $sql .= " AND te.type = ?";
    $params[] = $type_filter;
}

$sql .= " ORDER BY te.semestre, te.type, te.libelle";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$evaluations = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-clipboard-list"></i> Types d'évaluations</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#evaluationModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Nouveau type
            </button>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Semestre</label>
                <select class="form-select" id="semestre_filter" name="semestre">
                    <option value="1" <?php echo $semestre_filter == '1' ? 'selected' : ''; ?>>Semestre 1</option>
                    <option value="2" <?php echo $semestre_filter == '2' ? 'selected' : ''; ?>>Semestre 2</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Type</label>
                <select class="form-select" id="type_filter" name="type">
                    <option value="">Tous les types</option>
                    <option value="devoir" <?php echo $type_filter === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                    <option value="composition" <?php echo $type_filter === 'composition' ? 'selected' : ''; ?>>Composition</option>
                    <option value="examen" <?php echo $type_filter === 'examen' ? 'selected' : ''; ?>>Examen</option>
                    <option value="tp" <?php echo $type_filter === 'tp' ? 'selected' : ''; ?>>TP</option>
                    <option value="projet" <?php echo $type_filter === 'projet' ? 'selected' : ''; ?>>Projet</option>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Libellé</th>
                        <th>Semestre</th>
                        <th>Type</th>
                        <th>Matière</th>
                        <th>Filière</th>
                        <th>Classe</th>
                        <th>Coefficient</th>
                        <th>Note sur</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evaluations as $eval): ?>
                    <tr>
                        <td><strong><?php echo escape_html($eval['libelle']); ?></strong></td>
                        <td><span class="badge bg-<?php echo $eval['semestre'] == 1 ? 'primary' : 'success'; ?>">Semestre <?php echo $eval['semestre']; ?></span></td>
                        <td><span class="badge bg-info"><?php echo ucfirst($eval['type']); ?></span></td>
                        <td><?php echo $eval['matiere'] ? '<span class="badge bg-secondary">'.escape_html($eval['matiere']).'</span>' : '<span class="text-muted">Toutes</span>'; ?></td>
                        <td><?php echo $eval['filiere'] ? escape_html($eval['filiere']) : '<span class="text-muted">Toutes</span>'; ?></td>
                        <td><?php echo $eval['classe'] ? escape_html($eval['classe']) : '<span class="text-muted">Toutes</span>'; ?></td>
                        <td><?php echo $eval['coefficient']; ?></td>
                        <td><?php echo $eval['note_sur']; ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick='editEvaluation(<?php echo json_encode($eval); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Supprimer ce type ?')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $eval['id']; ?>">
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

<!-- Modal -->
<div class="modal fade" id="evaluationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nouveau type d'évaluation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="evaluationForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="evaluation_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="libelle" class="form-label">Libellé *</label>
                        <input type="text" class="form-control" id="libelle" name="libelle" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="semestre" class="form-label">Semestre *</label>
                        <select class="form-select" id="semestre" name="semestre" required>
                            <option value="1">Semestre 1</option>
                            <option value="2">Semestre 2</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Type *</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="devoir">Devoir</option>
                            <option value="composition">Composition</option>
                            <option value="examen">Examen</option>
                            <option value="tp">Travaux Pratiques</option>
                            <option value="projet">Projet</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="matiere_id" class="form-label">Matière (optionnel)</label>
                        <select class="form-select" id="matiere_id" name="matiere_id">
                            <option value="">Toutes les matières</option>
                            <?php foreach ($matieres as $matiere): ?>
                                <option value="<?php echo $matiere['id']; ?>"><?php echo escape_html($matiere['libelle']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="filiere_id" class="form-label">Filière (optionnel)</label>
                        <select class="form-select" id="filiere_id" name="filiere_id">
                            <option value="">Toutes les filières</option>
                            <?php foreach ($filieres as $filiere): ?>
                                <option value="<?php echo $filiere['id']; ?>"><?php echo escape_html($filiere['libelle']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="classe_id" class="form-label">Classe (optionnel)</label>
                        <select class="form-select" id="classe_id" name="classe_id">
                            <option value="">Toutes les classes</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>">
                                    <?php echo escape_html($classe['filiere'] . ' - ' . $classe['niveau'] . ' - ' . $classe['libelle']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="coefficient" class="form-label">Coefficient *</label>
                            <input type="number" step="0.1" class="form-control" id="coefficient" name="coefficient" value="1.0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="note_sur" class="form-label">Note sur *</label>
                            <input type="number" step="0.01" class="form-control" id="note_sur" name="note_sur" value="20.00" required>
                        </div>
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
    document.getElementById('evaluationForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('evaluation_id').value = '';
    document.getElementById('modalTitle').textContent = 'Nouveau type d\'évaluation';
}

function editEvaluation(eval) {
    document.getElementById('action').value = 'edit';
    document.getElementById('evaluation_id').value = eval.id;
    document.getElementById('libelle').value = eval.libelle;
    document.getElementById('semestre').value = eval.semestre || '1';
    document.getElementById('type').value = eval.type;
    document.getElementById('coefficient').value = eval.coefficient;
    document.getElementById('note_sur').value = eval.note_sur;
    document.getElementById('matiere_id').value = eval.matiere_id || '';
    document.getElementById('filiere_id').value = eval.filiere_id || '';
    document.getElementById('classe_id').value = eval.classe_id || '';
    document.getElementById('modalTitle').textContent = 'Modifier le type d\'évaluation';
    
    new bootstrap.Modal(document.getElementById('evaluationModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
