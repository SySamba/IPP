<?php
if (!defined('BASE_PATH')) {
    die('Accès direct non autorisé');
}

require_login();
$current_user = get_logged_user();
$page_title = $page_title ?? 'Tableau de bord';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/index.php">
                <i class="fas fa-graduation-cap"></i> <?php echo APP_SHORT_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php">
                            <i class="fas fa-home"></i> Accueil
                        </a>
                    </li>
                    
                    <?php if (has_role(['admin'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> Administration
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/users.php">Utilisateurs</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/enseignants.php">Enseignants</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/filieres.php">Filières</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/classes.php">Classes</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/matieres.php">Matières</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/evaluations.php">Évaluations</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/salles.php">Salles</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/emplois-du-temps.php">Emplois du temps</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/annees.php">Années académiques</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (has_role(['admin', 'scolarite'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarScolarite" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-graduate"></i> Scolarité
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/scolarite/etudiants.php">
                                <i class="fas fa-users"></i> Étudiants</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/scolarite/inscriptions.php">
                                <i class="fas fa-user-plus"></i> Inscriptions</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/scolarite/bulletins.php">
                                <i class="fas fa-file-alt"></i> Bulletins</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/scolarite/absences.php">
                                <i class="fas fa-calendar-times"></i> Absences</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/scolarite/paiements.php">
                                <i class="fas fa-money-bill"></i> Paiements</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    
                    
                    <?php if (has_role(['admin', 'direction'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/bi/dashboard.php">
                            <i class="fas fa-chart-bar"></i> BI Dashboard
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarUser" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo $current_user['prenom']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/profile.php">
                                <i class="fas fa-user"></i> Mon profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <?php if (has_role(['etudiant', 'enseignant'])): ?>
    <div class="d-flex" style="margin-top: 56px;">
        <!-- Sidebar Moderne -->
        <div class="sidebar border-end" style="width: 280px; min-height: calc(100vh - 56px); position: fixed; top: 56px; left: 0; overflow-y: auto; background: linear-gradient(180deg, #ef1c5d 0%, #c71450 100%);">
            <div class="p-3">
                <?php if (has_role('enseignant')): ?>
                <div class="sidebar-header mb-4 pb-3" style="border-bottom: 1px solid rgba(255,255,255,0.2);">
                    <div class="d-flex align-items-center">
                        <div style="width: 45px; height: 45px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                            <i class="fas fa-chalkboard-teacher text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 text-white fw-bold" style="font-size: 0.9rem;">ESPACE ENSEIGNANT</h6>
                            <small style="color: rgba(255,255,255,0.7); font-size: 0.75rem;">Gestion pédagogique</small>
                        </div>
                    </div>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/enseignant/classes.php">
                        <i class="fas fa-school"></i> Mes Classes
                    </a>
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/enseignant/matieres.php">
                        <i class="fas fa-book"></i> Mes Matières
                    </a>
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/enseignant/notes.php">
                        <i class="fas fa-edit"></i> Saisie des notes
                    </a>
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/enseignant/emploi-du-temps.php">
                        <i class="fas fa-calendar-alt"></i> Emploi du temps
                    </a>
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/enseignant/absences.php">
                        <i class="fas fa-calendar-times"></i> Absences
                    </a>
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/enseignant/annonces.php">
                        <i class="fas fa-bullhorn"></i> Annonces d'absence
                    </a>
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/enseignant/documents.php">
                        <i class="fas fa-file-upload"></i> Documents
                    </a>
                </nav>
                <?php endif; ?>
                
                <?php if (has_role('etudiant')): ?>
                <div class="sidebar-header mb-4 pb-3" style="border-bottom: 1px solid rgba(255,255,255,0.2);">
                    <div class="d-flex align-items-center">
                        <div style="width: 45px; height: 45px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                            <i class="fas fa-user-graduate text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 text-white fw-bold" style="font-size: 0.9rem;">MON ESPACE</h6>
                            <small style="color: rgba(255,255,255,0.7); font-size: 0.75rem;">Suivi académique</small>
                        </div>
                    </div>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/etudiant/notes.php">
                        <i class="fas fa-chart-line"></i> Mes notes
                    </a>
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/etudiant/bulletins.php">
                        <i class="fas fa-file-alt"></i> Bulletins
                    </a>
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/etudiant/emploi-du-temps.php">
                        <i class="fas fa-calendar-alt"></i> Emploi du temps
                    </a>
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/etudiant/absences.php">
                        <i class="fas fa-calendar-times"></i> Absences
                    </a>
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/etudiant/documents.php">
                        <i class="fas fa-folder-open"></i> Documents
                    </a>
                    <a class="nav-link sidebar-link" href="<?php echo BASE_URL; ?>/etudiant/paiements.php">
                        <i class="fas fa-money-bill"></i> Paiements
                    </a>
                </nav>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-grow-1" style="margin-left: 280px;">
            <div class="container-fluid mt-4">
                <?php
                $flash = get_flash_message();
                if ($flash):
                    $alert_class = 'alert-' . ($flash['type'] === 'danger' ? 'danger' : ($flash['type'] === 'success' ? 'success' : 'info'));
                ?>
                <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
    <?php else: ?>
    <div class="container-fluid mt-4" style="padding-top: 56px;">
        <?php
        $flash = get_flash_message();
        if ($flash):
            $alert_class = 'alert-' . ($flash['type'] === 'danger' ? 'danger' : ($flash['type'] === 'success' ? 'success' : 'info'));
        ?>
        <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
            <?php echo $flash['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
    <?php endif; ?>
