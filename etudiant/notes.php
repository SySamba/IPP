<?php
require_once '../config/config.php';
require_role('etudiant');

$page_title = 'Mes notes';
$db = Database::getInstance()->getConnection();

// Récupérer l'étudiant
$stmt = $db->prepare("SELECT e.*, e.classe_id, c.libelle as classe, aa.id as annee_id, aa.libelle as annee,
                      f.libelle as filiere, n.libelle as niveau
                      FROM etudiants e
                      JOIN classes c ON e.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      JOIN annees_academiques aa ON e.annee_academique_id = aa.id
                      WHERE e.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$etudiant = $stmt->fetch();

$notes_par_matiere = [];
$periodes = [];

if ($etudiant && $etudiant['classe_id']) {
    // Récupérer les périodes
    $stmt = $db->prepare("SELECT * FROM periodes WHERE annee_academique_id = ? ORDER BY numero");
    $stmt->execute([$etudiant['annee_id']]);
    $periodes = $stmt->fetchAll();
    
    // Filtre semestre
    $semestre_filter = $_GET['semestre'] ?? '1'; // Par défaut semestre 1
    
    // Récupérer les notes groupées par matière avec filtre semestre
    $stmt = $db->prepare("SELECT n.*, m.libelle as matiere, m.code as matiere_code, cm.coefficient as coef_matiere, 
                          n.type_note as type_eval, n.semestre
                          FROM notes n
                          JOIN classe_matieres cm ON n.classe_matiere_id = cm.id
                          JOIN matieres m ON cm.matiere_id = m.id
                          WHERE n.etudiant_id = ? AND n.semestre = ?
                          ORDER BY m.libelle, n.date_evaluation");
    $stmt->execute([$etudiant['id'], $semestre_filter]);
    $notes = $stmt->fetchAll();
    
    // Grouper par matière
    $notes_par_matiere = [];
    foreach ($notes as $note) {
        $matiere_key = $note['matiere_code'];
        
        if (!isset($notes_par_matiere[$matiere_key])) {
            $notes_par_matiere[$matiere_key] = [
                'matiere' => $note['matiere'],
                'code' => $note['matiere_code'],
                'coefficient' => $note['coef_matiere'],
                'notes' => []
            ];
        }
        $notes_par_matiere[$matiere_key]['notes'][] = $note;
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-chart-line"></i> Mes notes</h2>
        <p class="text-muted"><?php echo escape_html($etudiant['filiere'] . ' - ' . $etudiant['niveau'] . ' - ' . $etudiant['classe']); ?> | Année: <?php echo escape_html($etudiant['annee']); ?></p>
    </div>
</div>

<!-- Sélection du semestre -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-4">
                <label class="form-label fw-bold">Choisir le semestre</label>
                <select class="form-select form-select-lg" id="semestre_filter" name="semestre">
                    <option value="1" <?php echo $semestre_filter == '1' ? 'selected' : ''; ?>>Semestre 1</option>
                    <option value="2" <?php echo $semestre_filter == '2' ? 'selected' : ''; ?>>Semestre 2</option>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($etudiant && $etudiant['classe_id']): ?>

<?php if (empty($notes_par_matiere)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Aucune note disponible pour le moment.
    </div>
<?php else: ?>
    <?php foreach ($notes_par_matiere as $matiere_data): ?>
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <?php echo escape_html($matiere_data['matiere']); ?>
                        <span class="badge bg-light text-dark float-end">Coef: <?php echo $matiere_data['coefficient']; ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Semestre</th>
                                    <th>Note</th>
                                    <th>Sur</th>
                                    <th>Note/20</th>
                                    <th>Coef</th>
                                    <th>Date</th>
                                    <th>Commentaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_points = 0;
                                $total_coef = 0;
                                foreach ($matiere_data['notes'] as $note): 
                                    $note_sur_20 = ($note['note'] / $note['note_sur']) * 20;
                                    $total_points += $note_sur_20 * $note['coefficient'];
                                    $total_coef += $note['coefficient'];
                                ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?php echo escape_html(ucfirst($note['type_eval'])); ?></span></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($note['semestre'] ?? 1) == 1 ? 'primary' : 'success'; ?>">
                                            S<?php echo $note['semestre'] ?? 1; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo number_format($note['note'], 2); ?></strong></td>
                                    <td><?php echo number_format($note['note_sur'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $note_sur_20 >= 10 ? 'success' : 'danger'; ?>">
                                            <?php echo number_format($note_sur_20, 2); ?>/20
                                        </span>
                                    </td>
                                    <td><?php echo $note['coefficient']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($note['date_evaluation'])); ?></td>
                                    <td><small><?php echo escape_html($note['commentaire'] ?: '-'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3">Moyenne de la matière</th>
                                    <th colspan="4">
                                        <span class="badge bg-info fs-6">
                                            <?php echo $total_coef > 0 ? number_format($total_points / $total_coef, 2) : '0.00'; ?>/20
                                        </span>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
    <?php endforeach; ?>
    
    <!-- Moyenne générale -->
    <div class="card border-primary">
        <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <h5 class="mb-0">Moyenne générale</h5>
        </div>
        <div class="card-body text-center">
            <?php
            $total_general = 0;
            $total_coef_general = 0;
            
            foreach ($notes_par_matiere as $matiere_data) {
                $total_matiere = 0;
                $total_coef_matiere = 0;
                
                foreach ($matiere_data['notes'] as $note) {
                    $note_sur_20 = ($note['note'] / $note['note_sur']) * 20;
                    $total_matiere += $note_sur_20 * $note['coefficient'];
                    $total_coef_matiere += $note['coefficient'];
                }
                
                if ($total_coef_matiere > 0) {
                    $moyenne_matiere = $total_matiere / $total_coef_matiere;
                    $total_general += $moyenne_matiere * $matiere_data['coefficient'];
                    $total_coef_general += $matiere_data['coefficient'];
                }
            }
            
            $moyenne_generale = $total_coef_general > 0 ? $total_general / $total_coef_general : 0;
            ?>
            <h1 class="display-3 mb-3"><?php echo number_format($moyenne_generale, 2); ?><small class="text-muted">/20</small></h1>
            <h4>
                <span class="badge bg-<?php 
                    echo $moyenne_generale >= 16 ? 'success' : 
                        ($moyenne_generale >= 14 ? 'primary' : 
                        ($moyenne_generale >= 12 ? 'info' : 
                        ($moyenne_generale >= 10 ? 'warning' : 'danger'))); 
                ?> fs-4">
                    <?php 
                    if ($moyenne_generale >= 16) echo 'Très Bien';
                    elseif ($moyenne_generale >= 14) echo 'Bien';
                    elseif ($moyenne_generale >= 12) echo 'Assez Bien';
                    elseif ($moyenne_generale >= 10) echo 'Passable';
                    else echo 'Insuffisant';
                    ?>
                </span>
            </h4>
        </div>
    </div>
<?php endif; ?>

<?php else: ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> Vous n'êtes pas encore inscrit dans une classe.
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
