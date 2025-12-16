<?php
require_once '../config/config.php';
require_role(['admin', 'scolarite']);

$page_title = 'Gestion des paiements';
$db = Database::getInstance()->getConnection();

// Traitement des paiements mensuels
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_monthly') {
        $paiements = $_POST['paiements'] ?? [];
        
        try {
            $db->beginTransaction();
            
            foreach ($paiements as $etudiant_id => $mois_data) {
                foreach ($mois_data as $mois => $paye) {
                    if ($paye == '1') {
                        // Vérifier si le paiement existe déjà
                        $stmt = $db->prepare("SELECT id FROM paiements WHERE etudiant_id = ? AND type_frais = ? AND YEAR(date_paiement) = YEAR(CURDATE())");
                        $stmt->execute([$etudiant_id, 'Scolarité - ' . $mois]);
                        $existing = $stmt->fetch();
                        
                        if (!$existing) {
                            // Créer le paiement
                            $montant = 50000; // Montant mensuel par défaut
                            $stmt = $db->prepare("INSERT INTO paiements (etudiant_id, annee_academique_id, type_frais, montant_total, montant_paye, statut, date_paiement, mode_paiement, enregistre_par) VALUES (?, (SELECT annee_academique_id FROM etudiants WHERE id = ?), ?, ?, ?, 'paye', CURDATE(), 'especes', ?)");
                            $stmt->execute([$etudiant_id, $etudiant_id, 'Scolarité - ' . $mois, $montant, $montant, $_SESSION['user_id']]);
                        }
                    }
                }
            }
            
            $db->commit();
            log_activity('Mise à jour paiements mensuels', 'paiements', 0);
            
            // Compter les paiements enregistrés
            $nb_paiements = 0;
            foreach ($paiements as $etudiant_id => $mois_data) {
                foreach ($mois_data as $mois => $paye) {
                    if ($paye == '1') $nb_paiements++;
                }
            }
            
            set_flash_message("✓ {$nb_paiements} paiement(s) enregistré(s) avec succès", 'success');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('scolarite/paiements.php?classe_id=' . ($_POST['classe_id_hidden'] ?? '') . '&annee_id=' . ($_POST['annee_id_hidden'] ?? ''));
    }
}

// Filtres
$classe_filter = $_GET['classe_id'] ?? '';
$annee_filter = $_GET['annee_id'] ?? '';

// Récupérer l'année active par défaut
if (empty($annee_filter)) {
    $stmt = $db->query("SELECT id FROM annees_academiques WHERE statut = 'active' LIMIT 1");
    $annee_active = $stmt->fetch();
    $annee_filter = $annee_active ? $annee_active['id'] : 0;
}

// Classes disponibles
$classes = $db->query("SELECT c.*, f.libelle as filiere, n.libelle as niveau FROM classes c JOIN filieres f ON c.filiere_id = f.id JOIN niveaux n ON c.niveau_id = n.id WHERE c.statut = 'active' ORDER BY f.libelle, n.ordre")->fetchAll();

// Années académiques
$annees = $db->query("SELECT * FROM annees_academiques ORDER BY date_debut DESC")->fetchAll();

// Mois de l'année scolaire
$mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

// Filtre par mois
$mois_filter = $_GET['mois'] ?? '';

// Récupérer les étudiants de la classe sélectionnée
$etudiants = [];
if (!empty($classe_filter) && !empty($annee_filter)) {
    $stmt = $db->prepare("SELECT e.*, u.nom, u.prenom, u.email 
                          FROM etudiants e 
                          JOIN users u ON e.user_id = u.id 
                          WHERE e.classe_id = ? AND e.annee_academique_id = ? AND e.statut = 'actif'
                          ORDER BY u.nom, u.prenom");
    $stmt->execute([$classe_filter, $annee_filter]);
    $etudiants = $stmt->fetchAll();
    
    // Récupérer les paiements existants
    $paiements_data = [];
    foreach ($etudiants as $etudiant) {
        $stmt = $db->prepare("SELECT type_frais FROM paiements WHERE etudiant_id = ? AND statut = 'paye' AND YEAR(date_paiement) = YEAR(CURDATE())");
        $stmt->execute([$etudiant['id']]);
        $paiements = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $paiements_data[$etudiant['id']] = $paiements;
    }
    
    // Filtrer les étudiants par mois si un mois est sélectionné
    if (!empty($mois_filter)) {
        $etudiants_filtres = [];
        foreach ($etudiants as $etudiant) {
            $is_paye = in_array('Scolarité - ' . $mois_filter, $paiements_data[$etudiant['id']] ?? []);
            // Garder tous les étudiants mais on affichera seulement le mois sélectionné
            $etudiants_filtres[] = $etudiant;
        }
        $etudiants = $etudiants_filtres;
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-money-bill"></i> Gestion des paiements mensuels</h2>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3" id="filterForm">
            <div class="col-md-4">
                <label for="classe_id" class="form-label">Classe *</label>
                <select class="form-select" id="classe_filter" name="classe_id" required onchange="this.form.submit()">
                    <option value="">Sélectionner une classe...</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($classe['filiere'] . ' - ' . $classe['niveau'] . ' - ' . $classe['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="annee_id" class="form-label">Année académique *</label>
                <select class="form-select" id="annee_filter" name="annee_id" required onchange="this.form.submit()">
                    <?php foreach ($annees as $annee): ?>
                        <option value="<?php echo $annee['id']; ?>" <?php echo $annee_filter == $annee['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($annee['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="mois_filter" class="form-label">Mois</label>
                <select class="form-select" id="mois_filter" name="mois" onchange="this.form.submit()">
                    <option value="">Tous les mois</option>
                    <?php foreach ($mois as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($_GET['mois'] ?? '') === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($etudiants)): ?>
<!-- Tableau des paiements -->
<form method="POST" class="no-confirm">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="update_monthly">
    <input type="hidden" name="classe_id_hidden" value="<?php echo $classe_filter; ?>">
    <input type="hidden" name="annee_id_hidden" value="<?php echo $annee_filter; ?>">
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 200px;">Étudiant</th>
                            <?php 
                            // Si un mois est filtré, afficher seulement ce mois
                            $mois_afficher = !empty($mois_filter) ? [$mois_filter] : $mois;
                            foreach ($mois_afficher as $m): ?>
                                <th class="text-center" style="min-width: 80px;"><?php echo $m; ?></th>
                            <?php endforeach; ?>
                            <th class="text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etudiants as $etudiant): ?>
                        <tr>
                            <td>
                                <strong><?php echo escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo escape_html($etudiant['matricule']); ?></small>
                            </td>
                            <?php 
                            $total_paye = 0;
                            // Calculer le total sur tous les mois
                            foreach ($mois as $m_calc): 
                                if (in_array('Scolarité - ' . $m_calc, $paiements_data[$etudiant['id']] ?? [])) $total_paye++;
                            endforeach;
                            
                            // Afficher seulement les mois filtrés
                            foreach ($mois_afficher as $m): 
                                $is_paye = in_array('Scolarité - ' . $m, $paiements_data[$etudiant['id']] ?? []);
                            ?>
                                <td class="text-center">
                                    <div class="form-check d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" 
                                               name="paiements[<?php echo $etudiant['id']; ?>][<?php echo $m; ?>]" 
                                               value="1" 
                                               <?php echo $is_paye ? 'checked' : ''; ?>
                                               <?php echo $is_paye ? 'disabled' : ''; ?>>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $total_paye == 12 ? 'success' : ($total_paye > 6 ? 'warning' : 'danger'); ?>">
                                    <?php echo $total_paye; ?>/12
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-save"></i> Enregistrer les paiements
                </button>
            </div>
        </div>
    </div>
</form>
<?php else: ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Veuillez sélectionner une classe pour afficher les étudiants et gérer leurs paiements.
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
