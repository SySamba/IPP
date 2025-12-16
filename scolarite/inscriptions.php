<?php
require_once '../config/config.php';
require_role(['admin', 'scolarite']);

$page_title = 'Gestion des inscriptions';
$db = Database::getInstance()->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $etudiant_id = intval($_POST['etudiant_id']);
        $classe_id = intval($_POST['classe_id']);
        $annee_academique_id = intval($_POST['annee_academique_id']);
        $type_inscription = clean_input($_POST['type_inscription']);
        $date_inscription = clean_input($_POST['date_inscription']);
        
        try {
            $stmt = $db->prepare("INSERT INTO inscriptions (etudiant_id, classe_id, annee_academique_id, type_inscription, date_inscription, statut) VALUES (?, ?, ?, ?, ?, 'validee')");
            $stmt->execute([$etudiant_id, $classe_id, $annee_academique_id, $type_inscription, $date_inscription]);
            
            log_activity('Inscription étudiant', 'inscriptions', $db->lastInsertId());
            set_flash_message('Inscription enregistrée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Erreur: Cet étudiant est déjà inscrit pour cette année', 'danger');
        }
        
        redirect('scolarite/inscriptions.php');
    }
    
    if ($action === 'update_statut') {
        $id = intval($_POST['id']);
        $statut = clean_input($_POST['statut']);
        
        try {
            $stmt = $db->prepare("UPDATE inscriptions SET statut = ? WHERE id = ?");
            $stmt->execute([$statut, $id]);
            
            log_activity('Modification statut inscription', 'inscriptions', $id);
            set_flash_message('Statut modifié avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Erreur lors de la modification', 'danger');
        }
        
        redirect('scolarite/inscriptions.php');
    }
}

// Récupération des inscriptions
$annee_filter = $_GET['annee'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
$statut_filter = $_GET['statut'] ?? '';

$sql = "SELECT i.*, e.matricule, u.nom, u.prenom, c.libelle as classe, aa.libelle as annee
        FROM inscriptions i
        JOIN etudiants e ON i.etudiant_id = e.id
        JOIN users u ON e.user_id = u.id
        JOIN classes c ON i.classe_id = c.id
        JOIN annees_academiques aa ON i.annee_academique_id = aa.id
        WHERE 1=1";
$params = [];

if (!empty($annee_filter)) {
    $sql .= " AND i.annee_academique_id = ?";
    $params[] = $annee_filter;
}

if (!empty($classe_filter)) {
    $sql .= " AND i.classe_id = ?";
    $params[] = $classe_filter;
}

if (!empty($statut_filter)) {
    $sql .= " AND i.statut = ?";
    $params[] = $statut_filter;
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$inscriptions = $stmt->fetchAll();

// Données pour les formulaires
$etudiants = $db->query("SELECT e.id, e.matricule, u.nom, u.prenom FROM etudiants e JOIN users u ON e.user_id = u.id WHERE e.statut = 'actif' ORDER BY u.nom, u.prenom")->fetchAll();
$classes = $db->query("SELECT * FROM classes WHERE statut = 'active' ORDER BY libelle")->fetchAll();
$annees = $db->query("SELECT * FROM annees_academiques ORDER BY date_debut DESC")->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-user-plus"></i> Gestion des inscriptions</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#inscriptionModal">
                <i class="fas fa-plus"></i> Nouvelle inscription
            </button>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <select class="form-select" name="annee">
                    <option value="">Toutes les années</option>
                    <?php foreach ($annees as $annee): ?>
                        <option value="<?php echo $annee['id']; ?>" <?php echo $annee_filter == $annee['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($annee['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($classe['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="en_cours" <?php echo $statut_filter === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                    <option value="validee" <?php echo $statut_filter === 'validee' ? 'selected' : ''; ?>>Validée</option>
                    <option value="annulee" <?php echo $statut_filter === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Filtrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des inscriptions -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Étudiant</th>
                        <th>Classe</th>
                        <th>Année académique</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inscriptions as $inscription): ?>
                    <tr>
                        <td><strong><?php echo escape_html($inscription['matricule']); ?></strong></td>
                        <td><?php echo escape_html($inscription['prenom'] . ' ' . $inscription['nom']); ?></td>
                        <td><?php echo escape_html($inscription['classe']); ?></td>
                        <td><?php echo escape_html($inscription['annee']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $inscription['type_inscription'] === 'nouvelle' ? 'primary' : 'info'; ?>">
                                <?php echo ucfirst($inscription['type_inscription']); ?>
                            </span>
                        </td>
                        <td><?php echo format_date($inscription['date_inscription']); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $inscription['statut'] === 'validee' ? 'success' : 
                                    ($inscription['statut'] === 'annulee' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($inscription['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($inscription['statut'] !== 'annulee'): ?>
                            <button class="btn btn-sm btn-warning" onclick="updateStatut(<?php echo $inscription['id']; ?>, 'annulee')">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Inscription -->
<div class="modal fade" id="inscriptionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle inscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="etudiant_id" class="form-label">Étudiant *</label>
                        <select class="form-select" id="etudiant_id" name="etudiant_id" required>
                            <option value="">Sélectionner un étudiant...</option>
                            <?php foreach ($etudiants as $etudiant): ?>
                                <option value="<?php echo $etudiant['id']; ?>">
                                    <?php echo escape_html($etudiant['matricule'] . ' - ' . $etudiant['prenom'] . ' ' . $etudiant['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classe_id" class="form-label">Classe *</label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php echo escape_html($classe['libelle']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="annee_academique_id" class="form-label">Année académique *</label>
                            <select class="form-select" id="annee_academique_id" name="annee_academique_id" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($annees as $annee): ?>
                                    <option value="<?php echo $annee['id']; ?>" <?php echo $annee['statut'] === 'active' ? 'selected' : ''; ?>>
                                        <?php echo escape_html($annee['libelle']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type_inscription" class="form-label">Type d'inscription *</label>
                            <select class="form-select" id="type_inscription" name="type_inscription" required>
                                <option value="nouvelle">Nouvelle inscription</option>
                                <option value="reinscription">Réinscription</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="date_inscription" class="form-label">Date d'inscription *</label>
                            <input type="date" class="form-control" id="date_inscription" name="date_inscription" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
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

<!-- Form caché pour mise à jour statut -->
<form method="POST" id="updateStatutForm" style="display:none;">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="update_statut">
    <input type="hidden" name="id" id="update_id">
    <input type="hidden" name="statut" id="update_statut">
</form>

<script>
function updateStatut(id, statut) {
    if (confirm('Êtes-vous sûr de vouloir annuler cette inscription ?')) {
        document.getElementById('update_id').value = id;
        document.getElementById('update_statut').value = statut;
        document.getElementById('updateStatutForm').submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
