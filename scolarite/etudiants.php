<?php
require_once '../config/config.php';
require_role(['admin', 'scolarite']);

$page_title = 'Gestion des étudiants';
$db = Database::getInstance()->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        
        // Données utilisateur
        $username = clean_input($_POST['username']);
        $email = clean_input($_POST['email']);
        $password = $_POST['password'] ?? '';
        $nom = clean_input($_POST['nom']);
        $prenom = clean_input($_POST['prenom']);
        $telephone = clean_input($_POST['telephone']);
        
        // Données étudiant
        $matricule = clean_input($_POST['matricule']);
        $date_naissance = clean_input($_POST['date_naissance']);
        $lieu_naissance = clean_input($_POST['lieu_naissance']);
        $sexe = clean_input($_POST['sexe']);
        $adresse = clean_input($_POST['adresse']);
        $ville = clean_input($_POST['ville']);
        $pays = clean_input($_POST['pays']);
        $nationalite = clean_input($_POST['nationalite']);
        $nom_tuteur = clean_input($_POST['nom_tuteur']);
        $telephone_tuteur = clean_input($_POST['telephone_tuteur']);
        $statut = clean_input($_POST['statut']);
        
        // Données obligatoires
        $classe_id = intval($_POST['classe_id']);
        $annee_academique_id = intval($_POST['annee_academique_id']);
        
        if ($classe_id <= 0 || $annee_academique_id <= 0) {
            set_flash_message('La classe et l\'année académique sont obligatoires', 'danger');
            redirect('scolarite/etudiants.php');
            exit;
        }
        
        try {
            $db->beginTransaction();
            
            if ($action === 'add') {
                if (empty($password)) {
                    throw new Exception('Le mot de passe est requis');
                }
                
                // Créer l'utilisateur
                $hashed_password = hash_password($password);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role, nom, prenom, telephone, statut) VALUES (?, ?, ?, 'etudiant', ?, ?, ?, 'actif')");
                $stmt->execute([$username, $email, $hashed_password, $nom, $prenom, $telephone]);
                $user_id = $db->lastInsertId();
                
                // Créer l'étudiant avec classe et année
                $stmt = $db->prepare("INSERT INTO etudiants (user_id, matricule, classe_id, annee_academique_id, date_naissance, lieu_naissance, sexe, adresse, ville, pays, nationalite, nom_tuteur, telephone_tuteur, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $matricule, $classe_id, $annee_academique_id, $date_naissance, $lieu_naissance, $sexe, $adresse, $ville, $pays, $nationalite, $nom_tuteur, $telephone_tuteur, $statut]);
                $etudiant_id = $db->lastInsertId();
                
                // Créer l'inscription automatiquement
                $stmt = $db->prepare("INSERT INTO inscriptions (etudiant_id, classe_id, annee_academique_id, type_inscription, date_inscription, statut) VALUES (?, ?, ?, 'nouvelle', CURDATE(), 'validee')");
                $stmt->execute([$etudiant_id, $classe_id, $annee_academique_id]);
                
                $db->commit();
                log_activity('Création étudiant', 'etudiants', $etudiant_id, $matricule);
                set_flash_message('Étudiant créé avec succès', 'success');
            } else {
                // Récupérer l'user_id et les données actuelles
                $stmt = $db->prepare("SELECT user_id, classe_id, annee_academique_id FROM etudiants WHERE id = ?");
                $stmt->execute([$id]);
                $etudiant = $stmt->fetch();
                $user_id = $etudiant['user_id'];
                
                // Vérifier si on change de classe pour la même année académique
                if ($etudiant['annee_academique_id'] == $annee_academique_id && $etudiant['classe_id'] != $classe_id) {
                    // Vérifier s'il y a d'autres inscriptions validées pour cette année
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM inscriptions WHERE etudiant_id = ? AND annee_academique_id = ? AND classe_id != ? AND statut = 'validee'");
                    $stmt->execute([$id, $annee_academique_id, $classe_id]);
                    $result = $stmt->fetch();
                    if ($result['count'] > 0) {
                        throw new Exception('Cet étudiant est déjà inscrit dans une autre classe pour cette année académique');
                    }
                }
                
                // Mettre à jour l'utilisateur
                $sql = "UPDATE users SET username = ?, email = ?, nom = ?, prenom = ?, telephone = ?";
                $params = [$username, $email, $nom, $prenom, $telephone];
                
                if (!empty($password)) {
                    $sql .= ", password = ?";
                    $params[] = hash_password($password);
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $user_id;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                // Mettre à jour l'étudiant avec classe et année
                $stmt = $db->prepare("UPDATE etudiants SET matricule = ?, classe_id = ?, annee_academique_id = ?, date_naissance = ?, lieu_naissance = ?, sexe = ?, adresse = ?, ville = ?, pays = ?, nationalite = ?, nom_tuteur = ?, telephone_tuteur = ?, statut = ? WHERE id = ?");
                $stmt->execute([$matricule, $classe_id, $annee_academique_id, $date_naissance, $lieu_naissance, $sexe, $adresse, $ville, $pays, $nationalite, $nom_tuteur, $telephone_tuteur, $statut, $id]);
                
                // Mettre à jour l'inscription si nécessaire
                if ($etudiant['classe_id'] != $classe_id || $etudiant['annee_academique_id'] != $annee_academique_id) {
                    $stmt = $db->prepare("UPDATE inscriptions SET classe_id = ?, annee_academique_id = ? WHERE etudiant_id = ? AND annee_academique_id = ?");
                    $stmt->execute([$classe_id, $annee_academique_id, $id, $etudiant['annee_academique_id']]);
                }
                
                $db->commit();
                log_activity('Modification étudiant', 'etudiants', $id, $matricule);
                set_flash_message('Étudiant modifié avec succès', 'success');
            }
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('scolarite/etudiants.php');
    }
}

// Données pour les formulaires
$classes = $db->query("SELECT c.*, f.libelle as filiere, n.libelle as niveau FROM classes c JOIN filieres f ON c.filiere_id = f.id JOIN niveaux n ON c.niveau_id = n.id WHERE c.statut = 'active' ORDER BY f.libelle, n.ordre")->fetchAll();
$annees = $db->query("SELECT * FROM annees_academiques ORDER BY date_debut DESC")->fetchAll();

// Récupération des filières et classes pour les filtres
$filieres = $db->query("SELECT * FROM filieres WHERE statut = 'active' ORDER BY libelle")->fetchAll();
$classes = $db->query("SELECT c.*, f.libelle as filiere, n.libelle as niveau FROM classes c JOIN filieres f ON c.filiere_id = f.id JOIN niveaux n ON c.niveau_id = n.id WHERE c.statut = 'active' ORDER BY f.libelle, n.ordre")->fetchAll();

// Récupération des étudiants
$search = $_GET['search'] ?? '';
$statut_filter = $_GET['statut'] ?? '';
$filiere_filter = $_GET['filiere_id'] ?? '';
$classe_filter = $_GET['classe_id'] ?? '';

$sql = "SELECT e.*, u.username, u.email, u.telephone, u.nom, u.prenom,
        c.libelle as classe, c.id as classe_id, f.libelle as filiere, f.id as filiere_id, n.libelle as niveau, aa.libelle as annee
        FROM etudiants e
        JOIN users u ON e.user_id = u.id
        JOIN classes c ON e.classe_id = c.id
        JOIN filieres f ON c.filiere_id = f.id
        JOIN niveaux n ON c.niveau_id = n.id
        JOIN annees_academiques aa ON e.annee_academique_id = aa.id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (e.matricule LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_fill(0, 4, $search_param);
}

if (!empty($statut_filter)) {
    $sql .= " AND e.statut = ?";
    $params[] = $statut_filter;
}

if (!empty($filiere_filter)) {
    $sql .= " AND f.id = ?";
    $params[] = $filiere_filter;
}

if (!empty($classe_filter)) {
    $sql .= " AND c.id = ?";
    $params[] = $classe_filter;
}

$sql .= " ORDER BY e.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$etudiants = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-user-graduate"></i> Gestion des étudiants</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#etudiantModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Nouvel étudiant
            </button>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Rechercher par matricule, nom, prénom..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filiere_filter" name="filiere_id">
                    <option value="">Toutes les filières</option>
                    <?php foreach ($filieres as $filiere): ?>
                        <option value="<?php echo $filiere['id']; ?>" <?php echo $filiere_filter == $filiere['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($filiere['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="classe_filter" name="classe_id">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($classe['niveau'] . ' - ' . $classe['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statut_filter" name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?php echo $statut_filter === 'actif' ? 'selected' : ''; ?>>Actif</option>
                    <option value="inactif" <?php echo $statut_filter === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                    <option value="suspendu" <?php echo $statut_filter === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Liste des étudiants -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Nom complet</th>
                        <th>Classe</th>
                        <th>Filière</th>
                        <th>Niveau</th>
                        <th>Année</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($etudiants as $etudiant): ?>
                    <tr>
                        <td><strong><?php echo escape_html($etudiant['matricule']); ?></strong></td>
                        <td><?php echo escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></td>
                        <td><?php echo escape_html($etudiant['classe']); ?></td>
                        <td><?php echo escape_html($etudiant['filiere']); ?></td>
                        <td><?php echo escape_html($etudiant['niveau']); ?></td>
                        <td><?php echo escape_html($etudiant['annee']); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $etudiant['statut'] === 'actif' ? 'success' : 
                                    ($etudiant['statut'] === 'diplome' ? 'info' : 
                                    ($etudiant['statut'] === 'suspendu' ? 'warning' : 'secondary')); 
                            ?>">
                                <?php echo ucfirst($etudiant['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/scolarite/etudiant-detail.php?id=<?php echo $etudiant['id']; ?>" 
                               class="btn btn-sm btn-primary" title="Détails">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button class="btn btn-sm btn-info" onclick="editEtudiant(<?php echo $etudiant['id']; ?>)">
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

<!-- Modal Étudiant -->
<div class="modal fade" id="etudiantModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nouvel étudiant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="etudiantForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="etudiant_id">
                
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
                        <div class="col-md-3 mb-3">
                            <label for="matricule" class="form-label">Matricule *</label>
                            <input type="text" class="form-control" id="matricule" name="matricule" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="nom" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="prenom" class="form-label">Prénom *</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="sexe" class="form-label">Sexe *</label>
                            <select class="form-select" id="sexe" name="sexe" required>
                                <option value="">Sélectionner...</option>
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="date_naissance" class="form-label">Date de naissance *</label>
                            <input type="date" class="form-control" id="date_naissance" name="date_naissance" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="lieu_naissance" class="form-label">Lieu de naissance</label>
                            <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nationalite" class="form-label">Nationalité</label>
                            <input type="text" class="form-control" id="nationalite" name="nationalite">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="text" class="form-control" id="telephone" name="telephone">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="ville" class="form-label">Ville</label>
                            <input type="text" class="form-control" id="ville" name="ville">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="pays" class="form-label">Pays</label>
                            <input type="text" class="form-control" id="pays" name="pays">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adresse" class="form-label">Adresse</label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="2"></textarea>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3"><i class="fas fa-users"></i> Informations tuteur</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom_tuteur" class="form-label">Nom du tuteur</label>
                            <input type="text" class="form-control" id="nom_tuteur" name="nom_tuteur">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telephone_tuteur" class="form-label">Téléphone tuteur</label>
                            <input type="text" class="form-control" id="telephone_tuteur" name="telephone_tuteur">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                            <option value="suspendu">Suspendu</option>
                            <option value="diplome">Diplômé</option>
                        </select>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3"><i class="fas fa-graduation-cap"></i> Inscription *</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classe_id" class="form-label">Classe *</label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe...</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php echo escape_html($classe['filiere'] . ' - ' . $classe['niveau'] . ' - ' . $classe['libelle']); ?>
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
    document.getElementById('etudiantForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('etudiant_id').value = '';
    document.getElementById('modalTitle').textContent = 'Nouvel étudiant';
    document.getElementById('password').required = true;
    document.getElementById('password_required').style.display = 'inline';
    
    // Générer un matricule automatique
    document.getElementById('matricule').value = 'IPP' + new Date().getFullYear() + Math.floor(Math.random() * 10000).toString().padStart(4, '0');
}

function editEtudiant(id) {
    // Charger les données via AJAX
    fetch('<?php echo BASE_URL; ?>/api/get-etudiant.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const etudiant = data.etudiant;
                document.getElementById('action').value = 'edit';
                document.getElementById('etudiant_id').value = etudiant.id;
                document.getElementById('username').value = etudiant.username;
                document.getElementById('email').value = etudiant.email;
                document.getElementById('matricule').value = etudiant.matricule;
                document.getElementById('nom').value = etudiant.nom;
                document.getElementById('prenom').value = etudiant.prenom;
                document.getElementById('sexe').value = etudiant.sexe;
                document.getElementById('date_naissance').value = etudiant.date_naissance;
                document.getElementById('lieu_naissance').value = etudiant.lieu_naissance || '';
                document.getElementById('nationalite').value = etudiant.nationalite || '';
                document.getElementById('telephone').value = etudiant.telephone || '';
                document.getElementById('ville').value = etudiant.ville || '';
                document.getElementById('pays').value = etudiant.pays || '';
                document.getElementById('adresse').value = etudiant.adresse || '';
                document.getElementById('nom_tuteur').value = etudiant.nom_tuteur || '';
                document.getElementById('telephone_tuteur').value = etudiant.telephone_tuteur || '';
                document.getElementById('statut').value = etudiant.statut;
                document.getElementById('classe_id').value = etudiant.classe_id;
                document.getElementById('annee_academique_id').value = etudiant.annee_academique_id;
                document.getElementById('password').required = false;
                document.getElementById('password_required').style.display = 'none';
                document.getElementById('modalTitle').textContent = 'Modifier l\'étudiant';
                
                new bootstrap.Modal(document.getElementById('etudiantModal')).show();
            }
        });
}
</script>

<?php include '../includes/footer.php'; ?>
