<?php
require_once '../config/config.php';
require_role(['admin', 'scolarite']);

$page_title = 'Génération des bulletins';
$db = Database::getInstance()->getConnection();

// Filtres
$classe_filter = $_GET['classe_id'] ?? '';
$annee_filter = $_GET['annee_id'] ?? '';
$periode_filter = $_GET['periode_id'] ?? '1'; // Par défaut semestre 1

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

// Récupérer les étudiants et leurs bulletins
$etudiants = [];
if (!empty($classe_filter) && !empty($annee_filter) && !empty($periode_filter)) {
    $stmt = $db->prepare("SELECT e.*, u.nom, u.prenom, u.email 
                          FROM etudiants e 
                          JOIN users u ON e.user_id = u.id 
                          WHERE e.classe_id = ? AND e.annee_academique_id = ? AND e.statut = 'actif'
                          ORDER BY u.nom, u.prenom");
    $stmt->execute([$classe_filter, $annee_filter]);
    $etudiants = $stmt->fetchAll();
    
    // Pour chaque étudiant, calculer ses moyennes
    foreach ($etudiants as &$etudiant) {
        // Vérifier si la colonne semestre existe
        $has_semestre = false;
        try {
            $db->query("SELECT semestre FROM notes LIMIT 1");
            $has_semestre = true;
        } catch (Exception $e) {
            // La colonne n'existe pas encore
        }
        
        // Récupérer toutes les notes de l'étudiant pour ce semestre
        if ($has_semestre) {
            $stmt = $db->prepare("SELECT n.*, cm.coefficient as coef_matiere, m.libelle as matiere
                                  FROM notes n
                                  JOIN classe_matieres cm ON n.classe_matiere_id = cm.id
                                  JOIN matieres m ON cm.matiere_id = m.id
                                  WHERE n.etudiant_id = ? AND cm.classe_id = ? AND n.semestre = ?
                                  ORDER BY m.libelle");
            $stmt->execute([$etudiant['id'], $classe_filter, $periode_filter]);
        } else {
            $stmt = $db->prepare("SELECT n.*, cm.coefficient as coef_matiere, m.libelle as matiere
                                  FROM notes n
                                  JOIN classe_matieres cm ON n.classe_matiere_id = cm.id
                                  JOIN matieres m ON cm.matiere_id = m.id
                                  WHERE n.etudiant_id = ? AND cm.classe_id = ?
                                  ORDER BY m.libelle");
            $stmt->execute([$etudiant['id'], $classe_filter]);
        }
        $notes = $stmt->fetchAll();
        
        // Calculer la moyenne par matière
        $matieres_moyennes = [];
        foreach ($notes as $note) {
            if (!isset($matieres_moyennes[$note['matiere']])) {
                $matieres_moyennes[$note['matiere']] = [
                    'notes' => [],
                    'coef' => $note['coef_matiere']
                ];
            }
            // Convertir la note sur 20
            $note_sur_20 = ($note['note'] / $note['note_sur']) * 20;
            $matieres_moyennes[$note['matiere']]['notes'][] = $note_sur_20 * $note['coefficient'];
            $matieres_moyennes[$note['matiere']]['total_coef'] = ($matieres_moyennes[$note['matiere']]['total_coef'] ?? 0) + $note['coefficient'];
        }
        
        // Calculer moyenne générale
        $somme_moyennes = 0;
        $somme_coefs = 0;
        $etudiant['matieres'] = [];
        
        foreach ($matieres_moyennes as $matiere => $data) {
            if ($data['total_coef'] > 0) {
                $moyenne_matiere = array_sum($data['notes']) / $data['total_coef'];
                $etudiant['matieres'][$matiere] = [
                    'moyenne' => $moyenne_matiere,
                    'coef' => $data['coef']
                ];
                $somme_moyennes += $moyenne_matiere * $data['coef'];
                $somme_coefs += $data['coef'];
            }
        }
        
        $etudiant['moyenne_generale'] = $somme_coefs > 0 ? $somme_moyennes / $somme_coefs : 0;
        
        // Récupérer les absences
        $stmt = $db->prepare("SELECT COUNT(*) as nb FROM absences WHERE etudiant_id = ?");
        $stmt->execute([$etudiant['id']]);
        $etudiant['nb_absences'] = $stmt->fetch()['nb'];
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-file-alt"></i> Génération des bulletins</h2>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="classe_id" class="form-label">Classe *</label>
                <select class="form-select" id="classe_filter" name="classe_id" required>
                    <option value="">Sélectionner...</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($classe['filiere'] . ' - ' . $classe['niveau'] . ' - ' . $classe['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="annee_id" class="form-label">Année académique *</label>
                <select class="form-select" id="annee_filter" name="annee_id" required>
                    <?php foreach ($annees as $annee): ?>
                        <option value="<?php echo $annee['id']; ?>" <?php echo $annee_filter == $annee['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($annee['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="periode_filter" class="form-label">Semestre *</label>
                <select class="form-select" id="periode_filter" name="periode_id" required>
                    <option value="">Sélectionner...</option>
                    <option value="1" <?php echo $periode_filter == '1' ? 'selected' : ''; ?>>Semestre 1</option>
                    <option value="2" <?php echo $periode_filter == '2' ? 'selected' : ''; ?>>Semestre 2</option>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($etudiants)): ?>
<!-- Liste des bulletins -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Matricule</th>
                        <th>Nom complet</th>
                        <th>Moyenne générale</th>
                        <th>Absences</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($etudiants as $etudiant): ?>
                    <tr>
                        <td><?php echo escape_html($etudiant['matricule']); ?></td>
                        <td><strong><?php echo escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></strong></td>
                        <td>
                            <span class="badge bg-<?php echo $etudiant['moyenne_generale'] >= 10 ? 'success' : 'danger'; ?> fs-6">
                                <?php echo number_format($etudiant['moyenne_generale'], 2); ?>/20
                            </span>
                        </td>
                        <td><?php echo $etudiant['nb_absences']; ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/scolarite/bulletin-pdf.php?etudiant_id=<?php echo $etudiant['id']; ?>&periode_id=<?php echo $periode_filter; ?>" 
                               class="btn btn-sm btn-primary" target="_blank">
                                <i class="fas fa-file-pdf"></i> Générer PDF
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Veuillez sélectionner une classe, une année et une période pour afficher les bulletins.
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
