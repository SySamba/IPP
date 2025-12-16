<?php
require_once '../config/config.php';
require_role('enseignant');

$page_title = 'Mes Classes';
$db = Database::getInstance()->getConnection();

// Récupérer les classes de l'enseignant
$enseignant_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT DISTINCT c.*, f.libelle as filiere, n.libelle as niveau, aa.libelle as annee,
                      COUNT(DISTINCT e.id) as nb_etudiants,
                      COUNT(DISTINCT cm.matiere_id) as nb_matieres
                      FROM classe_matieres cm
                      JOIN classes c ON cm.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      JOIN annees_academiques aa ON c.annee_academique_id = aa.id
                      LEFT JOIN etudiants e ON c.id = e.classe_id AND e.statut = 'actif'
                      WHERE cm.enseignant_id = ?
                      GROUP BY c.id
                      ORDER BY f.libelle, n.ordre, c.libelle");
$stmt->execute([$enseignant_id]);
$classes = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-school"></i> Mes Classes</h2>
        <p class="text-muted">Liste des classes dans lesquelles vous enseignez</p>
    </div>
</div>

<?php if (empty($classes)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Vous n'êtes assigné à aucune classe pour le moment. Contactez l'administration.
</div>
<?php else: ?>
<div class="row">
    <?php foreach ($classes as $classe): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?php echo escape_html($classe['libelle']); ?></h5>
                <small><?php echo escape_html($classe['filiere'] . ' - ' . $classe['niveau']); ?></small>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <i class="fas fa-calendar-alt text-muted"></i>
                    <strong>Année:</strong> <?php echo escape_html($classe['annee']); ?>
                </div>
                
                <div class="mb-3">
                    <i class="fas fa-users text-primary"></i>
                    <strong>Étudiants:</strong> <?php echo $classe['nb_etudiants']; ?>
                </div>
                
                <div class="mb-3">
                    <i class="fas fa-book text-success"></i>
                    <strong>Matières enseignées:</strong> <?php echo $classe['nb_matieres']; ?>
                </div>
                
                <hr>
                
                <?php
                // Récupérer les matières enseignées dans cette classe
                $stmt = $db->prepare("SELECT m.libelle, cm.coefficient 
                                      FROM classe_matieres cm 
                                      JOIN matieres m ON cm.matiere_id = m.id 
                                      WHERE cm.classe_id = ? AND cm.enseignant_id = ?");
                $stmt->execute([$classe['id'], $enseignant_id]);
                $matieres = $stmt->fetchAll();
                ?>
                
                <h6 class="text-muted mb-2">Mes matières:</h6>
                <ul class="list-unstyled">
                    <?php foreach ($matieres as $matiere): ?>
                    <li class="mb-1">
                        <i class="fas fa-check-circle text-success"></i>
                        <?php echo escape_html($matiere['libelle']); ?>
                        <span class="badge bg-secondary">Coef: <?php echo $matiere['coefficient']; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card-footer">
                <a href="<?php echo BASE_URL; ?>/enseignant/notes.php?classe_id=<?php echo $classe['id']; ?>&auto_select=1" 
                   class="btn btn-sm btn-primary">
                    <i class="fas fa-edit"></i> Saisir notes
                </a>
                <a href="<?php echo BASE_URL; ?>/enseignant/absences.php?classe_id=<?php echo $classe['id']; ?>" 
                   class="btn btn-sm btn-warning">
                    <i class="fas fa-calendar-times"></i> Absences
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
