<?php
require_once '../config/config.php';
require_role(['admin', 'scolarite']);

$page_title = 'Détail étudiant';
$db = Database::getInstance()->getConnection();

$etudiant_id = intval($_GET['id'] ?? 0);

if ($etudiant_id <= 0) {
    set_flash_message('Étudiant introuvable', 'danger');
    redirect('scolarite/etudiants.php');
}

// Récupération des informations complètes de l'étudiant
$stmt = $db->prepare("SELECT e.*, u.username, u.email, u.telephone, u.nom, u.prenom, u.statut as user_statut
                      FROM etudiants e
                      JOIN users u ON e.user_id = u.id
                      WHERE e.id = ?");
$stmt->execute([$etudiant_id]);
$etudiant = $stmt->fetch();

if (!$etudiant) {
    set_flash_message('Étudiant introuvable', 'danger');
    redirect('scolarite/etudiants.php');
}

// Récupération des inscriptions
$stmt = $db->prepare("SELECT i.*, c.libelle as classe, aa.libelle as annee, f.libelle as filiere, n.libelle as niveau
                      FROM inscriptions i
                      JOIN classes c ON i.classe_id = c.id
                      JOIN annees_academiques aa ON i.annee_academique_id = aa.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      WHERE i.etudiant_id = ?
                      ORDER BY i.created_at DESC");
$stmt->execute([$etudiant_id]);
$inscriptions = $stmt->fetchAll();

// Récupération des paiements
$stmt = $db->prepare("SELECT p.*, aa.libelle as annee
                      FROM paiements p
                      JOIN annees_academiques aa ON p.annee_academique_id = aa.id
                      WHERE p.etudiant_id = ?
                      ORDER BY p.created_at DESC");
$stmt->execute([$etudiant_id]);
$paiements = $stmt->fetchAll();

// Statistiques de notes (dernière inscription active)
$inscription_active = null;
foreach ($inscriptions as $insc) {
    if ($insc['statut'] === 'validee') {
        $inscription_active = $insc;
        break;
    }
}

$moyenne_generale = 0;
$nb_absences = 0;

if ($inscription_active) {
    // Calcul moyenne
    $stmt = $db->prepare("SELECT AVG((n.note / n.note_sur) * 20) as moyenne
                          FROM notes n
                          JOIN classe_matieres cm ON n.classe_matiere_id = cm.id
                          WHERE n.etudiant_id = ? AND cm.classe_id = ?");
    $stmt->execute([$etudiant_id, $inscription_active['classe_id']]);
    $result = $stmt->fetch();
    $moyenne_generale = $result['moyenne'] ?? 0;
    
    // Nombre d'absences
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM absences WHERE etudiant_id = ?");
    $stmt->execute([$etudiant_id]);
    $nb_absences = $stmt->fetch()['total'];
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/scolarite/etudiants.php">Étudiants</a></li>
                <li class="breadcrumb-item active"><?php echo escape_html($etudiant['matricule']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <!-- Carte profil -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user"></i> Profil</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-5x text-primary"></i>
                </div>
                <h4><?php echo escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></h4>
                <p class="text-muted mb-2"><?php echo escape_html($etudiant['matricule']); ?></p>
                <span class="badge bg-<?php echo $etudiant['statut'] === 'actif' ? 'success' : 'secondary'; ?> mb-3">
                    <?php echo ucfirst($etudiant['statut']); ?>
                </span>
                
                <hr>
                
                <div class="text-start">
                    <p><i class="fas fa-envelope"></i> <?php echo escape_html($etudiant['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo escape_html($etudiant['telephone'] ?: 'Non renseigné'); ?></p>
                    <p><i class="fas fa-birthday-cake"></i> <?php echo format_date($etudiant['date_naissance']); ?></p>
                    <p><i class="fas fa-venus-mars"></i> <?php echo $etudiant['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Statistiques rapides -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Statistiques</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6>Moyenne générale</h6>
                    <h3 class="text-primary"><?php echo number_format($moyenne_generale, 2); ?>/20</h3>
                </div>
                <div class="mb-3">
                    <h6>Absences</h6>
                    <h3 class="text-warning"><?php echo $nb_absences; ?></h3>
                </div>
                <div>
                    <h6>Inscriptions</h6>
                    <h3 class="text-success"><?php echo count($inscriptions); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Informations personnelles -->
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-id-card"></i> Informations personnelles</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Date de naissance:</strong><br>
                        <?php echo $etudiant['date_naissance'] ? format_date($etudiant['date_naissance']) : 'Non renseignée'; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Lieu de naissance:</strong><br>
                        <?php echo escape_html($etudiant['lieu_naissance'] ?: 'Non renseigné'); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Sexe:</strong><br>
                        <?php echo $etudiant['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Nationalité:</strong><br>
                        <?php echo escape_html($etudiant['nationalite'] ?: 'Non renseignée'); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Ville:</strong><br>
                        <?php echo escape_html($etudiant['ville'] ?: 'Non renseignée'); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Pays:</strong><br>
                        <?php echo escape_html($etudiant['pays'] ?: 'Non renseigné'); ?>
                    </div>
                    <div class="col-12 mb-3">
                        <strong>Adresse:</strong><br>
                        <?php echo escape_html($etudiant['adresse'] ?: 'Non renseignée'); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Email:</strong><br>
                        <?php echo escape_html($etudiant['email']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Téléphone:</strong><br>
                        <?php echo escape_html($etudiant['telephone'] ?: 'Non renseigné'); ?>
                    </div>
                </div>
                
                <hr>
                
                <h6 class="mb-3">Tuteur / Responsable</h6>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Nom:</strong><br>
                        <?php echo escape_html($etudiant['nom_tuteur'] ?: 'Non renseigné'); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Téléphone:</strong><br>
                        <?php echo escape_html($etudiant['telephone_tuteur'] ?: 'Non renseigné'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Inscriptions -->
        <div class="card mb-3">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Historique des inscriptions</h5>
            </div>
            <div class="card-body">
                <?php if (empty($inscriptions)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucune inscription enregistrée.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Année</th>
                                    <th>Classe</th>
                                    <th>Filière</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inscriptions as $insc): ?>
                                <tr>
                                    <td><?php echo escape_html($insc['annee']); ?></td>
                                    <td><?php echo escape_html($insc['classe']); ?></td>
                                    <td><?php echo escape_html($insc['filiere']); ?></td>
                                    <td><span class="badge bg-<?php echo $insc['type_inscription'] === 'nouvelle' ? 'primary' : 'info'; ?>">
                                        <?php echo ucfirst($insc['type_inscription']); ?>
                                    </span></td>
                                    <td><?php echo format_date($insc['date_inscription']); ?></td>
                                    <td><span class="badge bg-<?php echo $insc['statut'] === 'validee' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($insc['statut']); ?>
                                    </span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Paiements -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-money-bill"></i> Historique des paiements</h5>
            </div>
            <div class="card-body">
                <?php if (empty($paiements)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucun paiement enregistré.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Année</th>
                                    <th>Type</th>
                                    <th>Montant total</th>
                                    <th>Payé</th>
                                    <th>Reste</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paiements as $paiement): ?>
                                <tr>
                                    <td><?php echo escape_html($paiement['annee']); ?></td>
                                    <td><?php echo escape_html($paiement['type_frais']); ?></td>
                                    <td><?php echo number_format($paiement['montant_total'], 0, ',', ' '); ?> FCFA</td>
                                    <td><?php echo number_format($paiement['montant_paye'], 0, ',', ' '); ?> FCFA</td>
                                    <td><?php echo number_format($paiement['montant_total'] - $paiement['montant_paye'], 0, ',', ' '); ?> FCFA</td>
                                    <td><span class="badge bg-<?php 
                                        echo $paiement['statut'] === 'paye' ? 'success' : 
                                            ($paiement['statut'] === 'partiel' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo strtoupper($paiement['statut']); ?>
                                    </span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
