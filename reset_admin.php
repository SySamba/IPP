<?php
/**
 * Script de réinitialisation du mot de passe admin
 * À supprimer après utilisation
 */

require_once 'config/config.php';

$db = Database::getInstance()->getConnection();

// Nouveau mot de passe
$new_password = 'admin123';
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

try {
    // Mettre à jour le mot de passe admin
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$hashed]);
    
    echo "✅ Mot de passe admin réinitialisé avec succès!<br>";
    echo "Utilisateur: <strong>admin</strong><br>";
    echo "Mot de passe: <strong>admin123</strong><br><br>";
    echo "Hash généré: " . $hashed . "<br><br>";
    
    // Vérifier
    $stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ Utilisateur trouvé dans la base<br>";
        echo "Email: " . $user['email'] . "<br>";
        echo "Statut: " . $user['statut'] . "<br><br>";
        
        // Test de vérification
        if (password_verify('admin123', $user['password'])) {
            echo "✅ Vérification du mot de passe: OK<br>";
        } else {
            echo "❌ Vérification du mot de passe: ÉCHEC<br>";
        }
    } else {
        echo "❌ Utilisateur admin non trouvé!<br>";
    }
    
    echo "<br><a href='login.php'>Aller à la page de connexion</a>";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
