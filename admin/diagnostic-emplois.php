<?php
require_once '../config/config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();

echo "<h2>Diagnostic des tables emplois du temps</h2>";

try {
    // Vérifier quelles tables existent
    echo "<h3>Tables existantes:</h3>";
    $tables = $db->query("SHOW TABLES LIKE '%emplois%'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<strong style='color: red;'>Aucune table emplois trouvée!</strong><br><br>";
    } else {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li><strong>{$table}</strong></li>";
        }
        echo "</ul>";
    }
    
    // Vérifier emplois_du_temps
    echo "<h3>Vérification de la table 'emplois_du_temps':</h3>";
    try {
        $count = $db->query("SELECT COUNT(*) as count FROM emplois_du_temps")->fetch();
        echo "✓ Table 'emplois_du_temps' existe<br>";
        echo "Nombre d'enregistrements: <strong>{$count['count']}</strong><br><br>";
        
        if ($count['count'] > 0) {
            echo "<h4>Contenu de la table:</h4>";
            $emplois = $db->query("SELECT * FROM emplois_du_temps")->fetchAll();
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Classe ID</th><th>Matière ID</th><th>Enseignant ID</th><th>Salle ID</th><th>Jour</th><th>Heure début</th><th>Heure fin</th><th>Année ID</th></tr>";
            foreach ($emplois as $e) {
                echo "<tr>";
                echo "<td>{$e['id']}</td>";
                echo "<td>{$e['classe_id']}</td>";
                echo "<td>{$e['matiere_id']}</td>";
                echo "<td>{$e['enseignant_id']}</td>";
                echo "<td>" . ($e['salle_id'] ?? 'NULL') . "</td>";
                echo "<td>{$e['jour']}</td>";
                echo "<td>{$e['heure_debut']}</td>";
                echo "<td>{$e['heure_fin']}</td>";
                echo "<td>{$e['annee_academique_id']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<strong style='color: red;'>✗ Table 'emplois_du_temps' n'existe pas</strong><br>";
        echo "Erreur: " . $e->getMessage() . "<br><br>";
    }
    
    // Vérifier emplois_temps (sans du)
    echo "<h3>Vérification de la table 'emplois_temps':</h3>";
    try {
        $count = $db->query("SELECT COUNT(*) as count FROM emplois_temps")->fetch();
        echo "✓ Table 'emplois_temps' existe<br>";
        echo "Nombre d'enregistrements: <strong>{$count['count']}</strong><br><br>";
        
        if ($count['count'] > 0) {
            echo "<h4>Contenu de la table:</h4>";
            $emplois = $db->query("SELECT * FROM emplois_temps")->fetchAll();
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Classe ID</th><th>Classe Matière ID</th><th>Salle ID</th><th>Jour</th><th>Heure début</th><th>Heure fin</th><th>Année ID</th></tr>";
            foreach ($emplois as $e) {
                echo "<tr>";
                echo "<td>{$e['id']}</td>";
                echo "<td>{$e['classe_id']}</td>";
                echo "<td>{$e['classe_matiere_id']}</td>";
                echo "<td>" . ($e['salle_id'] ?? 'NULL') . "</td>";
                echo "<td>{$e['jour']}</td>";
                echo "<td>{$e['heure_debut']}</td>";
                echo "<td>{$e['heure_fin']}</td>";
                echo "<td>{$e['annee_academique_id']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<strong style='color: red;'>✗ Table 'emplois_temps' n'existe pas</strong><br>";
        echo "Erreur: " . $e->getMessage() . "<br><br>";
    }
    
    // Vérifier les années académiques
    echo "<h3>Années académiques:</h3>";
    $annees = $db->query("SELECT * FROM annees_academiques")->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Libellé</th><th>Statut</th></tr>";
    foreach ($annees as $a) {
        echo "<tr>";
        echo "<td>{$a['id']}</td>";
        echo "<td>{$a['libelle']}</td>";
        echo "<td><strong>{$a['statut']}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><br><a href='emplois-du-temps.php'>Retour aux emplois du temps</a>";
    
} catch (Exception $e) {
    echo "<strong style='color: red;'>Erreur: " . $e->getMessage() . "</strong>";
}
?>
