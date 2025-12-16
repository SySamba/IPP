<?php
require_once '../config/config.php';
require_role('admin');

$page_title = 'Gestion des classes';
$db = Database::getInstance()->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $libelle = clean_input($_POST['libelle']);
        $filiere_id = intval($_POST['filiere_id']);
        $niveau_id = intval($_POST['niveau_id']);
        $annee_academique_id = intval($_POST['annee_academique_id']);
        $effectif_max = intval($_POST['effectif_max']);
        $statut = clean_input($_POST['statut']);
        
        // Générer le code automatiquement
        $stmt = $db->prepare("SELECT f.code as f_code, n.code as n_code FROM filieres f, niveaux n WHERE f.id = ? AND n.id = ?");
        $stmt->execute([$filiere_id, $niveau_id]);
        $codes = $stmt->fetch();
        $code = $codes['f_code'] . '-' . $codes['n_code'] . '-' . substr($libelle, 0, 3);
        
        try {
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO classes (code, libelle, filiere_id, niveau_id, annee_academique_id, effectif_max, statut) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $libelle, $filiere_id, $niveau_id, $annee_academique_id, $effectif_max, $statut]);
                
                log_activity('Création classe', 'classes', $db->lastInsertId(), $libelle);
                set_flash_message('Classe créée avec succès', 'success');
            } else {
                $stmt = $db->prepare("UPDATE classes SET code = ?, libelle = ?, filiere_id = ?, niveau_id = ?, annee_academique_id = ?, effectif_max = ?, statut = ? WHERE id = ?");
                $stmt->execute([$code, $libelle, $filiere_id, $niveau_id, $annee_academique_id, $effectif_max, $statut, $id]);
                
                log_activity('Modification classe', 'classes', $id, $libelle);
                set_flash_message('Classe modifiée avec succès', 'success');
            }
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/classes.php');
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM classes WHERE id = ?");
            $stmt->execute([$id]);
            
            log_activity('Suppression classe', 'classes', $id);
            set_flash_message('Classe supprimée avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Impossible de supprimer cette classe (données liées)', 'danger');
        }
        
        redirect('admin/classes.php');
    }
}

// Filtres
$filiere_filter = $_GET['filiere_id'] ?? '';
$niveau_filter = $_GET['niveau_id'] ?? '';
$statut_filter = $_GET['statut'] ?? '';

// Récupération des classes avec informations
$sql = "SELECT c.*, f.libelle as filiere, n.libelle as niveau, aa.libelle as annee, 
        COUNT(DISTINCT e.id) as nb_etudiants,
        GROUP_CONCAT(DISTINCT CONCAT(u.prenom, ' ', u.nom) SEPARATOR ', ') as enseignants
        FROM classes c
        JOIN filieres f ON c.filiere_id = f.id
        JOIN niveaux n ON c.niveau_id = n.id
        JOIN annees_academiques aa ON c.annee_academique_id = aa.id
        LEFT JOIN etudiants e ON c.id = e.classe_id AND e.annee_academique_id = c.annee_academique_id AND e.statut = 'actif'
        LEFT JOIN classe_matieres cm ON c.id = cm.classe_id
        LEFT JOIN users u ON cm.enseignant_id = u.id AND u.role = 'enseignant'
        WHERE 1=1";
$params = [];

if (!empty($filiere_filter)) {
    $sql .= " AND f.id = ?";
    $params[] = $filiere_filter;
}

if (!empty($niveau_filter)) {
    $sql .= " AND n.id = ?";
    $params[] = $niveau_filter;
}

if (!empty($statut_filter)) {
    $sql .= " AND c.statut = ?";
    $params[] = $statut_filter;
}

$sql .= " GROUP BY c.id ORDER BY aa.date_debut DESC, f.libelle, n.ordre";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$classes = $stmt->fetchAll();

// Récupération des données pour les formulaires
$filieres = $db->query("SELECT * FROM filieres WHERE statut = 'active' ORDER BY libelle")->fetchAll();
$niveaux = $db->query("SELECT * FROM niveaux WHERE statut = 'actif' ORDER BY ordre")->fetchAll();
$annees = $db->query("SELECT * FROM annees_academiques ORDER BY date_debut DESC")->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-school"></i> Gestion des classes</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#classeModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Nouvelle classe
            </button>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <select class="form-select" id="filiere_filter" name="filiere_id">
                    <option value="">Toutes les filières</option>
                    <?php foreach ($filieres as $filiere): ?>
                        <option value="<?php echo $filiere['id']; ?>" <?php echo $filiere_filter == $filiere['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($filiere['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select class="form-select" id="niveau_filter" name="niveau_id">
                    <option value="">Tous les niveaux</option>
                    <?php foreach ($niveaux as $niveau): ?>
                        <option value="<?php echo $niveau['id']; ?>" <?php echo $niveau_filter == $niveau['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($niveau['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select class="form-select" id="statut_filter" name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="active" <?php echo $statut_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statut_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
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
                        <th>Classe</th>
                        <th>Filière</th>
                        <th>Niveau</th>
                        <th>Année</th>
                        <th>Étudiants</th>
                        <th>Enseignants</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $classe): ?>
                    <tr>
                        <td><strong><?php echo escape_html($classe['libelle']); ?></strong></td>
                        <td><?php echo escape_html($classe['filiere']); ?></td>
                        <td><?php echo escape_html($classe['niveau']); ?></td>
                        <td><?php echo escape_html($classe['annee']); ?></td>
                        <td>
                            <span class="badge bg-info">
                                <?php echo $classe['nb_etudiants']; ?> / <?php echo $classe['effectif_max']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($classe['enseignants'])): ?>
                                <small><?php echo escape_html($classe['enseignants']); ?></small>
                            <?php else: ?>
                                <span class="text-muted"><small>Aucun enseignant</small></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $classe['statut'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($classe['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/admin/classe-matieres.php?classe_id=<?php echo $classe['id']; ?>" 
                               class="btn btn-sm btn-primary" title="Gérer les matières">
                                <i class="fas fa-book"></i>
                            </a>
                            <button class="btn btn-sm btn-info" onclick="editClasse(<?php echo htmlspecialchars(json_encode($classe)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Supprimer cette classe ?')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $classe['id']; ?>">
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

<!-- Modal Classe -->
<div class="modal fade" id="classeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nouvelle classe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="classeForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="classe_id">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="libelle" class="form-label">Nom de la classe *</label>
                            <input type="text" class="form-control" id="libelle" name="libelle" placeholder="Ex: Classe A, Groupe 1..." required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="filiere_id" class="form-label">Filière *</label>
                            <select class="form-select" id="filiere_id" name="filiere_id" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($filieres as $filiere): ?>
                                    <option value="<?php echo $filiere['id']; ?>">
                                        <?php echo escape_html($filiere['libelle']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="niveau_id" class="form-label">Niveau *</label>
                            <select class="form-select" id="niveau_id" name="niveau_id" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($niveaux as $niveau): ?>
                                    <option value="<?php echo $niveau['id']; ?>">
                                        <?php echo escape_html($niveau['libelle']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
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
                        
                        <div class="col-md-6 mb-3">
                            <label for="effectif_max" class="form-label">Effectif maximum *</label>
                            <input type="number" class="form-control" id="effectif_max" name="effectif_max" value="50" required>
                        </div>
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
    document.getElementById('classeForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('classe_id').value = '';
    document.getElementById('modalTitle').textContent = 'Nouvelle classe';
}

function editClasse(classe) {
    document.getElementById('action').value = 'edit';
    document.getElementById('classe_id').value = classe.id;
    document.getElementById('libelle').value = classe.libelle;
    document.getElementById('filiere_id').value = classe.filiere_id;
    document.getElementById('niveau_id').value = classe.niveau_id;
    document.getElementById('annee_academique_id').value = classe.annee_academique_id;
    document.getElementById('effectif_max').value = classe.effectif_max;
    document.getElementById('statut').value = classe.statut;
    document.getElementById('modalTitle').textContent = 'Modifier la classe';
    
    new bootstrap.Modal(document.getElementById('classeModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
