<?php
require_once '../config/config.php';
require_role('admin');

$page_title = 'Gestion des utilisateurs';
$db = Database::getInstance()->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $username = clean_input($_POST['username']);
        $email = clean_input($_POST['email']);
        $nom = clean_input($_POST['nom']);
        $prenom = clean_input($_POST['prenom']);
        $telephone = clean_input($_POST['telephone']);
        $role = clean_input($_POST['role']);
        $statut = clean_input($_POST['statut']);
        $password = $_POST['password'] ?? '';
        
        try {
            if ($action === 'add') {
                if (empty($password)) {
                    throw new Exception('Le mot de passe est requis');
                }
                
                $hashed_password = hash_password($password);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role, nom, prenom, telephone, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $role, $nom, $prenom, $telephone, $statut]);
                $user_id = $db->lastInsertId();
                
                // Si c'est un étudiant, créer aussi l'enregistrement dans la table etudiants
                if ($role === 'etudiant' && !empty($_POST['matricule']) && !empty($_POST['classe_id'])) {
                    $matricule = clean_input($_POST['matricule']);
                    $classe_id = intval($_POST['classe_id']);
                    $date_naissance = clean_input($_POST['date_naissance']);
                    $sexe = clean_input($_POST['sexe']);
                    
                    // Récupérer l'année académique active
                    $stmt_annee = $db->query("SELECT id FROM annees_academiques WHERE statut = 'active' LIMIT 1");
                    $annee = $stmt_annee->fetch();
                    $annee_id = $annee ? $annee['id'] : null;
                    
                    if ($annee_id) {
                        $stmt_etudiant = $db->prepare("INSERT INTO etudiants (user_id, matricule, classe_id, annee_academique_id, date_naissance, sexe, statut) VALUES (?, ?, ?, ?, ?, ?, 'actif')");
                        $stmt_etudiant->execute([$user_id, $matricule, $classe_id, $annee_id, $date_naissance, $sexe]);
                    }
                }
                
                log_activity('Création utilisateur', 'users', $user_id, $username);
                set_flash_message('Utilisateur créé avec succès', 'success');
            } else {
                $sql = "UPDATE users SET username = ?, email = ?, role = ?, nom = ?, prenom = ?, telephone = ?, statut = ?";
                $params = [$username, $email, $role, $nom, $prenom, $telephone, $statut];
                
                if (!empty($password)) {
                    $sql .= ", password = ?";
                    $params[] = hash_password($password);
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $id;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                log_activity('Modification utilisateur', 'users', $id, $username);
                set_flash_message('Utilisateur modifié avec succès', 'success');
            }
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/users.php');
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND id != ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            
            log_activity('Suppression utilisateur', 'users', $id);
            set_flash_message('Utilisateur supprimé avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Erreur lors de la suppression', 'danger');
        }
        
        redirect('admin/users.php');
    }
}

// Récupérer les classes pour le formulaire
$classes = $db->query("SELECT c.*, f.libelle as filiere, n.libelle as niveau FROM classes c JOIN filieres f ON c.filiere_id = f.id JOIN niveaux n ON c.niveau_id = n.id WHERE c.statut = 'active' ORDER BY f.libelle, n.ordre")->fetchAll();

// Récupération des utilisateurs avec pagination
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$statut_filter = $_GET['statut'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_fill(0, 4, $search_param);
}

if (!empty($role_filter)) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
}

if (!empty($statut_filter)) {
    $sql .= " AND statut = ?";
    $params[] = $statut_filter;
}

// Compter le total
$count_sql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
$count_params = [];
if (!empty($search)) {
    $count_sql .= " AND (username LIKE ? OR nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
    $count_params = array_fill(0, 4, $search_param);
}
if (!empty($role_filter)) {
    $count_sql .= " AND role = ?";
    $count_params[] = $role_filter;
}
if (!empty($statut_filter)) {
    $count_sql .= " AND statut = ?";
    $count_params[] = $statut_filter;
}

$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($count_params);
$total_users = $count_stmt->fetch()['total'];
$total_pages = ceil($total_users / $per_page);

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users"></i> Gestion des utilisateurs</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Nouvel utilisateur
            </button>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <input type="text" class="form-control" name="search" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" id="role_filter" name="role">
                    <option value="">Tous les rôles</option>
                    <?php foreach (ROLES as $key => $value): ?>
                        <option value="<?php echo $key; ?>" <?php echo $role_filter === $key ? 'selected' : ''; ?>>
                            <?php echo $value; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="statut_filter" name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?php echo ($_GET['statut'] ?? '') === 'actif' ? 'selected' : ''; ?>>Actif</option>
                    <option value="inactif" <?php echo ($_GET['statut'] ?? '') === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Liste des utilisateurs -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom d'utilisateur</th>
                        <th>Nom complet</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Téléphone</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo escape_html($user['username']); ?></td>
                        <td><?php echo escape_html($user['prenom'] . ' ' . $user['nom']); ?></td>
                        <td><?php echo escape_html($user['email']); ?></td>
                        <td>
                            <?php
                            $role_colors = [
                                'admin' => 'background: linear-gradient(135deg, #ef1c5d 0%, #c71450 100%);',
                                'scolarite' => 'background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);',
                                'enseignant' => 'background: linear-gradient(135deg, #f58024 0%, #d66a1a 100%);',
                                'etudiant' => 'background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);',
                                'bi' => 'background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);'
                            ];
                            $role_icons = [
                                'admin' => 'fas fa-user-shield',
                                'scolarite' => 'fas fa-user-tie',
                                'enseignant' => 'fas fa-chalkboard-teacher',
                                'etudiant' => 'fas fa-user-graduate',
                                'bi' => 'fas fa-chart-pie'
                            ];
                            $role_style = $role_colors[$user['role']] ?? 'background: #6c757d;';
                            $role_icon = $role_icons[$user['role']] ?? 'fas fa-user';
                            ?>
                            <span class="badge" style="<?php echo $role_style; ?> padding: 8px 12px; font-size: 0.8rem;">
                                <i class="<?php echo $role_icon; ?> me-1"></i><?php echo ROLES[$user['role']]; ?>
                            </span>
                        </td>
                        <td><?php echo escape_html($user['telephone']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user['statut'] === 'actif' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($user['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Supprimer cet utilisateur ?')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                Affichage de <?php echo $offset + 1; ?> à <?php echo min($offset + $per_page, $total_users); ?> sur <?php echo $total_users; ?> utilisateurs
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . $role_filter : ''; ?><?php echo !empty($statut_filter) ? '&statut=' . $statut_filter : ''; ?>">Précédent</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . $role_filter : ''; ?><?php echo !empty($statut_filter) ? '&statut=' . $statut_filter : ''; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . $role_filter : ''; ?><?php echo !empty($statut_filter) ? '&statut=' . $statut_filter : ''; ?>">Suivant</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Utilisateur -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Nouvel utilisateur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="userForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="user_id">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="prenom" class="form-label">Prénom *</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="text" class="form-control" id="telephone" name="telephone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Rôle *</label>
                            <select class="form-select" id="role" name="role" required>
                                <?php foreach (ROLES as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Mot de passe <span id="password_required">*</span></label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="text-muted">Laisser vide pour ne pas modifier</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="statut" class="form-label">Statut *</label>
                            <select class="form-select" id="statut" name="statut" required>
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Champs spécifiques pour les étudiants -->
                    <div id="etudiant_fields" style="display: none;">
                        <hr>
                        <h6 class="text-primary">Informations étudiant</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="matricule" class="form-label">Matricule *</label>
                                <input type="text" class="form-control" id="matricule" name="matricule">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="classe_id" class="form-label">Classe *</label>
                                <select class="form-select" id="classe_id" name="classe_id">
                                    <option value="">Sélectionner une classe...</option>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?php echo $classe['id']; ?>">
                                            <?php echo escape_html($classe['filiere'] . ' - ' . $classe['niveau'] . ' - ' . $classe['libelle']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_naissance" class="form-label">Date de naissance *</label>
                                <input type="date" class="form-control" id="date_naissance" name="date_naissance">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sexe" class="form-label">Sexe *</label>
                                <select class="form-select" id="sexe" name="sexe">
                                    <option value="">Sélectionner...</option>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Pour une inscription complète, utilisez le module <strong>Scolarité → Inscriptions</strong>
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
    document.getElementById('userForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('user_id').value = '';
    document.getElementById('modalTitle').textContent = 'Nouvel utilisateur';
    document.getElementById('password').required = true;
    document.getElementById('password_required').style.display = 'inline';
    document.getElementById('etudiant_fields').style.display = 'none';
}

function editUser(user) {
    document.getElementById('action').value = 'edit';
    document.getElementById('user_id').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('email').value = user.email;
    document.getElementById('nom').value = user.nom;
    document.getElementById('prenom').value = user.prenom;
    document.getElementById('telephone').value = user.telephone || '';
    document.getElementById('role').value = user.role;
    document.getElementById('statut').value = user.statut;
    document.getElementById('password').required = false;
    document.getElementById('password_required').style.display = 'none';
    document.getElementById('modalTitle').textContent = 'Modifier l\'utilisateur';
    document.getElementById('etudiant_fields').style.display = 'none';
    
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

// Afficher/masquer les champs étudiant selon le rôle sélectionné
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const etudiantFields = document.getElementById('etudiant_fields');
    const matriculeInput = document.getElementById('matricule');
    const classeSelect = document.getElementById('classe_id');
    const dateNaissanceInput = document.getElementById('date_naissance');
    const sexeSelect = document.getElementById('sexe');
    
    roleSelect.addEventListener('change', function() {
        if (this.value === 'etudiant') {
            etudiantFields.style.display = 'block';
            matriculeInput.required = true;
            classeSelect.required = true;
            dateNaissanceInput.required = true;
            sexeSelect.required = true;
        } else {
            etudiantFields.style.display = 'none';
            matriculeInput.required = false;
            classeSelect.required = false;
            dateNaissanceInput.required = false;
            sexeSelect.required = false;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
