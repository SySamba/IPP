<?php
require_once '../config/config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();

try {
    // 1. Vérifier si la colonne code existe
    $columns = $db->query("SHOW COLUMNS FROM salles")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('code', $columns)) {
        echo "Ajout de la colonne 'code'...<br>";
        $db->exec("ALTER TABLE salles ADD COLUMN code VARCHAR(20) UNIQUE AFTER id");
    }
    
    // 2. Mettre à jour les enregistrements existants sans code
    echo "Mise à jour des codes manquants...<br>";
    $salles_sans_code = $db->query("SELECT id, libelle FROM salles WHERE code IS NULL OR code = ''")->fetchAll();
    
    foreach ($salles_sans_code as $salle) {
        // Générer un code basé sur le libellé ou l'ID
        $code = 'SALLE_' . $salle['id'];
        $stmt = $db->prepare("UPDATE salles SET code = ? WHERE id = ?");
        $stmt->execute([$code, $salle['id']]);
        echo "Code '{$code}' ajouté pour '{$salle['libelle']}'<br>";
    }
    
    // 3. Normaliser les statuts (disponible -> active, indisponible -> inactive)
    echo "Normalisation des statuts...<br>";
    
    // Vérifier le type de la colonne statut
    $column_info = $db->query("SHOW COLUMNS FROM salles LIKE 'statut'")->fetch();
    
    if (strpos($column_info['Type'], 'disponible') !== false) {
        // Modifier le type ENUM pour inclure 'active' et 'inactive'
        $db->exec("ALTER TABLE salles MODIFY COLUMN statut ENUM('disponible', 'indisponible', 'maintenance', 'active', 'inactive') DEFAULT 'active'");
        
        // Mettre à jour les valeurs
        $db->exec("UPDATE salles SET statut = 'active' WHERE statut = 'disponible'");
        $db->exec("UPDATE salles SET statut = 'inactive' WHERE statut = 'indisponible'");
        
        // Retirer les anciennes valeurs de l'ENUM
        $db->exec("ALTER TABLE salles MODIFY COLUMN statut ENUM('active', 'inactive', 'maintenance') DEFAULT 'active'");
        
        echo "Statuts normalisés (disponible → active, indisponible → inactive)<br>";
    }
    
    echo "<br><strong style='color: green;'>✓ Migration terminée avec succès!</strong><br>";
    echo "<a href='salles.php' class='btn btn-primary mt-3'>Retour aux salles</a>";
    
} catch (Exception $e) {
    echo "<strong style='color: red;'>Erreur: " . $e->getMessage() . "</strong>";
}
?>
