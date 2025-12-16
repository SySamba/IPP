<?php
require_once '../config/config.php';
require_role('admin');

$page_title = 'Détails de la matière';
$db = Database::getInstance()->getConnection();

$matiere_id = intval($_GET['matiere_id'] ?? 0);

// Récupération des informations de la matière
$stmt = $db->prepare("SELECT * FROM matieres WHERE id = ?");
$stmt->execute([$matiere_id]);
$matiere = $stmt->fetch();

if (!$matiere) {
    set_flash_message('Matière introuvable', 'danger');
    redirect('admin/matieres.php');
}

// Récupération de toutes les affectations (classe + enseignant)
$stmt = $db->prepare("SELECT cm.*, 
                      c.libelle as classe, c.code as classe_code,
                      f.libelle as filiere, n.libelle as niveau,
                      u.nom as enseignant_nom, u.prenom as enseignant_prenom,
                      COUNT(DISTINCT e.id) as nb_etudiants
                      FROM classe_matieres cm
                      JOIN classes c ON cm.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      LEFT JOIN users u ON cm.enseignant_id = u.id
                      LEFT JOIN etudiants e ON e.classe_id = c.id AND e.statut = 'actif'
                      WHERE cm.matiere_id = ?
                      GROUP BY cm.id
                      ORDER BY f.libelle, n.ordre, c.libelle");
$stmt->execute([$matiere_id]);
$affectations = $stmt->fetchAll();

// Statistiques
$total_classes = count($affectations);
$total_enseignants = count(array_unique(array_filter(array_column($affectations, 'enseignant_id'))));
$total_etudiants = array_sum(array_column($affectations, 'nb_etudiants'));

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/matieres.php">Matières</a></li>
                <li class="breadcrumb-item active"><?php echo escape_html($matiere['libelle']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-gradient text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2"><i class="fas fa-book"></i> <?php echo escape_html($matiere['libelle']); ?></h2>
                        <p class="mb-0">
                            <strong>Code:</strong> <?php echo escape_html($matiere['code']); ?> | 
                            <strong>Coefficient:</strong> <?php echo $matiere['coefficient']; ?> | 
                            <strong>Statut:</strong> <?php echo ucfirst($matiere['statut']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="<?php echo BASE_URL; ?>/admin/matiere-classes.php?matiere_id=<?php echo $matiere_id; ?>" 
                           class="btn btn-light">
                            <i class="fas fa-plus"></i> Ajouter à une classe
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-school fa-3x text-primary"></i>
                </div>
                <h2 class="mb-2"><?php echo $total_classes; ?></h2>
                <p class="text-muted mb-0">Classe(s)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-chalkboard-teacher fa-3x text-success"></i>
                </div>
                <h2 class="mb-2"><?php echo $total_enseignants; ?></h2>
                <p class="text-muted mb-0">Enseignant(s)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-user-graduate fa-3x text-info"></i>
                </div>
                <h2 class="mb-2"><?php echo $total_etudiants; ?></h2>
                <p class="text-muted mb-0">Étudiant(s)</p>
            </div>
        </div>
    </div>
</div>

<!-- Liste des affectations -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Affectations par classe</h5>
    </div>
    <div class="card-body">
        <?php if (empty($affectations)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Cette matière n'est affectée à aucune classe pour le moment.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Classe</th>
                        <th>Filière</th>
                        <th>Niveau</th>
                        <th>Coefficient</th>
                        <th>Enseignant</th>
                        <th>Étudiants</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($affectations as $aff): ?>
                    <tr>
                        <td><strong><?php echo escape_html($aff['classe']); ?></strong></td>
                        <td><?php echo escape_html($aff['filiere']); ?></td>
                        <td><?php echo escape_html($aff['niveau']); ?></td>
                        <td><span class="badge bg-info"><?php echo $aff['coefficient']; ?></span></td>
                        <td>
                            <?php if ($aff['enseignant_nom']): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-user"></i> 
                                    <?php echo escape_html($aff['enseignant_prenom'] . ' ' . $aff['enseignant_nom']); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Non assigné</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-primary"><?php echo $aff['nb_etudiants']; ?></span></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/admin/classe-matieres.php?classe_id=<?php echo $aff['classe_id']; ?>" 
                               class="btn btn-sm btn-info" title="Gérer cette classe">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
