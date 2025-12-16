<?php
require_once '../config/config.php';
require_role('admin');

$page_title = 'Gestion des emplois du temps';
$db = Database::getInstance()->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $classe_id = intval($_POST['classe_id']);
        $matiere_id = intval($_POST['matiere_id']);
        $enseignant_id = intval($_POST['enseignant_id']);
        $salle_id = !empty($_POST['salle_id']) ? intval($_POST['salle_id']) : null;
        $jour = clean_input($_POST['jour']);
        $heure_debut = clean_input($_POST['heure_debut']);
        $heure_fin = clean_input($_POST['heure_fin']);
        $date_debut = clean_input($_POST['date_debut']);
        $date_fin = !empty($_POST['date_fin']) ? clean_input($_POST['date_fin']) : null;
        $annee_academique_id = intval($_POST['annee_academique_id']);
        
        try {
            // Validation 1: Vérifier que l'enseignant n'a pas déjà un cours à cette heure
            $check_sql = "SELECT COUNT(*) as count FROM emplois_du_temps 
                         WHERE enseignant_id = ? AND jour = ? 
                         AND ((heure_debut < ? AND heure_fin > ?) OR (heure_debut < ? AND heure_fin > ?) OR (heure_debut >= ? AND heure_fin <= ?))
                         AND annee_academique_id = ?";
            $check_params = [$enseignant_id, $jour, $heure_fin, $heure_debut, $heure_fin, $heure_debut, $heure_debut, $heure_fin, $annee_academique_id];
            
            if ($action === 'edit') {
                $check_sql .= " AND id != ?";
                $check_params[] = $id;
            }
            
            $stmt_check = $db->prepare($check_sql);
            $stmt_check->execute($check_params);
            $result = $stmt_check->fetch();
            
            if ($result['count'] > 0) {
                set_flash_message('Erreur: Cet enseignant a déjà un cours à cette heure le ' . $jour, 'danger');
                redirect('admin/emplois-du-temps.php');
                exit;
            }
            
            // Validation 2: Vérifier que la salle n'est pas déjà occupée
            if ($salle_id) {
                $check_sql = "SELECT COUNT(*) as count FROM emplois_du_temps 
                             WHERE salle_id = ? AND jour = ? 
                             AND ((heure_debut < ? AND heure_fin > ?) OR (heure_debut < ? AND heure_fin > ?) OR (heure_debut >= ? AND heure_fin <= ?))
                             AND annee_academique_id = ?";
                $check_params = [$salle_id, $jour, $heure_fin, $heure_debut, $heure_fin, $heure_debut, $heure_debut, $heure_fin, $annee_academique_id];
                
                if ($action === 'edit') {
                    $check_sql .= " AND id != ?";
                    $check_params[] = $id;
                }
                
                $stmt_check = $db->prepare($check_sql);
                $stmt_check->execute($check_params);
                $result = $stmt_check->fetch();
                
                if ($result['count'] > 0) {
                    set_flash_message('Erreur: Cette salle est déjà occupée à cette heure le ' . $jour, 'danger');
                    redirect('admin/emplois-du-temps.php');
                    exit;
                }
            }
            
            // Validation 3: Vérifier que la classe n'a pas déjà un cours à cette heure
            $check_sql = "SELECT COUNT(*) as count FROM emplois_du_temps 
                         WHERE classe_id = ? AND jour = ? 
                         AND ((heure_debut < ? AND heure_fin > ?) OR (heure_debut < ? AND heure_fin > ?) OR (heure_debut >= ? AND heure_fin <= ?))
                         AND annee_academique_id = ?";
            $check_params = [$classe_id, $jour, $heure_fin, $heure_debut, $heure_fin, $heure_debut, $heure_debut, $heure_fin, $annee_academique_id];
            
            if ($action === 'edit') {
                $check_sql .= " AND id != ?";
                $check_params[] = $id;
            }
            
            $stmt_check = $db->prepare($check_sql);
            $stmt_check->execute($check_params);
            $result = $stmt_check->fetch();
            
            if ($result['count'] > 0) {
                set_flash_message('Erreur: Cette classe a déjà un cours à cette heure le ' . $jour, 'danger');
                redirect('admin/emplois-du-temps.php');
                exit;
            }
            
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO emplois_du_temps (classe_id, matiere_id, enseignant_id, salle_id, jour, heure_debut, heure_fin, date_debut, date_fin, annee_academique_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$classe_id, $matiere_id, $enseignant_id, $salle_id, $jour, $heure_debut, $heure_fin, $date_debut, $date_fin, $annee_academique_id]);
                
                log_activity('Création emploi du temps', 'emplois_du_temps', $db->lastInsertId());
                set_flash_message('Cours ajouté à l\'emploi du temps avec succès', 'success');
            } else {
                $stmt = $db->prepare("UPDATE emplois_du_temps SET classe_id = ?, matiere_id = ?, enseignant_id = ?, salle_id = ?, jour = ?, heure_debut = ?, heure_fin = ?, date_debut = ?, date_fin = ?, annee_academique_id = ? WHERE id = ?");
                $stmt->execute([$classe_id, $matiere_id, $enseignant_id, $salle_id, $jour, $heure_debut, $heure_fin, $date_debut, $date_fin, $annee_academique_id, $id]);
                
                log_activity('Modification emploi du temps', 'emplois_du_temps', $id);
                set_flash_message('Cours modifié avec succès', 'success');
            }
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/emplois-du-temps.php');
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        
        try {
            $stmt = $db->prepare("DELETE FROM emplois_du_temps WHERE id = ?");
            $stmt->execute([$id]);
            
            log_activity('Suppression emploi du temps', 'emplois_du_temps', $id);
            set_flash_message('Cours supprimé avec succès', 'success');
        } catch (Exception $e) {
            set_flash_message('Erreur: ' . $e->getMessage(), 'danger');
        }
        
        redirect('admin/emplois-du-temps.php');
    }
}

// Filtres
$classe_filter = $_GET['classe_id'] ?? '';
$annee_filter = $_GET['annee_id'] ?? '';

// Année active par défaut
if (!$annee_filter) {
    $stmt = $db->query("SELECT id FROM annees_academiques WHERE statut = 'active' LIMIT 1");
    $annee_active = $stmt->fetch();
    if ($annee_active) {
        $annee_filter = $annee_active['id'];
    }
}

// Vérifier et créer la table salles si elle n'existe pas
try {
    // Vérifier si les colonnes nécessaires existent
    $columns = $db->query("SHOW COLUMNS FROM salles")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('code', $columns)) {
        $db->exec("ALTER TABLE salles ADD COLUMN code VARCHAR(20) UNIQUE AFTER id");
        // Ajouter des codes par défaut aux salles existantes
        $salles_existantes = $db->query("SELECT id FROM salles")->fetchAll();
        foreach ($salles_existantes as $s) {
            $db->exec("UPDATE salles SET code = 'SALLE_" . $s['id'] . "' WHERE id = " . $s['id']);
        }
    }
    
    if (!in_array('batiment', $columns)) {
        $db->exec("ALTER TABLE salles ADD COLUMN batiment VARCHAR(50) AFTER type");
    }
    
    $result = $db->query("SELECT COUNT(*) as count FROM salles LIMIT 1")->fetch();
    // Si la table existe mais est vide, ajouter des salles
    if ($result['count'] == 0) {
        $db->exec("INSERT INTO salles (code, libelle, capacite, type, batiment, statut) VALUES 
            ('A101', 'Salle A101', 40, 'cours', 'Batiment A', 'active'),
            ('A102', 'Salle A102', 40, 'cours', 'Batiment A', 'active'),
            ('B201', 'Salle B201', 30, 'tp', 'Batiment B', 'active'),
            ('B202', 'Salle B202', 30, 'tp', 'Batiment B', 'active'),
            ('AMPHI1', 'Amphi 1', 100, 'amphi', 'Batiment C', 'active'),
            ('LABO_INFO', 'Labo Info', 25, 'labo', 'Batiment B', 'active')");
    }
} catch (Exception $e) {
    // Créer la table salles
    $db->exec("CREATE TABLE IF NOT EXISTS salles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        libelle VARCHAR(100) NOT NULL,
        capacite INT DEFAULT 30,
        type ENUM('cours', 'tp', 'amphi', 'labo') DEFAULT 'cours',
        batiment VARCHAR(50),
        statut ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Ajouter des salles par défaut
    $db->exec("INSERT INTO salles (code, libelle, capacite, type, batiment, statut) VALUES 
        ('A101', 'Salle A101', 40, 'cours', 'Batiment A', 'active'),
        ('A102', 'Salle A102', 40, 'cours', 'Batiment A', 'active'),
        ('B201', 'Salle B201', 30, 'tp', 'Batiment B', 'active'),
        ('B202', 'Salle B202', 30, 'tp', 'Batiment B', 'active'),
        ('AMPHI1', 'Amphi 1', 100, 'amphi', 'Batiment C', 'active'),
        ('LABO_INFO', 'Labo Info', 25, 'labo', 'Batiment B', 'active')");
}

// Récupérer les données pour les filtres
$annees = $db->query("SELECT * FROM annees_academiques ORDER BY libelle DESC")->fetchAll();
$classes = $db->query("SELECT c.*, f.libelle as filiere, n.libelle as niveau FROM classes c JOIN filieres f ON c.filiere_id = f.id JOIN niveaux n ON c.niveau_id = n.id WHERE c.statut = 'active' ORDER BY f.libelle, n.ordre")->fetchAll();
$matieres = $db->query("SELECT * FROM matieres WHERE statut = 'active' ORDER BY libelle")->fetchAll();
$enseignants = $db->query("SELECT u.id, u.nom, u.prenom FROM users u WHERE u.role = 'enseignant' AND u.statut = 'actif' ORDER BY u.nom, u.prenom")->fetchAll();

// Récupérer les salles avec gestion d'erreur
try {
    $salles = $db->query("SELECT id, libelle, capacite, type, statut FROM salles WHERE statut IN ('active', 'disponible') ORDER BY libelle")->fetchAll();
    
    // Debug: vérifier si on a des salles
    if (empty($salles)) {
        error_log("ATTENTION: Aucune salle active trouvée dans la base de données");
    } else {
        error_log("Salles trouvées: " . count($salles));
    }
} catch (Exception $e) {
    $salles = [];
    error_log("Erreur récupération salles: " . $e->getMessage());
}

// Récupérer les emplois du temps
$sql = "SELECT edt.*, c.libelle as classe, f.libelle as filiere, n.libelle as niveau,
        m.libelle as matiere, m.code as matiere_code,
        u.nom as enseignant_nom, u.prenom as enseignant_prenom,
        s.libelle as salle
        FROM emplois_du_temps edt
        JOIN classes c ON edt.classe_id = c.id
        JOIN filieres f ON c.filiere_id = f.id
        JOIN niveaux n ON c.niveau_id = n.id
        JOIN matieres m ON edt.matiere_id = m.id
        JOIN users u ON edt.enseignant_id = u.id
        LEFT JOIN salles s ON edt.salle_id = s.id
        WHERE edt.annee_academique_id = ?";

$params = [$annee_filter];

if ($classe_filter) {
    $sql .= " AND edt.classe_id = ?";
    $params[] = $classe_filter;
}

$sql .= " ORDER BY FIELD(edt.jour, 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'), edt.heure_debut";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$emplois = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-calendar-alt"></i> Gestion des emplois du temps</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#edtModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Ajouter un cours
            </button>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Année académique</label>
                <select class="form-select" name="annee_id" onchange="this.form.submit()">
                    <?php foreach ($annees as $annee): ?>
                        <option value="<?php echo $annee['id']; ?>" <?php echo $annee_filter == $annee['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($annee['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Classe</label>
                <select class="form-select" name="classe_id" onchange="this.form.submit()">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($classe['filiere'] . ' - ' . $classe['niveau'] . ' - ' . $classe['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Emploi du temps -->
<div class="card">
    <div class="card-body">
        <?php if (empty($emplois)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Aucun cours dans l'emploi du temps.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Jour</th>
                            <th>Horaire</th>
                            <th>Classe</th>
                            <th>Matière</th>
                            <th>Enseignant</th>
                            <th>Salle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emplois as $edt): ?>
                        <tr>
                            <td><span class="badge bg-primary"><?php echo ucfirst($edt['jour']); ?></span></td>
                            <td><?php echo substr($edt['heure_debut'], 0, 5); ?> - <?php echo substr($edt['heure_fin'], 0, 5); ?></td>
                            <td><?php echo escape_html($edt['filiere'] . ' - ' . $edt['niveau'] . ' - ' . $edt['classe']); ?></td>
                            <td><strong><?php echo escape_html($edt['matiere']); ?></strong></td>
                            <td><?php echo escape_html($edt['enseignant_prenom'] . ' ' . $edt['enseignant_nom']); ?></td>
                            <td><?php echo escape_html($edt['salle'] ?: '-'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="editEdt(<?php echo htmlspecialchars(json_encode($edt)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce cours ?')">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $edt['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Emploi du temps -->
<div class="modal fade" id="edtModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Ajouter un cours</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="edtForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="edt_id">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="annee_academique_id" class="form-label">Année académique *</label>
                            <select class="form-select" id="annee_academique_id" name="annee_academique_id" required>
                                <?php foreach ($annees as $annee): ?>
                                    <option value="<?php echo $annee['id']; ?>" <?php echo $annee['statut'] === 'active' ? 'selected' : ''; ?>>
                                        <?php echo escape_html($annee['libelle']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="classe_id" class="form-label">Classe *</label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php echo escape_html($classe['filiere'] . ' - ' . $classe['niveau'] . ' - ' . $classe['libelle']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="matiere_id" class="form-label">Matière *</label>
                            <select class="form-select" id="matiere_id" name="matiere_id" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($matieres as $matiere): ?>
                                    <option value="<?php echo $matiere['id']; ?>">
                                        <?php echo escape_html($matiere['libelle']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="enseignant_id" class="form-label">Enseignant *</label>
                            <select class="form-select" id="enseignant_id" name="enseignant_id" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?php echo $enseignant['id']; ?>">
                                        <?php echo escape_html($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="jour" class="form-label">Jour *</label>
                            <select class="form-select" id="jour" name="jour" required>
                                <option value="lundi">Lundi</option>
                                <option value="mardi">Mardi</option>
                                <option value="mercredi">Mercredi</option>
                                <option value="jeudi">Jeudi</option>
                                <option value="vendredi">Vendredi</option>
                                <option value="samedi">Samedi</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="salle_id" class="form-label">Salle</label>
                            <select class="form-select" id="salle_id" name="salle_id">
                                <option value="">Aucune</option>
                                <?php 
                                // Recharger les salles directement ici pour être sûr
                                $salles_modal = $db->query("SELECT id, libelle, capacite FROM salles WHERE statut IN ('active', 'disponible') ORDER BY libelle")->fetchAll();
                                if (!empty($salles_modal)): 
                                    foreach ($salles_modal as $salle): 
                                ?>
                                    <option value="<?php echo $salle['id']; ?>">
                                        <?php echo escape_html($salle['libelle'] . ' (' . $salle['capacite'] . ' places)'); ?>
                                    </option>
                                <?php 
                                    endforeach;
                                else: 
                                ?>
                                    <option value="" disabled>Aucune salle disponible</option>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted">Allez dans Admin → Salles pour ajouter des salles</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="heure_debut" class="form-label">Heure début *</label>
                            <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="heure_fin" class="form-label">Heure fin *</label>
                            <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date_debut" class="form-label">Date début *</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date_fin" class="form-label">Date fin</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin">
                            <small class="text-muted">Optionnel - Laisser vide pour un cours permanent</small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('edtForm').reset();
    document.getElementById('action').value = 'add';
    document.getElementById('edt_id').value = '';
    document.getElementById('modalTitle').textContent = 'Ajouter un cours';
}

function editEdt(edt) {
    document.getElementById('action').value = 'edit';
    document.getElementById('edt_id').value = edt.id;
    document.getElementById('annee_academique_id').value = edt.annee_academique_id;
    document.getElementById('classe_id').value = edt.classe_id;
    document.getElementById('matiere_id').value = edt.matiere_id;
    document.getElementById('enseignant_id').value = edt.enseignant_id;
    document.getElementById('salle_id').value = edt.salle_id || '';
    document.getElementById('jour').value = edt.jour;
    document.getElementById('heure_debut').value = edt.heure_debut;
    document.getElementById('heure_fin').value = edt.heure_fin;
    document.getElementById('date_debut').value = edt.date_debut || '';
    document.getElementById('date_fin').value = edt.date_fin || '';
    document.getElementById('modalTitle').textContent = 'Modifier le cours';
    
    new bootstrap.Modal(document.getElementById('edtModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
