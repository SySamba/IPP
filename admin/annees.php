<?php
require_once '../config/config.php';
require_role(['admin', 'scolarite']);

$page_title = 'Années académiques';
$db = Database::getInstance()->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $libelle = clean_input($_POST['libelle']);
        $date_debut = clean_input($_POST['date_debut']);
        $date_fin = clean_input($_POST['date_fin']);
        $statut = clean_input($_POST['statut']);
        
        try {
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO annees_academiques (libelle, date_debut, date_fin, statut) VALUES (?, ?, ?, ?)");
                $stmt->execute([$libelle, $date_debut, $date_fin, $statut]);
                
                log_activity('Création année académique', 'annees_academiques', $db->lastInsertId(), $libelle);
                set_flash_message('Année académique créée avec succès', 'success');
            } else {
                $stmt = $db->prepare("UPDATE annees_academiques SET libelle = ?, date_debut = ?, date_fin = ?, statut = ? WHERE id = ?");
                $stmt->execute([$libelle, $date_debut, $date_fin, $statut, $id]);
                
                log_activity('Modification année académique', 'annees_academiques', $id, $libelle);
                set_flash_message('Année académique modifiée avec succès', 'success');
            }
            
            // Si on active une année, désactiver les autres
            if ($statut === 'active') {
                $stmt = $db->prepare("UPDATE annees_academiques SET statut = 'inactive' WHERE id != ?");
                $stmt->execute([$id ?? $db->lastInsertId()]);
            }
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/annees.php');
    }
    
    if ($action === 'activate') {
        $id = $_POST['id'];
        
        try {
            $db->beginTransaction();
            
            // Désactiver toutes les années
            $stmt = $db->query("UPDATE annees_academiques SET statut = 'inactive'");
            
            // Activer l'année sélectionnée
            $stmt = $db->prepare("UPDATE annees_academiques SET statut = 'active' WHERE id = ?");
            $stmt->execute([$id]);
            
            $db->commit();
            
            log_activity('Activation année académique', 'annees_academiques', $id);
            set_flash_message('Année académique activée avec succès', 'success');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_message('Erreur lors de l\'activation', 'danger');
        }
        
        redirect('admin/annees.php');
    }
}

// Récupération des années académiques
$stmt = $db->query("SELECT a.*, COUNT(DISTINCT e.id) as nb_inscriptions FROM annees_academiques a LEFT JOIN etudiants e ON a.id = e.annee_academique_id GROUP BY a.id ORDER BY a.date_debut DESC");
$annees = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-calendar-alt"></i> Années académiques</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#anneeModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Nouvelle année
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
                        <th>Libellé</th>
                        <th>Date début</th>
                        <th>Date fin</th>
                        <th>Inscriptions</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($annees as $annee): ?>
                    <tr>
                        <td><strong><?php echo escape_html($annee['libelle']); ?></strong></td>
                        <td><?php echo format_date($annee['date_debut']); ?></td>
                        <td><?php echo format_date($annee['date_fin']); ?></td>
                        <td><span class="badge bg-info"><?php echo $annee['nb_inscriptions']; ?></span></td>
                        <td>
                            <span class="badge bg-<?php echo $annee['statut'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo $annee['statut'] === 'active' ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($annee['statut'] !== 'active'): ?>
                            <form method="POST" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="id" value="<?php echo $annee['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success" title="Activer">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <button class="btn btn-sm btn-info" onclick="editAnnee(<?php echo htmlspecialchars(json_encode($annee)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <a href="<?php echo BASE_URL; ?>/admin/periodes.php?annee_id=<?php echo $annee['id']; ?>" class="btn btn-sm btn-primary" title="Gérer les périodes">
                                <i class="fas fa-calendar"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Année -->
<div class="modal fade" id="anneeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nouvelle année académique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="anneeForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="annee_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="libelle" class="form-label">Libellé *</label>
                        <input type="text" class="form-control" id="libelle" name="libelle" placeholder="Ex: 2024-2025" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_debut" class="form-label">Date de début *</label>
                        <input type="date" class="form-control" id="date_debut" name="date_debut" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_fin" class="form-label">Date de fin *</label>
                        <input type="date" class="form-control" id="date_fin" name="date_fin" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="inactive">Inactive</option>
                            <option value="active">Active</option>
                        </select>
                        <small class="text-muted">Une seule année peut être active à la fois</small>
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
    document.getElementById('anneeForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('annee_id').value = '';
    document.getElementById('modalTitle').textContent = 'Nouvelle année académique';
}

function editAnnee(annee) {
    document.getElementById('action').value = 'edit';
    document.getElementById('annee_id').value = annee.id;
    document.getElementById('libelle').value = annee.libelle;
    document.getElementById('date_debut').value = annee.date_debut;
    document.getElementById('date_fin').value = annee.date_fin;
    document.getElementById('statut').value = annee.statut;
    document.getElementById('modalTitle').textContent = 'Modifier l\'année académique';
    
    new bootstrap.Modal(document.getElementById('anneeModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
