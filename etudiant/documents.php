<?php
require_once '../config/config.php';
require_role('etudiant');

$page_title = 'Documents';
$db = Database::getInstance()->getConnection();

// Récupérer l'étudiant
$stmt = $db->prepare("SELECT e.*, c.id as classe_id, c.libelle as classe, f.libelle as filiere, n.libelle as niveau
                      FROM etudiants e
                      JOIN users u ON e.user_id = u.id
                      JOIN classes c ON e.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$etudiant = $stmt->fetch();

if (!$etudiant) {
    set_flash_message('Profil étudiant introuvable', 'danger');
    redirect('index.php');
}

// Récupérer les documents pour ma classe
$stmt = $db->prepare("SELECT d.*, u.nom as prof_nom, u.prenom as prof_prenom,
                      'Général' as matiere, 'GEN' as matiere_code
                      FROM documents d
                      JOIN users u ON d.uploaded_by = u.id
                      WHERE d.classe_id = ? OR d.classe_id IS NULL
                      ORDER BY d.created_at DESC");
$stmt->execute([$etudiant['classe_id']]);
$documents = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-folder-open"></i> Mes Documents</h2>
        <p class="text-muted">Documents partagés par vos enseignants | <?php echo escape_html($etudiant['filiere'] . ' - ' . $etudiant['niveau'] . ' - ' . $etudiant['classe']); ?></p>
    </div>
</div>

<?php if (empty($documents)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Aucun document disponible pour le moment.
</div>
<?php else: ?>

<!-- Grouper les documents par matière -->
<?php 
$docs_par_matiere = [];
foreach ($documents as $doc) {
    $matiere_key = $doc['matiere_code'];
    if (!isset($docs_par_matiere[$matiere_key])) {
        $docs_par_matiere[$matiere_key] = [
            'matiere' => $doc['matiere'],
            'documents' => []
        ];
    }
    $docs_par_matiere[$matiere_key]['documents'][] = $doc;
}
?>

<?php foreach ($docs_par_matiere as $matiere_data): ?>
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-book"></i> <?php echo escape_html($matiere_data['matiere']); ?>
            <span class="badge bg-light text-dark float-end"><?php echo count($matiere_data['documents']); ?> document(s)</span>
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($matiere_data['documents'] as $doc): ?>
            <div class="col-md-6 mb-3">
                <div class="card border-secondary h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-file-<?php 
                                    $ext = strtolower(pathinfo($doc['fichier'], PATHINFO_EXTENSION));
                                    echo $ext === 'pdf' ? 'pdf' : ($ext === 'doc' || $ext === 'docx' ? 'word' : 
                                        ($ext === 'xls' || $ext === 'xlsx' ? 'excel' : 
                                        ($ext === 'ppt' || $ext === 'pptx' ? 'powerpoint' : 'alt')));
                                ?> text-primary"></i>
                                <?php echo escape_html($doc['titre']); ?>
                            </h6>
                            <span class="badge bg-secondary"><?php echo strtoupper($ext); ?></span>
                        </div>
                        
                        <?php if ($doc['description']): ?>
                        <p class="card-text text-muted small mb-2">
                            <?php echo escape_html($doc['description']); ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-user"></i> <?php echo escape_html($doc['prof_prenom'] . ' ' . $doc['prof_nom']); ?>
                            </small>
                            <br>
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y à H:i', strtotime($doc['created_at'])); ?>
                            </small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="<?php echo BASE_URL; ?>/uploads/documents/<?php echo $doc['fichier']; ?>" 
                               class="btn btn-primary btn-sm" target="_blank">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            <a href="<?php echo BASE_URL; ?>/uploads/documents/<?php echo $doc['fichier']; ?>" 
                               class="btn btn-success btn-sm" download>
                                <i class="fas fa-download"></i> Télécharger
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Vue en liste complète -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list"></i> Liste complète</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Titre</th>
                        <th>Matière</th>
                        <th>Enseignant</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><strong><?php echo escape_html($doc['titre']); ?></strong></td>
                        <td><span class="badge bg-info"><?php echo escape_html($doc['matiere']); ?></span></td>
                        <td><small><?php echo escape_html($doc['prof_prenom'] . ' ' . $doc['prof_nom']); ?></small></td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo strtoupper(pathinfo($doc['fichier'], PATHINFO_EXTENSION)); ?>
                            </span>
                        </td>
                        <td><small><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></small></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/uploads/documents/<?php echo $doc['fichier']; ?>" 
                               class="btn btn-sm btn-primary" target="_blank" title="Voir">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/uploads/documents/<?php echo $doc['fichier']; ?>" 
                               class="btn btn-sm btn-success" download title="Télécharger">
                                <i class="fas fa-download"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
