<?php
require_once 'config/config.php';
require_login();

$page_title = 'Mon profil';
$db = Database::getInstance()->getConnection();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $nom = clean_input($_POST['nom']);
    $prenom = clean_input($_POST['prenom']);
    $email = clean_input($_POST['email']);
    $telephone = clean_input($_POST['telephone']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {
        $db->beginTransaction();
        
        // Mise à jour des informations de base
        $stmt = $db->prepare("UPDATE users SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id = ?");
        $stmt->execute([$nom, $prenom, $email, $telephone, $_SESSION['user_id']]);
        
        // Mise à jour du mot de passe si fourni
        if (!empty($new_password)) {
            // Vérifier le mot de passe actuel
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!verify_password($current_password, $user['password'])) {
                throw new Exception('Mot de passe actuel incorrect');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('Les mots de passe ne correspondent pas');
            }
            
            if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
                throw new Exception('Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères');
            }
            
            $hashed_password = hash_password($new_password);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
        }
        
        $db->commit();
        
        // Mettre à jour la session
        $_SESSION['user_nom'] = $nom;
        $_SESSION['user_prenom'] = $prenom;
        
        log_activity('Modification profil', 'users', $_SESSION['user_id']);
        set_flash_message('Profil mis à jour avec succès', 'success');
        redirect('profile.php');
    } catch (Exception $e) {
        $db->rollBack();
        set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
    }
}

// Récupération des informations de l'utilisateur
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-user"></i> Mon profil</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    
                    <h5 class="mb-3">Informations personnelles</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="nom" name="nom" 
                                   value="<?php echo escape_html($user['nom']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="prenom" class="form-label">Prénom *</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" 
                                   value="<?php echo escape_html($user['prenom']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo escape_html($user['email']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="text" class="form-control" id="telephone" name="telephone" 
                                   value="<?php echo escape_html($user['telephone']); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" value="<?php echo escape_html($user['username']); ?>" readonly>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rôle</label>
                            <input type="text" class="form-control" value="<?php echo ROLES[$user['role']]; ?>" readonly>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5 class="mb-3">Changer le mot de passe</h5>
                    <p class="text-muted small">Laissez vide si vous ne souhaitez pas changer votre mot de passe</p>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Mot de passe actuel</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informations du compte</h5>
            </div>
            <div class="card-body">
                <p><strong>Date de création:</strong> <?php echo format_date($user['created_at'], 'd/m/Y H:i'); ?></p>
                <p><strong>Dernière modification:</strong> <?php echo format_date($user['updated_at'], 'd/m/Y H:i'); ?></p>
                <p class="mb-0"><strong>Statut:</strong> 
                    <span class="badge bg-<?php echo $user['statut'] === 'actif' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($user['statut']); ?>
                    </span>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
