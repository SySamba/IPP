<?php
require_once 'config/config.php';

if (is_logged_in()) {
    log_activity('Déconnexion', 'users', $_SESSION['user_id']);
    session_unset();
    session_destroy();
}

redirect('login.php');
