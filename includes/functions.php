<?php
/**
 * Fonctions utilitaires
 * Institut Polytechnique Panafricain
 */

/**
 * Nettoie et sécurise les données d'entrée
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Redirige vers une page
 */
function redirect($url) {
    header("Location: " . BASE_URL . "/" . $url);
    exit();
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique
 */
function has_role($role) {
    if (!is_logged_in()) {
        return false;
    }
    
    if (is_array($role)) {
        return in_array($_SESSION['user_role'], $role);
    }
    
    return $_SESSION['user_role'] === $role;
}

/**
 * Récupère l'utilisateur connecté
 */
function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['user_role'],
        'nom' => $_SESSION['user_nom'] ?? '',
        'prenom' => $_SESSION['user_prenom'] ?? ''
    ];
}

/**
 * Affiche un message flash
 */
function set_flash_message($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Récupère et supprime le message flash
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Formate une date
 */
function format_date($date, $format = 'd/m/Y') {
    if (empty($date)) {
        return '';
    }
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Génère un matricule unique pour un étudiant
 */
function generate_matricule($annee = null) {
    if ($annee === null) {
        $annee = date('Y');
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT COUNT(*) as total FROM etudiants WHERE YEAR(created_at) = $annee");
    $result = $stmt->fetch();
    $numero = str_pad($result['total'] + 1, 4, '0', STR_PAD_LEFT);
    
    return 'IPP' . $annee . $numero;
}

/**
 * Calcule la moyenne d'un tableau de notes
 */
function calculate_moyenne($notes) {
    if (empty($notes)) {
        return 0;
    }
    
    $total_points = 0;
    $total_coef = 0;
    
    foreach ($notes as $note) {
        $note_sur_20 = ($note['note'] / $note['note_sur']) * 20;
        $coef = $note['coefficient'] ?? 1;
        $total_points += $note_sur_20 * $coef;
        $total_coef += $coef;
    }
    
    return $total_coef > 0 ? round($total_points / $total_coef, 2) : 0;
}

/**
 * Détermine la mention selon la moyenne
 */
function get_mention($moyenne) {
    if ($moyenne >= 16) {
        return 'Très Bien';
    } elseif ($moyenne >= 14) {
        return 'Bien';
    } elseif ($moyenne >= 12) {
        return 'Assez Bien';
    } elseif ($moyenne >= 10) {
        return 'Passable';
    } else {
        return 'Insuffisant';
    }
}

/**
 * Upload un fichier
 */
function upload_file($file, $destination_folder, $allowed_types = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erreur lors du téléchargement du fichier'];
    }
    
    // Vérifier la taille
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Le fichier est trop volumineux (max 5 MB)'];
    }
    
    // Vérifier le type
    if ($allowed_types !== null && !in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé'];
    }
    
    // Créer le dossier si nécessaire
    $upload_path = UPLOADS_PATH . '/' . $destination_folder;
    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0755, true);
    }
    
    // Générer un nom unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_path . '/' . $filename;
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $destination_folder . '/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier'];
}

/**
 * Enregistre une action dans les logs
 */
function log_activity($action, $table_name = null, $record_id = null, $details = null) {
    if (!is_logged_in()) {
        return;
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $table_name,
            $record_id,
            $details,
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        // Ignorer les erreurs de log
    }
}

/**
 * Pagination
 */
function paginate($total_items, $items_per_page, $current_page) {
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    
    return [
        'total_items' => $total_items,
        'items_per_page' => $items_per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'offset' => $offset
    ];
}

/**
 * Vérifie les conflits d'emploi du temps
 */
function check_schedule_conflict($classe_id, $jour, $heure_debut, $heure_fin, $salle_id = null, $exclude_id = null) {
    $db = Database::getInstance()->getConnection();
    
    $sql = "SELECT et.*, c.libelle as classe, s.libelle as salle 
            FROM emplois_temps et
            JOIN classes c ON et.classe_id = c.id
            LEFT JOIN salles s ON et.salle_id = s.id
            WHERE et.jour = ? 
            AND (
                (et.heure_debut < ? AND et.heure_fin > ?) OR
                (et.heure_debut < ? AND et.heure_fin > ?) OR
                (et.heure_debut >= ? AND et.heure_fin <= ?)
            )
            AND (et.classe_id = ? OR et.salle_id = ?)";
    
    $params = [$jour, $heure_fin, $heure_debut, $heure_fin, $heure_debut, $heure_debut, $heure_fin, $classe_id, $salle_id];
    
    if ($exclude_id) {
        $sql .= " AND et.id != ?";
        $params[] = $exclude_id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}
