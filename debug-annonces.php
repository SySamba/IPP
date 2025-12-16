<?php
require_once 'config/config.php';
require_login();

$page_title = 'Debug Annonces';
$db = Database::getInstance()->getConnection();

echo "<h2>Debug des Annonces d'Absence</h2>";

// 1. Vérifier toutes les annonces
echo "<h3>1. Toutes les annonces dans la base</h3>";
$stmt = $db->query("SELECT aa.*, edt.classe_id, c.libelle as classe, m.libelle as matiere, 
                    u.nom, u.prenom, edt.jour, edt.heure_debut
                    FROM annonces_absence aa
                    JOIN emplois_du_temps edt ON aa.cours_id = edt.id
                    JOIN classes c ON edt.classe_id = c.id
                    JOIN matieres m ON edt.matiere_id = m.id
                    JOIN users u ON aa.enseignant_id = u.id
                    ORDER BY aa.date_absence DESC");
$annonces = $stmt->fetchAll();

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Date</th><th>Matière</th><th>Classe</th><th>Classe ID</th><th>Professeur</th><th>Jour</th><th>Heure</th><th>Message</th></tr>";
foreach ($annonces as $a) {
    echo "<tr>";
    echo "<td>{$a['id']}</td>";
    echo "<td>{$a['date_absence']}</td>";
    echo "<td>{$a['matiere']}</td>";
    echo "<td>{$a['classe']}</td>";
    echo "<td>{$a['classe_id']}</td>";
    echo "<td>{$a['prenom']} {$a['nom']}</td>";
    echo "<td>{$a['jour']}</td>";
    echo "<td>{$a['heure_debut']}</td>";
    echo "<td>{$a['message']}</td>";
    echo "</tr>";
}
echo "</table>";

// 2. Si l'utilisateur est étudiant, vérifier ses infos
if (has_role('etudiant')) {
    echo "<h3>2. Informations de l'étudiant connecté</h3>";
    $stmt = $db->prepare("SELECT e.*, c.libelle as classe, c.id as classe_id
                          FROM etudiants e
                          JOIN classes c ON e.classe_id = c.id
                          WHERE e.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $etudiant = $stmt->fetch();
    
    if ($etudiant) {
        echo "<p><strong>Classe de l'étudiant:</strong> {$etudiant['classe']} (ID: {$etudiant['classe_id']})</p>";
        
        // 3. Vérifier les annonces pour cette classe
        echo "<h3>3. Annonces pour la classe de l'étudiant (7 derniers jours)</h3>";
        $stmt = $db->prepare("SELECT aa.*, m.libelle as matiere, u.nom, u.prenom, edt.jour, edt.heure_debut,
                              c.libelle as classe_libelle, edt.classe_id
                              FROM annonces_absence aa
                              JOIN emplois_du_temps edt ON aa.cours_id = edt.id
                              JOIN classes c ON edt.classe_id = c.id
                              JOIN matieres m ON edt.matiere_id = m.id
                              JOIN users u ON aa.enseignant_id = u.id
                              WHERE edt.classe_id = ? 
                              AND aa.date_absence >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                              ORDER BY aa.date_absence DESC");
        $stmt->execute([$etudiant['classe_id']]);
        $annonces_etudiant = $stmt->fetchAll();
        
        echo "<p><strong>Nombre d'annonces trouvées:</strong> " . count($annonces_etudiant) . "</p>";
        
        if (count($annonces_etudiant) > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Date</th><th>Matière</th><th>Classe</th><th>Professeur</th><th>Message</th></tr>";
            foreach ($annonces_etudiant as $a) {
                echo "<tr>";
                echo "<td>{$a['date_absence']}</td>";
                echo "<td>{$a['matiere']}</td>";
                echo "<td>{$a['classe_libelle']}</td>";
                echo "<td>{$a['prenom']} {$a['nom']}</td>";
                echo "<td>{$a['message']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: red;'>Aucune annonce trouvée pour cette classe dans les 7 derniers jours.</p>";
        }
        
        // 4. Vérifier la date actuelle
        echo "<h3>4. Informations de date</h3>";
        $stmt = $db->query("SELECT CURDATE() as today, DATE_SUB(CURDATE(), INTERVAL 7 DAY) as week_ago");
        $dates = $stmt->fetch();
        echo "<p><strong>Date actuelle (serveur):</strong> {$dates['today']}</p>";
        echo "<p><strong>Date il y a 7 jours:</strong> {$dates['week_ago']}</p>";
    }
}

// 5. Vérifier les classes
echo "<h3>5. Toutes les classes</h3>";
$stmt = $db->query("SELECT c.id, c.libelle, f.libelle as filiere, n.libelle as niveau
                    FROM classes c
                    JOIN filieres f ON c.filiere_id = f.id
                    JOIN niveaux n ON c.niveau_id = n.id
                    WHERE c.statut = 'active'
                    ORDER BY c.libelle");
$classes = $stmt->fetchAll();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Classe</th><th>Filière</th><th>Niveau</th></tr>";
foreach ($classes as $c) {
    echo "<tr>";
    echo "<td>{$c['id']}</td>";
    echo "<td>{$c['libelle']}</td>";
    echo "<td>{$c['filiere']}</td>";
    echo "<td>{$c['niveau']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><br><a href='index.php'>Retour à l'accueil</a>";
?>
