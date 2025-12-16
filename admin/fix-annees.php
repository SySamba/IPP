<?php
require_once '../config/config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();

echo "<h2>Correction des années académiques</h2>";

try {
    // Désactiver toutes les années sauf la plus récente
    $db->exec("UPDATE annees_academiques SET statut = 'inactive'");
    echo "✓ Toutes les années désactivées<br>";
    
    // Activer uniquement 2025-2026
    $db->exec("UPDATE annees_academiques SET statut = 'active' WHERE libelle = '2025-2026'");
    echo "✓ Année 2025-2026 activée<br><br>";
    
    // Migrer le cours vers l'année active
    echo "<h3>Migration du cours vers l'année active:</h3>";
    $annee_active = $db->query("SELECT id FROM annees_academiques WHERE statut = 'active' LIMIT 1")->fetch();
    
    $count = $db->exec("UPDATE emplois_du_temps SET annee_academique_id = {$annee_active['id']}");
    echo "✓ {$count} cours migré(s) vers l'année académique active (ID: {$annee_active['id']})<br><br>";
    
    // Afficher le résultat
    echo "<h3>Années académiques après correction:</h3>";
    $annees = $db->query("SELECT * FROM annees_academiques ORDER BY libelle DESC")->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Libellé</th><th>Statut</th></tr>";
    foreach ($annees as $a) {
        $style = $a['statut'] === 'active' ? "style='background-color: #d4edda;'" : "";
        echo "<tr {$style}>";
        echo "<td>{$a['id']}</td>";
        echo "<td>{$a['libelle']}</td>";
        echo "<td><strong>{$a['statut']}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><br><strong style='color: green;'>✓ Correction terminée!</strong><br><br>";
    echo "<a href='emplois-du-temps.php' class='btn btn-primary'>Voir les emplois du temps</a>";
    
} catch (Exception $e) {
    echo "<strong style='color: red;'>Erreur: " . $e->getMessage() . "</strong>";
}
?>
