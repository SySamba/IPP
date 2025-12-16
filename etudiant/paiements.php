<?php
require_once '../config/config.php';
require_role('etudiant');

$page_title = 'Mes Paiements';
$db = Database::getInstance()->getConnection();

// Récupérer l'étudiant
$stmt = $db->prepare("SELECT e.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau, aa.libelle as annee
                      FROM etudiants e
                      JOIN users u ON e.user_id = u.id
                      JOIN classes c ON e.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      JOIN annees_academiques aa ON e.annee_academique_id = aa.id
                      WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$etudiant = $stmt->fetch();

if (!$etudiant) {
    set_flash_message('Profil étudiant introuvable', 'danger');
    redirect('index.php');
}

// Récupérer les paiements avec détails
$stmt = $db->prepare("SELECT p.*, aa.libelle as annee 
                      FROM paiements p
                      JOIN annees_academiques aa ON p.annee_academique_id = aa.id
                      WHERE p.etudiant_id = ? 
                      ORDER BY p.created_at DESC");
$stmt->execute([$etudiant['id']]);
$paiements = $stmt->fetchAll();

// Calculer les totaux
$total_a_payer = 0;
$total_paye = 0;
foreach ($paiements as $p) {
    $total_a_payer += $p['montant_total'];
    $total_paye += $p['montant_paye'];
}
$reste_a_payer = $total_a_payer - $total_paye;

// Créer un tableau des 12 mois
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
$mois_payes_count = 0;
foreach ($mois_statuts as $mois) {
    if ($mois['paye']) {
        $mois_payes_count++;
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-money-bill"></i> Mes Paiements</h2>
        <p class="text-muted"><?php echo escape_html($etudiant['filiere'] . ' - ' . $etudiant['niveau'] . ' - ' . $etudiant['classe']); ?></p>
    </div>
</div>

<!-- Vue calendrier des paiements -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-calendar-check"></i> Calendrier des paiements
            <span class="badge bg-light text-dark float-end"><?php echo $mois_payes_count; ?> / 12 mois payés</span>
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($mois_statuts as $mois): ?>
            <div class="col-md-3 col-sm-4 col-6 mb-3">
                <div class="card h-100 <?php echo $mois['paye'] ? 'border-success' : 'border-secondary'; ?>">
                    <div class="card-body text-center p-3">
                        <h6 class="mb-2"><?php echo $mois['nom']; ?></h6>
                        <?php if ($mois['paye']): ?>
                            <div class="text-success">
                                <i class="fas fa-check-circle fa-3x mb-2"></i>
                                <p class="mb-0"><strong>PAYÉ</strong></p>
                                <?php if (!empty($mois['paiements'])): ?>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y', strtotime($mois['paiements'][0]['date_paiement'])); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-secondary">
                                <i class="fas fa-times-circle fa-3x mb-2"></i>
                                <p class="mb-0"><strong>NON PAYÉ</strong></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Barre de progression -->
        <div class="mt-4">
            <h6>Progression annuelle</h6>
            <div class="progress" style="height: 30px;">
                <div class="progress-bar bg-success" role="progressbar" 
                     style="width: <?php echo ($mois_payes_count / 12) * 100; ?>%" 
                     aria-valuenow="<?php echo $mois_payes_count; ?>" aria-valuemin="0" aria-valuemax="12">
                    <strong><?php echo $mois_payes_count; ?> / 12 mois (<?php echo round(($mois_payes_count / 12) * 100); ?>%)</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tableau détaillé des paiements - 1 ligne par mois -->
<div class="card mb-4">
    <div class="card-header" style="background: linear-gradient(135deg, #ef1c5d 0%, #f58024 100%);">
        <h5 class="mb-0 text-white">
            <i class="fas fa-list"></i> Mes paiements de scolarité (12 mois)
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th class="py-3 px-4">Mois</th>
                        <th class="py-3 text-center">Statut</th>
                        <th class="py-3 text-center">Date de paiement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mois_statuts as $mois): ?>
                    <tr>
                        <td class="py-3 px-4">
                            <div class="d-flex align-items-center">
                                <div style="width: 35px; height: 35px; background: <?php echo $mois['paye'] ? 'linear-gradient(135deg, #28a745 0%, #20c997 100%)' : 'linear-gradient(135deg, #6c757d 0%, #adb5bd 100%)'; ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                    <i class="fas fa-calendar-day text-white" style="font-size: 0.9rem;"></i>
                                </div>
                                <span class="fw-bold"><?php echo $mois['nom']; ?></span>
                            </div>
                        </td>
                        <td class="py-3 text-center">
                            <?php if ($mois['paye']): ?>
                                <span class="badge" style="background: #28a745; padding: 8px 16px; font-size: 0.85rem;">
                                    <i class="fas fa-check me-1"></i> Payé
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: #ef1c5d; padding: 8px 16px; font-size: 0.85rem;">
                                    <i class="fas fa-times me-1"></i> Non payé
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-center">
                            <?php if ($mois['paye'] && !empty($mois['paiements'])): ?>
                                <span class="text-muted"><?php echo date('d/m/Y', strtotime($mois['paiements'][0]['date_paiement'])); ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
