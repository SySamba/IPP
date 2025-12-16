<?php
require_once '../config/config.php';
require_role(['admin', 'scolarite']);

$page_title = 'Détail des paiements';
$db = Database::getInstance()->getConnection();

// Récupérer l'étudiant
$etudiant_id = $_GET['etudiant_id'] ?? 0;

$stmt = $db->prepare("SELECT e.*, u.nom, u.prenom, u.email, 
                      c.libelle as classe, f.libelle as filiere, n.libelle as niveau,
                      aa.libelle as annee
                      FROM etudiants e
                      JOIN users u ON e.user_id = u.id
                      JOIN classes c ON e.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      JOIN annees_academiques aa ON e.annee_academique_id = aa.id
                      WHERE e.id = ?");
$stmt->execute([$etudiant_id]);
$etudiant = $stmt->fetch();

if (!$etudiant) {
    set_flash_message('Étudiant introuvable', 'danger');
    redirect('scolarite/paiements.php');
}

// Traitement des paiements
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_payment') {
        $type_frais = $_POST['type_frais'] ?? '';
        $montant = floatval($_POST['montant'] ?? 0);
        $mode_paiement = $_POST['mode_paiement'] ?? 'especes';
        $date_paiement = $_POST['date_paiement'] ?? date('Y-m-d');
        
        try {
            $stmt = $db->prepare("INSERT INTO paiements (etudiant_id, annee_academique_id, type_frais, montant_total, montant_paye, statut, date_paiement, mode_paiement, enregistre_par) 
                                 VALUES (?, ?, ?, ?, ?, 'paye', ?, ?, ?)");
            $stmt->execute([$etudiant_id, $etudiant['annee_academique_id'], $type_frais, $montant, $montant, $date_paiement, $mode_paiement, $_SESSION['user_id']]);
            
            log_activity('Ajout paiement étudiant', 'paiements', $db->lastInsertId());
            set_flash_message('Paiement enregistré avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('scolarite/paiements-detail.php?etudiant_id=' . $etudiant_id);
    }
}

// Récupérer les paiements
$stmt = $db->prepare("SELECT * FROM paiements WHERE etudiant_id = ? ORDER BY date_paiement DESC");
$stmt->execute([$etudiant_id]);
$paiements = $stmt->fetchAll();

// Calculer les totaux
$total_paye = 0;
foreach ($paiements as $p) {
    $total_paye += $p['montant_paye'];
}

// Mois de l'année
$mois_noms = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

// Créer un tableau pour tous les mois avec statut
$mois_statuts = [];
for ($i = 1; $i <= 12; $i++) {
    $mois_statuts[$i] = [
        'nom' => $mois_noms[$i],
        'numero' => $i,
        'paye' => false,
        'paiements' => []
    ];
}

// Marquer les mois payés - basé sur le type_frais, pas la date
foreach ($paiements as $p) {
    if ($p['statut'] === 'paye' && strpos($p['type_frais'], 'Scolarité -') !== false) {
        // Extraire le nom du mois depuis type_frais (ex: "Scolarité - Janvier")
        $mois_nom = trim(str_replace('Scolarité -', '', $p['type_frais']));
        // Trouver le numéro du mois correspondant
        foreach ($mois_noms as $num => $nom) {
            if ($nom === $mois_nom) {
                $mois_statuts[$num]['paye'] = true;
                $mois_statuts[$num]['paiements'][] = $p;
                break;
            }
        }
    }
}

// Compter les mois payés
$mois_payes_count = count(array_filter($mois_statuts, fn($m) => $m['paye']));

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <a href="paiements.php" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-left"></i> Retour à la liste
        </a>
        <h2><i class="fas fa-money-bill"></i> Détail des paiements</h2>
        <h4 class="text-muted"><?php echo escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></h4>
        <p class="text-muted"><?php echo escape_html($etudiant['filiere'] . ' - ' . $etudiant['niveau'] . ' - ' . $etudiant['classe']); ?></p>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card bg-gradient-green text-white">
            <div class="card-body">
                <h3><?php echo number_format($total_paye, 0, ',', ' '); ?> FCFA</h3>
                <p class="mb-0">Total payé</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-gradient-orange text-white">
            <div class="card-body">
                <h3><?php echo count($paiements); ?></h3>
                <p class="mb-0">Nombre de paiements</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-gradient-pink text-white">
            <div class="card-body">
                <h3><?php echo $mois_payes_count; ?> / 12</h3>
                <p class="mb-0">Mois payés</p>
            </div>
        </div>
    </div>
</div>

<!-- Calendrier des mois -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Calendrier des paiements mensuels</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($mois_statuts as $mois): ?>
            <div class="col-md-2 col-sm-4 col-6 mb-3">
                <div class="card h-100 <?php echo $mois['paye'] ? 'border-success' : 'border-secondary'; ?>">
                    <div class="card-body text-center p-2">
                        <h6 class="mb-1"><?php echo $mois['nom']; ?></h6>
                        <?php if ($mois['paye']): ?>
                            <i class="fas fa-check-circle text-success fa-2x"></i>
                            <p class="mb-0 small text-success"><strong>PAYÉ</strong></p>
                        <?php else: ?>
                            <i class="fas fa-times-circle text-secondary fa-2x"></i>
                            <p class="mb-0 small text-secondary"><strong>NON PAYÉ</strong></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Formulaire d'ajout de paiement -->
<div class="card mb-4">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Ajouter un paiement</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_payment">
            
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="type_frais" class="form-label">Type de frais *</label>
                        <select class="form-select" name="type_frais" id="type_frais" required>
                            <?php foreach ($mois_noms as $num => $nom): ?>
                                <option value="Scolarité - <?php echo $nom; ?>">Scolarité - <?php echo $nom; ?></option>
                            <?php endforeach; ?>
                            <option value="Inscription">Inscription</option>
                            <option value="Réinscription">Réinscription</option>
                            <option value="Examen">Examen</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="montant" class="form-label">Montant (FCFA) *</label>
                        <input type="number" class="form-control" name="montant" id="montant" value="50000" required min="0" step="1000">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="mode_paiement" class="form-label">Mode de paiement *</label>
                        <select class="form-select" name="mode_paiement" id="mode_paiement" required>
                            <option value="especes">Espèces</option>
                            <option value="cheque">Chèque</option>
                            <option value="virement">Virement</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="date_paiement" class="form-label">Date de paiement *</label>
                        <input type="date" class="form-control" name="date_paiement" id="date_paiement" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Enregistrer le paiement
            </button>
        </form>
    </div>
</div>

<!-- Historique des paiements -->
<div class="card">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-history"></i> Historique des paiements</h5>
    </div>
    <div class="card-body">
        <?php if (empty($paiements)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Aucun paiement enregistré pour cet étudiant.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type de frais</th>
                        <th>Montant</th>
                        <th>Mode</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paiements as $paiement): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($paiement['date_paiement'])); ?></td>
                        <td><?php echo escape_html($paiement['type_frais']); ?></td>
                        <td><strong><?php echo number_format($paiement['montant_paye'], 0, ',', ' '); ?> FCFA</strong></td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php 
                                $modes = [
                                    'especes' => 'Espèces',
                                    'cheque' => 'Chèque',
                                    'virement' => 'Virement',
                                    'mobile_money' => 'Mobile Money'
                                ];
                                echo $modes[$paiement['mode_paiement']] ?? $paiement['mode_paiement'];
                                ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-success">
                                <i class="fas fa-check"></i> Payé
                            </span>
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
