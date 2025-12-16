<?php
require_once '../config/config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();

echo "<h2>Installation de la table annonces_absence</h2>";

try {
    // Créer la table annonces_absence
    $db->exec("CREATE TABLE IF NOT EXISTS annonces_absence (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cours_id INT NOT NULL,
        enseignant_id INT NOT NULL,
        date_absence DATE NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cours_id) REFERENCES emplois_du_temps(id) ON DELETE CASCADE,
        FOREIGN KEY (enseignant_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_date (date_absence),
        INDEX idx_enseignant (enseignant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "<div class='alert alert-success'>";
    echo "<i class='fas fa-check-circle'></i> Table 'annonces_absence' créée avec succès!";
    echo "</div>";
    
    echo "<h3>Fonctionnalités installées:</h3>";
    echo "<ul>";
    echo "<li>✓ Les enseignants peuvent créer des annonces d'absence</li>";
    echo "<li>✓ Les étudiants voient les annonces sur leur dashboard (message défilant)</li>";
    echo "<li>✓ Les annonces sont liées aux cours de l'emploi du temps</li>";
    echo "</ul>";
    
    echo "<br><a href='../enseignant/annonces.php' class='btn btn-primary'>Accéder aux annonces (Enseignant)</a> ";
    echo "<a href='../etudiant/dashboard.php' class='btn btn-success'>Voir dashboard (Étudiant)</a>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<strong>Erreur:</strong> " . $e->getMessage();
    echo "</div>";
}
?>
