<?php
require_once '../config/config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();

echo "<h2>Diagnostic des salles</h2>";

try {
    // Afficher la structure de la table
    echo "<h3>Structure de la table salles:</h3>";
    $columns = $db->query("SHOW COLUMNS FROM salles")->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Afficher toutes les salles avec leurs valeurs
    echo "<h3>Contenu de la table salles:</h3>";
    $salles = $db->query("SELECT * FROM salles")->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Code</th><th>Libellé</th><th>Capacité</th><th>Type</th><th>Statut</th><th>Batiment</th></tr>";
    foreach ($salles as $salle) {
        echo "<tr>";
        echo "<td>{$salle['id']}</td>";
        echo "<td>" . ($salle['code'] ?? 'NULL') . "</td>";
        echo "<td>{$salle['libelle']}</td>";
        echo "<td>{$salle['capacite']}</td>";
        echo "<td>" . ($salle['type'] ?? 'NULL') . "</td>";
        echo "<td>" . ($salle['statut'] ?? 'NULL') . "</td>";
        echo "<td>" . ($salle['batiment'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Corriger les salles avec statut vide ou NULL
    echo "<h3>Correction des statuts:</h3>";
    $count = $db->exec("UPDATE salles SET statut = 'active' WHERE statut IS NULL OR statut = ''");
    echo "✓ {$count} salle(s) mise(s) à jour avec statut 'active'<br>";
    
    // Corriger les types vides
    echo "<h3>Correction des types:</h3>";
    $count = $db->exec("UPDATE salles SET type = 'cours' WHERE type IS NULL OR type = ''");
    echo "✓ {$count} salle(s) mise(s) à jour avec type 'cours'<br>";
    
    // Afficher les salles après correction
    echo "<h3>Salles après correction:</h3>";
    $salles = $db->query("SELECT * FROM salles")->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Code</th><th>Libellé</th><th>Capacité</th><th>Type</th><th>Statut</th></tr>";
    foreach ($salles as $salle) {
        echo "<tr>";
        echo "<td>{$salle['id']}</td>";
        echo "<td>{$salle['code']}</td>";
        echo "<td>{$salle['libelle']}</td>";
        echo "<td>{$salle['capacite']}</td>";
        echo "<td>{$salle['type']}</td>";
        echo "<td>{$salle['statut']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><br><a href='salles.php'>Retour aux salles</a> | ";
    echo "<a href='emplois-du-temps.php'>Voir emplois du temps</a>";
    
} catch (Exception $e) {
    echo "<strong style='color: red;'>Erreur: " . $e->getMessage() . "</strong>";
}
?>
