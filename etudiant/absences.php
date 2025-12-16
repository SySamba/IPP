<?php
require_once '../config/config.php';
require_role('etudiant');

$page_title = 'Mes absences';
$db = Database::getInstance()->getConnection();

// Récupérer l'étudiant
$stmt = $db->prepare("SELECT e.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau
                      FROM etudiants e
                      JOIN classes c ON e.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      WHERE e.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$etudiant = $stmt->fetch();

$absences = [];
$total_heures = 0;

if ($etudiant) {
    // Récupération des absences
    $stmt = $db->prepare("SELECT a.*, m.libelle as matiere, m.code as matiere_code,
                          '' as saisi_par_nom, '' as saisi_par_prenom
                          FROM absences a
                          JOIN classe_matieres cm ON a.classe_matiere_id = cm.id
                          JOIN matieres m ON cm.matiere_id = m.id
                          WHERE a.etudiant_id = ?
                          ORDER BY a.date_absence DESC, a.heure_debut DESC");
    $stmt->execute([$etudiant['id']]);
    $absences = $stmt->fetchAll();
    
    // Calculer le total d'heures d'absence
    foreach ($absences as $absence) {
        if ($absence['heure_debut'] && $absence['heure_fin']) {
            $debut = strtotime($absence['heure_debut']);
            $fin = strtotime($absence['heure_fin']);
            $duree = ($fin - $debut) / 3600; // en heures
            $total_heures += $duree;
        }
    }
}

// Statistiques
$total_absences = count($absences);
$justifiees = count(array_filter($absences, fn($a) => $a['type'] === 'justifiee'));
$non_justifiees = count(array_filter($absences, fn($a) => $a['type'] === 'non_justifiee'));

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-calendar-times"></i> Mes absences</h2>
        <p class="text-muted"><?php echo escape_html($etudiant['filiere'] . ' - ' . $etudiant['niveau'] . ' - ' . $etudiant['classe']); ?></p>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #fcb628 0%, #e09e1a 100%);">
            <div class="card-body text-center">
                <h6 class="mb-2" style="color: white; font-weight: 700; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Total absences</h6>
                <h1 class="display-4 mb-0" style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo $total_absences; ?></h1>
                <small style="color: white; font-weight: 600;">séance(s)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #f58024 0%, #d66a1a 100%);">
            <div class="card-body text-center">
                <h6 class="mb-2" style="color: white; font-weight: 700; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Total heures</h6>
                <h1 class="display-4 mb-0" style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo number_format($total_heures, 1); ?></h1>
                <small style="color: white; font-weight: 600;">heure(s)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
            <div class="card-body text-center">
                <h6 class="mb-2" style="color: white; font-weight: 700; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Justifiées</h6>
                <h1 class="display-4 mb-0" style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo $justifiees; ?></h1>
                <small style="color: white; font-weight: 600;">absence(s)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #ef1c5d 0%, #c71450 100%);">
            <div class="card-body text-center">
                <h6 class="mb-2" style="color: white; font-weight: 700; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Non justifiées</h6>
                <h1 class="display-4 mb-0" style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo $non_justifiees; ?></h1>
                <small style="color: white; font-weight: 600;">absence(s)</small>
            </div>
        </div>
    </div>
</div>

<!-- Liste des absences -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Détail des absences</h5>
    </div>
    <div class="card-body">
        <?php if (empty($absences)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Vous n'avez aucune absence enregistrée. Excellent ! Continuez ainsi !
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Jour</th>
                            <th>Matière</th>
                            <th>Horaire</th>
                            <th>Durée</th>
                            <th>Type</th>
                            <th>Motif</th>
                            <th>Enregistré par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $absence): 
                            $jour_semaine = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                            $jour = $jour_semaine[date('w', strtotime($absence['date_absence']))];
                            
                            $duree = '-';
                            if ($absence['heure_debut'] && $absence['heure_fin']) {
                                $debut = strtotime($absence['heure_debut']);
                                $fin = strtotime($absence['heure_fin']);
                                $duree_heures = ($fin - $debut) / 3600;
                                $duree = number_format($duree_heures, 1) . 'h';
                            }
                        ?>
                        <tr>
                            <td><strong><?php echo date('d/m/Y', strtotime($absence['date_absence'])); ?></strong></td>
                            <td><span class="badge bg-secondary"><?php echo $jour; ?></span></td>
                            <td><?php echo escape_html($absence['matiere']); ?></td>
                            <td>
                                <?php if ($absence['heure_debut']): ?>
                                    <i class="fas fa-clock"></i> <?php echo substr($absence['heure_debut'], 0, 5); ?> - <?php echo substr($absence['heure_fin'], 0, 5); ?>
                                <?php else: ?>
                                    <span class="text-muted">Journée complète</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo $duree; ?></strong></td>
                            <td>
                                <span class="badge bg-<?php echo $absence['type'] === 'justifiee' ? 'success' : 'danger'; ?>">
                                    <i class="fas fa-<?php echo $absence['type'] === 'justifiee' ? 'check' : 'times'; ?>"></i>
                                    <?php echo $absence['type'] === 'justifiee' ? 'Justifiée' : 'Non justifiée'; ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo escape_html($absence['motif'] ?: '-'); ?></small>
                                <?php if ($absence['justificatif']): ?>
                                <br><a href="<?php echo BASE_URL; ?>/uploads/justificatifs/<?php echo $absence['justificatif']; ?>" target="_blank" class="text-primary">
                                    <i class="fas fa-paperclip"></i> Voir justificatif
                                </a>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?php echo escape_html($absence['saisi_par_prenom'] . ' ' . $absence['saisi_par_nom']); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">TOTAL</th>
                            <th><strong><?php echo number_format($total_heures, 1); ?>h</strong></th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($non_justifiees > 0): ?>
<div class="alert alert-warning mt-3">
    <i class="fas fa-exclamation-triangle"></i> 
    <strong>Attention:</strong> Vous avez <?php echo $non_justifiees; ?> absence(s) non justifiée(s). 
    Veuillez contacter le service de scolarité pour régulariser votre situation.
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
