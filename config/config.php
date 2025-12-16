<?php
/**
 * Configuration générale de l'application
 * Institut Polytechnique Panafricain
 */

// Démarrage de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration des chemins
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('ASSETS_PATH', BASE_PATH . '/assets');

// URL de base
define('BASE_URL', 'http://localhost/ipp');

// Configuration de l'application
define('APP_NAME', 'Institut Polytechnique Panafricain');
define('APP_SHORT_NAME', 'IPP');
define('APP_VERSION', '1.0.0');

// Configuration de sécurité
define('SESSION_LIFETIME', 3600); // 1 heure
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 6);

// Configuration des uploads
define('MAX_FILE_SIZE', 5242880); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Configuration des rôles
define('ROLES', [
    'admin' => 'Administrateur',
    'scolarite' => 'Scolarité',
    'enseignant' => 'Enseignant',
    'etudiant' => 'Étudiant',
    'direction' => 'Direction'
]);

// Fuseau horaire
date_default_timezone_set('Africa/Abidjan');

// Affichage des erreurs (à désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclusion des fichiers nécessaires
require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/security.php';
