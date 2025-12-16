<?php
require_once '../config/config.php';
require_role('admin');

$page_title = 'Gestion des salles';
$db = Database::getInstance()->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $code = clean_input($_POST['code']);
        $libelle = clean_input($_POST['libelle']);
        $capacite = intval($_POST['capacite']);
        $type = clean_input($_POST['type']);
        $equipements = clean_input($_POST['equipements']);
        $statut = clean_input($_POST['statut']);
        
        try {
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO salles (code, libelle, capacite, type, batiment, statut) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $libelle, $capacite, $type, isset($_POST['batiment']) ? clean_input($_POST['batiment']) : null, $statut]);
                
                log_activity('Création salle', 'salles', $db->lastInsertId(), $libelle);
                set_flash_message('Salle créée avec succès', 'success');
            } else {
                $stmt = $db->prepare("UPDATE salles SET code = ?, libelle = ?, capacite = ?, type = ?, batiment = ?, statut = ? WHERE id = ?");
                $stmt->execute([$code, $libelle, $capacite, $type, isset($_POST['batiment']) ? clean_input($_POST['batiment']) : null, $statut, $id]);
                
                log_activity('Modification salle', 'salles', $id, $libelle);
                set_flash_message('Salle modifiée avec succès', 'success');
            }
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/salles.php');
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM salles WHERE id = ?");
            $stmt->execute([$id]);
            
            log_activity('Suppression salle', 'salles', $id);
            set_flash_message('Salle supprimée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Impossible de supprimer cette salle (données liées)', 'danger');
        }
        
        redirect('admin/salles.php');
    }
}

// Vérifier et créer la table salles si nécessaire
try {
    $db->query("SELECT 1 FROM salles LIMIT 1");
} catch (Exception $e) {
    // Créer la table salles
    $db->exec("CREATE TABLE IF NOT EXISTS salles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        libelle VARCHAR(100) NOT NULL,
        capacite INT DEFAULT 30,
        type ENUM('cours', 'tp', 'amphi', 'labo') DEFAULT 'cours',
        batiment VARCHAR(50),
        statut ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Récupération des salles
try {
    $stmt = $db->query("SELECT * FROM salles ORDER BY libelle");
    $salles = $stmt->fetchAll();
} catch (Exception $e) {
    $salles = [];
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-door-open"></i> Gestion des salles</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#salleModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Nouvelle salle
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
                        <th>Capacité</th>
                        <th>Type</th>
                        <th>Équipements</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salles as $salle): ?>
                    <tr>
                        <td><strong><?php echo escape_html($salle['code']); ?></strong></td>
                        <td><?php echo escape_html($salle['libelle']); ?></td>
                        <td><span class="badge bg-info"><?php echo $salle['capacite']; ?> places</span></td>
                        <td><?php echo escape_html($salle['type']); ?></td>
                        <td><?php echo escape_html($salle['equipements']); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $salle['statut'] === 'disponible' ? 'success' : 
                                    ($salle['statut'] === 'maintenance' ? 'warning' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($salle['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="editSalle(<?php echo htmlspecialchars(json_encode($salle)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Supprimer cette salle ?')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $salle['id']; ?>">
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

<!-- Modal Salle -->
<div class="modal fade" id="salleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nouvelle salle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="salleForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="salle_id">
                
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
                        <label for="capacite" class="form-label">Capacité *</label>
                        <input type="number" class="form-control" id="capacite" name="capacite" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">Sélectionner...</option>
                            <option value="Amphithéâtre">Amphithéâtre</option>
                            <option value="Salle de cours">Salle de cours</option>
                            <option value="Laboratoire">Laboratoire</option>
                            <option value="Salle informatique">Salle informatique</option>
                            <option value="Atelier">Atelier</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="equipements" class="form-label">Équipements</label>
                        <textarea class="form-control" id="equipements" name="equipements" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="disponible">Disponible</option>
                            <option value="indisponible">Indisponible</option>
                            <option value="maintenance">En maintenance</option>
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
    document.getElementById('salleForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('salle_id').value = '';
    document.getElementById('modalTitle').textContent = 'Nouvelle salle';
}

function editSalle(salle) {
    document.getElementById('action').value = 'edit';
    document.getElementById('salle_id').value = salle.id;
    document.getElementById('code').value = salle.code;
    document.getElementById('libelle').value = salle.libelle;
    document.getElementById('capacite').value = salle.capacite;
    document.getElementById('type').value = salle.type || '';
    document.getElementById('equipements').value = salle.equipements || '';
    document.getElementById('statut').value = salle.statut;
    document.getElementById('modalTitle').textContent = 'Modifier la salle';
    
    new bootstrap.Modal(document.getElementById('salleModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
