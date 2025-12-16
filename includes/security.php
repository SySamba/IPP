<?php
/**
 * Fonctions de sécurité
 * Institut Polytechnique Panafricain
 */

/**
 * Génère un token CSRF
 */
function generate_csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Vérifie le token CSRF
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Affiche un champ CSRF caché
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Vérifie le token CSRF depuis POST
 */
function check_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            die('Token CSRF invalide');
        }
    }
}

/**
 * Protège contre les injections XSS
 */
function escape_html($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Vérifie l'authentification
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash_message('Vous devez être connecté pour accéder à cette page', 'danger');
        redirect('login.php');
    }
}

/**
 * Vérifie le rôle de l'utilisateur
 */
function require_role($role) {
    require_login();
    
    if (!has_role($role)) {
        set_flash_message('Vous n\'avez pas les permissions nécessaires', 'danger');
        redirect('index.php');
    }
}

/**
 * Hash un mot de passe
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Vérifie un mot de passe
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Valide un email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide un numéro de téléphone
 */
function validate_phone($phone) {
    return preg_match('/^[\+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,9}$/', $phone);
}

/**
 * Nettoie une chaîne pour éviter les injections SQL (utiliser PDO à la place)
 */
function sanitize_string($string) {
    return strip_tags(trim($string));
}

/**
 * Vérifie la force d'un mot de passe
 */
function validate_password_strength($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Le mot de passe doit contenir au moins " . PASSWORD_MIN_LENGTH . " caractères";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une majuscule";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une minuscule";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un chiffre";
    }
    
    return $errors;
}

/**
 * Limite les tentatives de connexion
 */
function check_login_attempts($username) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    $attempts = $_SESSION['login_attempts'];
    
    if (isset($attempts[$username])) {
        $last_attempt = $attempts[$username]['last_attempt'];
        $count = $attempts[$username]['count'];
        
        // Réinitialiser après 15 minutes
        if (time() - $last_attempt > 900) {
            unset($_SESSION['login_attempts'][$username]);
            return true;
        }
        
        // Bloquer après 5 tentatives
        if ($count >= 5) {
            return false;
        }
    }
    
    return true;
}

/**
 * Enregistre une tentative de connexion
 */
function record_login_attempt($username, $success = false) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    if ($success) {
        unset($_SESSION['login_attempts'][$username]);
    } else {
        if (!isset($_SESSION['login_attempts'][$username])) {
            $_SESSION['login_attempts'][$username] = [
                'count' => 0,
                'last_attempt' => time()
            ];
        }
        
        $_SESSION['login_attempts'][$username]['count']++;
        $_SESSION['login_attempts'][$username]['last_attempt'] = time();
    }
}

/**
 * Vérifie la validité d'une session
 */
function validate_session() {
    if (!is_logged_in()) {
        return false;
    }
    
    // Vérifier l'expiration de la session
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}
