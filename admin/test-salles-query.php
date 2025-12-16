<?php
require_once '../config/config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();

echo "<h2>Test des requêtes de salles</h2>";

try {
    // Test 1: Requête utilisée dans emplois-du-temps.php
    echo "<h3>Test 1: Requête avec statut IN ('active', 'disponible')</h3>";
    $salles1 = $db->query("SELECT id, libelle, capacite, type, statut FROM salles WHERE statut IN ('active', 'disponible') ORDER BY libelle")->fetchAll();
    echo "Nombre de salles trouvées: " . count($salles1) . "<br>";
    if (!empty($salles1)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Libellé</th><th>Capacité</th><th>Type</th><th>Statut</th></tr>";
        foreach ($salles1 as $salle) {
            echo "<tr>";
            echo "<td>{$salle['id']}</td>";
            echo "<td>{$salle['libelle']}</td>";
            echo "<td>{$salle['capacite']}</td>";
            echo "<td>{$salle['type']}</td>";
            echo "<td>{$salle['statut']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<strong style='color: red;'>Aucune salle trouvée!</strong><br>";
    }
    
    // Test 2: Requête simple sans filtre
    echo "<h3>Test 2: Toutes les salles (sans filtre)</h3>";
    $salles2 = $db->query("SELECT id, libelle, capacite, type, statut FROM salles ORDER BY libelle")->fetchAll();
    echo "Nombre de salles trouvées: " . count($salles2) . "<br>";
    if (!empty($salles2)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Libellé</th><th>Capacité</th><th>Type</th><th>Statut</th></tr>";
        foreach ($salles2 as $salle) {
            echo "<tr>";
            echo "<td>{$salle['id']}</td>";
            echo "<td>{$salle['libelle']}</td>";
            echo "<td>{$salle['capacite']}</td>";
            echo "<td>{$salle['type']}</td>";
            echo "<td>{$salle['statut']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 3: Requête pour le modal
    echo "<h3>Test 3: Requête du modal (id, libelle, capacite)</h3>";
    $salles3 = $db->query("SELECT id, libelle, capacite FROM salles WHERE statut IN ('active', 'disponible') ORDER BY libelle")->fetchAll();
    echo "Nombre de salles trouvées: " . count($salles3) . "<br>";
    if (!empty($salles3)) {
        echo "<ul>";
        foreach ($salles3 as $salle) {
            echo "<li>ID: {$salle['id']} - {$salle['libelle']} ({$salle['capacite']} places)</li>";
        }
        echo "</ul>";
    } else {
        echo "<strong style='color: red;'>Aucune salle trouvée pour le modal!</strong><br>";
    }
    
    echo "<br><br>";
    echo "<strong style='color: green;'>✓ Si vous voyez des salles ci-dessus, elles devraient maintenant apparaître dans le formulaire emplois du temps.</strong><br><br>";
    echo "<a href='emplois-du-temps.php'>Aller à Emplois du temps</a> | ";
    echo "<a href='salles.php'>Retour aux salles</a>";
    
} catch (Exception $e) {
    echo "<strong style='color: red;'>Erreur: " . $e->getMessage() . "</strong><br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
