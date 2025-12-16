<?php
require_once '../config/config.php';
require_role('enseignant');

$page_title = 'Mes Matières';
$db = Database::getInstance()->getConnection();

// Récupérer l'enseignant
$enseignant_id = $_SESSION['user_id'];

// Récupérer toutes les matières enseignées par cet enseignant avec les classes
$stmt = $db->prepare("SELECT m.*, 
                      GROUP_CONCAT(DISTINCT CONCAT(c.libelle, ' (', f.libelle, ' - ', n.libelle, ')') SEPARATOR ', ') as classes,
                      COUNT(DISTINCT cm.classe_id) as nb_classes
                      FROM classe_matieres cm
                      JOIN matieres m ON cm.matiere_id = m.id
                      JOIN classes c ON cm.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      WHERE cm.enseignant_id = ?
                      GROUP BY m.id
                      ORDER BY m.libelle");
$stmt->execute([$enseignant_id]);
$matieres = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-book"></i> Mes Matières</h2>
        <p class="text-muted">Liste des matières que vous enseignez</p>
    </div>
</div>

<?php if (empty($matieres)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Vous n'êtes assigné à aucune matière pour le moment. Contactez l'administration.
</div>
<?php else: ?>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card bg-gradient-pink text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0" style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo count($matieres); ?></h3>
                        <p class="mb-0" style="color: white; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Matières enseignées</p>
                    </div>
                    <i class="fas fa-book fa-3x opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-gradient-orange text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0" style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo array_sum(array_column($matieres, 'nb_classes')); ?></h3>
                        <p class="mb-0" style="color: white; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Classes au total</p>
                    </div>
                    <i class="fas fa-school fa-3x opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-gradient-yellow text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0" style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo array_sum(array_column($matieres, 'coefficient')); ?></h3>
                        <p class="mb-0" style="color: white; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Coefficient total</p>
                    </div>
                    <i class="fas fa-calculator fa-3x opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des matières -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Liste de mes matières</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Matière</th>
                        <th>Coefficient</th>
                        <th>Nombre de classes</th>
                        <th>Classes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matieres as $matiere): ?>
                    <tr>
                        <td><span class="badge bg-info"><?php echo escape_html($matiere['code']); ?></span></td>
                        <td>
                            <strong><?php echo escape_html($matiere['libelle']); ?></strong>
                            <?php if ($matiere['description']): ?>
                            <br><small class="text-muted"><?php echo escape_html($matiere['description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-star"></i> <?php echo $matiere['coefficient']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-primary">
                                <?php echo $matiere['nb_classes']; ?> classe<?php echo $matiere['nb_classes'] > 1 ? 's' : ''; ?>
                            </span>
                        </td>
                        <td>
                            <small><?php echo escape_html($matiere['classes']); ?></small>
                        </td>
                        <td>
                            <a href="notes.php?matiere_id=<?php echo $matiere['id']; ?>&auto_select=1" class="btn btn-sm btn-primary" title="Saisir les notes">
                                <i class="fas fa-edit"></i> Notes
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Détails par classe -->
<div class="card mt-4">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="fas fa-th-list"></i> Détails par classe</h5>
    </div>
    <div class="card-body">
        <div class="accordion" id="accordionClasses">
            <?php
            // Récupérer les détails par classe
            $stmt = $db->prepare("SELECT cm.*, m.libelle as matiere, m.code as matiere_code, m.coefficient,
                                  c.libelle as classe, f.libelle as filiere, n.libelle as niveau,
                                  COUNT(DISTINCT e.id) as nb_etudiants
                                  FROM classe_matieres cm
                                  JOIN matieres m ON cm.matiere_id = m.id
                                  JOIN classes c ON cm.classe_id = c.id
                                  JOIN filieres f ON c.filiere_id = f.id
                                  JOIN niveaux n ON c.niveau_id = n.id
                                  LEFT JOIN etudiants e ON c.id = e.classe_id AND e.statut = 'actif'
                                  WHERE cm.enseignant_id = ?
                                  GROUP BY cm.id
                                  ORDER BY c.libelle, m.libelle");
            $stmt->execute([$enseignant_id]);
            $details = $stmt->fetchAll();
            
            $current_classe = '';
            $index = 0;
            foreach ($details as $detail):
                if ($current_classe != $detail['classe']):
                    if ($current_classe != ''): ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
                    <?php endif;
                    $current_classe = $detail['classe'];
                    $index++;
                ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                <button class="accordion-button <?php echo $index > 1 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo $index == 1 ? 'true' : 'false'; ?>">
                    <strong><?php echo escape_html($detail['classe']); ?></strong>
                    <span class="ms-2 text-muted">(<?php echo escape_html($detail['filiere'] . ' - ' . $detail['niveau']); ?>)</span>
                    <span class="badge bg-info ms-auto me-2"><?php echo $detail['nb_etudiants']; ?> étudiants</span>
                </button>
            </h2>
            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index == 1 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>">
                <div class="accordion-body">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Matière</th>
                                <th>Coefficient</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                <?php endif; ?>
                            <tr>
                                <td><span class="badge bg-info"><?php echo escape_html($detail['matiere_code']); ?></span></td>
                                <td><?php echo escape_html($detail['matiere']); ?></td>
                                <td><span class="badge bg-warning text-dark"><?php echo $detail['coefficient']; ?></span></td>
                                <td>
                                    <a href="notes.php?classe_id=<?php echo $detail['classe_id']; ?>&matiere_id=<?php echo $detail['matiere_id']; ?>&auto_select=1" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Saisir notes
                                    </a>
                                </td>
                            </tr>
            <?php endforeach; ?>
            <?php if ($current_classe != ''): ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
