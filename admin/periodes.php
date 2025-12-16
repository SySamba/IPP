<?php
require_once '../config/config.php';
require_role(['admin', 'scolarite']);

$page_title = 'Gestion des périodes';
$db = Database::getInstance()->getConnection();

$annee_id = intval($_GET['annee_id'] ?? 0);

// Récupération de l'année académique
$stmt = $db->prepare("SELECT * FROM annees_academiques WHERE id = ?");
$stmt->execute([$annee_id]);
$annee = $stmt->fetch();

if (!$annee) {
    set_flash_message('Année académique introuvable', 'danger');
    redirect('admin/annees.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $libelle = clean_input($_POST['libelle']);
        $numero = intval($_POST['numero']);
        $date_debut = clean_input($_POST['date_debut']);
        $date_fin = clean_input($_POST['date_fin']);
        
        try {
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO periodes (annee_academique_id, libelle, numero, date_debut, date_fin) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$annee_id, $libelle, $numero, $date_debut, $date_fin]);
                
                log_activity('Création période', 'periodes', $db->lastInsertId(), $libelle);
                set_flash_message('Période créée avec succès', 'success');
            } else {
                $stmt = $db->prepare("UPDATE periodes SET libelle = ?, numero = ?, date_debut = ?, date_fin = ? WHERE id = ?");
                $stmt->execute([$libelle, $numero, $date_debut, $date_fin, $id]);
                
                log_activity('Modification période', 'periodes', $id, $libelle);
                set_flash_message('Période modifiée avec succès', 'success');
            }
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/periodes.php?annee_id=' . $annee_id);
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM periodes WHERE id = ?");
            $stmt->execute([$id]);
            
            log_activity('Suppression période', 'periodes', $id);
            set_flash_message('Période supprimée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Impossible de supprimer cette période (données liées)', 'danger');
        }
        
        redirect('admin/periodes.php?annee_id=' . $annee_id);
    }
}

// Récupération des périodes
$stmt = $db->prepare("SELECT * FROM periodes WHERE annee_academique_id = ? ORDER BY numero");
$stmt->execute([$annee_id]);
$periodes = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/annees.php">Années académiques</a></li>
                <li class="breadcrumb-item active"><?php echo escape_html($annee['libelle']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-calendar"></i> Périodes - <?php echo escape_html($annee['libelle']); ?></h2>
                <p class="text-muted mb-0">
                    <?php echo format_date($annee['date_debut']); ?> - <?php echo format_date($annee['date_fin']); ?>
                </p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#periodeModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Nouvelle période
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($periodes)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Aucune période créée pour cette année académique.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Numéro</th>
                            <th>Libellé</th>
                            <th>Date début</th>
                            <th>Date fin</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periodes as $periode): ?>
                        <tr>
                            <td><span class="badge bg-primary"><?php echo $periode['numero']; ?></span></td>
                            <td><strong><?php echo escape_html($periode['libelle']); ?></strong></td>
                            <td><?php echo format_date($periode['date_debut']); ?></td>
                            <td><?php echo format_date($periode['date_fin']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="editPeriode(<?php echo htmlspecialchars(json_encode($periode)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Supprimer cette période ?')">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $periode['id']; ?>">
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

<!-- Modal Période -->
<div class="modal fade" id="periodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nouvelle période</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="periodeForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="periode_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="libelle" class="form-label">Libellé *</label>
                        <input type="text" class="form-control" id="libelle" name="libelle" 
                               placeholder="Ex: Semestre 1, Trimestre 1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="numero" class="form-label">Numéro *</label>
                        <input type="number" class="form-control" id="numero" name="numero" 
                               min="1" value="1" required>
                        <small class="text-muted">Ordre d'affichage</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_debut" class="form-label">Date de début *</label>
                        <input type="date" class="form-control" id="date_debut" name="date_debut" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_fin" class="form-label">Date de fin *</label>
                        <input type="date" class="form-control" id="date_fin" name="date_fin" required>
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
    document.getElementById('periodeForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('periode_id').value = '';
    document.getElementById('modalTitle').textContent = 'Nouvelle période';
}

function editPeriode(periode) {
    document.getElementById('action').value = 'edit';
    document.getElementById('periode_id').value = periode.id;
    document.getElementById('libelle').value = periode.libelle;
    document.getElementById('numero').value = periode.numero;
    document.getElementById('date_debut').value = periode.date_debut;
    document.getElementById('date_fin').value = periode.date_fin;
    document.getElementById('modalTitle').textContent = 'Modifier la période';
    
    new bootstrap.Modal(document.getElementById('periodeModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
