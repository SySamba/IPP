<?php
require_once '../config/config.php';
require_role('admin');

$page_title = 'Gestion des enseignants';
$db = Database::getInstance()->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $username = clean_input($_POST['username']);
        $email = clean_input($_POST['email']);
        $password = $_POST['password'] ?? '';
        $nom = clean_input($_POST['nom']);
        $prenom = clean_input($_POST['prenom']);
        $telephone = clean_input($_POST['telephone']);
        $specialite = clean_input($_POST['specialite']);
        $statut = clean_input($_POST['statut']);
        $classe_matieres = $_POST['classe_matieres'] ?? [];
        
        try {
            $db->beginTransaction();
            
            if ($action === 'add') {
                if (empty($password)) {
                    throw new Exception('Le mot de passe est requis');
                }
                
                $hashed_password = hash_password($password);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role, nom, prenom, telephone, statut) VALUES (?, ?, ?, 'enseignant', ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $nom, $prenom, $telephone, $statut]);
                $enseignant_id = $db->lastInsertId();
                
                log_activity('Création enseignant', 'users', $enseignant_id, $username);
            } else {
                $sql = "UPDATE users SET username = ?, email = ?, nom = ?, prenom = ?, telephone = ?, statut = ?";
                $params = [$username, $email, $nom, $prenom, $telephone, $statut];
                
                if (!empty($password)) {
                    $sql .= ", password = ?";
                    $params[] = hash_password($password);
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $id;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $enseignant_id = $id;
                
                // Supprimer les anciennes assignations
                $stmt = $db->prepare("UPDATE classe_matieres SET enseignant_id = NULL WHERE enseignant_id = ?");
                $stmt->execute([$enseignant_id]);
                
                log_activity('Modification enseignant', 'users', $id, $username);
            }
            
            // Assigner les classes-matières
            if (!empty($classe_matieres)) {
                $stmt = $db->prepare("UPDATE classe_matieres SET enseignant_id = ? WHERE id = ?");
                foreach ($classe_matieres as $cm_id) {
                    $stmt->execute([$enseignant_id, $cm_id]);
                }
            }
            
            $db->commit();
            set_flash_message('Enseignant ' . ($action === 'add' ? 'créé' : 'modifié') . ' avec succès', 'success');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/enseignants.php');
    }
}

// Récupération des classes-matières disponibles
$classes_matieres = $db->query("SELECT cm.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau, m.libelle as matiere 
                                 FROM classe_matieres cm 
                                 JOIN classes c ON cm.classe_id = c.id 
                                 JOIN filieres f ON c.filiere_id = f.id 
                                 JOIN niveaux n ON c.niveau_id = n.id 
                                 JOIN matieres m ON cm.matiere_id = m.id 
                                 ORDER BY f.libelle, n.ordre, c.libelle, m.libelle")->fetchAll();

// Récupération des enseignants
$search = $_GET['search'] ?? '';
$statut_filter = $_GET['statut'] ?? '';

$sql = "SELECT u.*, COUNT(DISTINCT cm.classe_id) as nb_classes
        FROM users u
        LEFT JOIN classe_matieres cm ON u.id = cm.enseignant_id
        WHERE u.role = 'enseignant'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_fill(0, 3, $search_param);
}

if (!empty($statut_filter)) {
    $sql .= " AND u.statut = ?";
    $params[] = $statut_filter;
}

$sql .= " GROUP BY u.id ORDER BY u.nom, u.prenom";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$enseignants = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-chalkboard-teacher"></i> Gestion des enseignants</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enseignantModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Nouvel enseignant
            </button>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-8">
                <input type="text" class="form-control" name="search" placeholder="Rechercher par nom, prénom ou email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <select class="form-select" id="statut_filter" name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?php echo $statut_filter === 'actif' ? 'selected' : ''; ?>>Actif</option>
                    <option value="inactif" <?php echo $statut_filter === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Liste des enseignants -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nom complet</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Classes assignées</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enseignants as $enseignant): 
                        // Récupérer les classes assignées
                        $stmt = $db->prepare("SELECT DISTINCT c.libelle, f.libelle as filiere, n.libelle as niveau 
                                              FROM classe_matieres cm 
                                              JOIN classes c ON cm.classe_id = c.id 
                                              JOIN filieres f ON c.filiere_id = f.id 
                                              JOIN niveaux n ON c.niveau_id = n.id 
                                              WHERE cm.enseignant_id = ?
                                              ORDER BY f.libelle, n.ordre");
                        $stmt->execute([$enseignant['id']]);
                        $classes_assignees = $stmt->fetchAll();
                    ?>
                    <tr>
                        <td><strong><?php echo escape_html($enseignant['prenom'] . ' ' . $enseignant['nom']); ?></strong></td>
                        <td><?php echo escape_html($enseignant['email']); ?></td>
                        <td><?php echo escape_html($enseignant['telephone']); ?></td>
                        <td>
                            <?php if (empty($classes_assignees)): ?>
                                <span class="text-muted">Aucune classe</span>
                            <?php else: ?>
                                <?php foreach ($classes_assignees as $classe): ?>
                                    <span class="badge bg-info mb-1">
                                        <?php echo escape_html($classe['filiere'] . ' - ' . $classe['niveau'] . ' - ' . $classe['libelle']); ?>
                                    </span><br>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $enseignant['statut'] === 'actif' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($enseignant['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="editEnseignant(<?php echo htmlspecialchars(json_encode($enseignant)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Enseignant -->
<div class="modal fade" id="enseignantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nouvel enseignant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="enseignantForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="enseignant_id">
                
                <div class="modal-body">
                    <h6 class="mb-3"><i class="fas fa-user"></i> Informations de connexion</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="password" class="form-label">Mot de passe <span id="password_required">*</span></label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="text-muted">Laisser vide pour ne pas modifier</small>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3"><i class="fas fa-id-card"></i> Informations personnelles</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="nom" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="prenom" class="form-label">Prénom *</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="text" class="form-control" id="telephone" name="telephone">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="specialite" class="form-label">Spécialité</label>
                            <input type="text" class="form-control" id="specialite" name="specialite" placeholder="Ex: Mathématiques, Informatique...">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="statut" class="form-label">Statut *</label>
                            <select class="form-select" id="statut" name="statut" required>
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                    </div>
                    
                    <hr id="classes_section" style="display:none;">
                    <h6 class="mb-3" id="classes_title" style="display:none;"><i class="fas fa-chalkboard"></i> Classes assignées</h6>
                    <div id="classes_list" style="max-height: 300px; overflow-y: auto; display:none;">
                        <?php 
                        $current_filiere = '';
                        foreach ($classes_matieres as $cm): 
                            if ($current_filiere !== $cm['filiere']) {
                                if ($current_filiere !== '') echo '</div>';
                                $current_filiere = $cm['filiere'];
                                echo '<div class="mb-3"><h6 class="text-primary">' . escape_html($current_filiere) . '</h6>';
                            }
                        ?>
                            <div class="form-check">
                                <input class="form-check-input classe-matiere-checkbox" type="checkbox" name="classe_matieres[]" 
                                       value="<?php echo $cm['id']; ?>" id="cm_<?php echo $cm['id']; ?>">
                                <label class="form-check-label" for="cm_<?php echo $cm['id']; ?>">
                                    <?php echo escape_html($cm['niveau'] . ' - ' . $cm['classe'] . ' - ' . $cm['matiere']); ?>
                                </label>
                            </div>
                        <?php 
                        endforeach; 
                        if ($current_filiere !== '') echo '</div>';
                        ?>
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
    document.getElementById('enseignantForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('enseignant_id').value = '';
    document.getElementById('modalTitle').textContent = 'Nouvel enseignant';
    document.getElementById('password').required = true;
    document.getElementById('password_required').style.display = 'inline';
    document.getElementById('classes_section').style.display = 'none';
    document.getElementById('classes_title').style.display = 'none';
    document.getElementById('classes_list').style.display = 'none';
    document.querySelectorAll('.classe-matiere-checkbox').forEach(cb => cb.checked = false);
}

function editEnseignant(enseignant) {
    document.getElementById('action').value = 'edit';
    document.getElementById('enseignant_id').value = enseignant.id;
    document.getElementById('username').value = enseignant.username;
    document.getElementById('email').value = enseignant.email;
    document.getElementById('nom').value = enseignant.nom;
    document.getElementById('prenom').value = enseignant.prenom;
    document.getElementById('telephone').value = enseignant.telephone || '';
    document.getElementById('specialite').value = enseignant.specialite || '';
    document.getElementById('statut').value = enseignant.statut;
    document.getElementById('password').required = false;
    document.getElementById('password_required').style.display = 'none';
    document.getElementById('modalTitle').textContent = 'Modifier l\'enseignant';
    
    // Afficher la section des classes
    document.getElementById('classes_section').style.display = 'block';
    document.getElementById('classes_title').style.display = 'block';
    document.getElementById('classes_list').style.display = 'block';
    
    // Décocher toutes les cases
    document.querySelectorAll('.classe-matiere-checkbox').forEach(cb => cb.checked = false);
    
    // Charger les classes assignées
    fetch('<?php echo BASE_URL; ?>/api/get-enseignant-classes.php?id=' + enseignant.id)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.classe_matieres) {
                data.classe_matieres.forEach(cm_id => {
                    const checkbox = document.getElementById('cm_' + cm_id);
                    if (checkbox) checkbox.checked = true;
                });
            }
        });
    
    new bootstrap.Modal(document.getElementById('enseignantModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
