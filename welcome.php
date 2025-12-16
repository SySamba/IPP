<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institut Polytechnique Panafricain - Accueil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ef1c5d;
            --secondary-color: #fdb92d;
            --accent-color: #f58024;
            --dark-blue: #1a1a1a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-blue) 100%);
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(251, 191, 36, 0.1);
            animation: float 20s infinite ease-in-out;
        }
        
        .shape:nth-child(1) {
            width: 300px;
            height: 300px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 200px;
            height: 200px;
            top: 60%;
            right: 10%;
            animation-delay: 5s;
            background: rgba(245, 128, 36, 0.1);
        }
        
        .shape:nth-child(3) {
            width: 150px;
            height: 150px;
            bottom: 20%;
            left: 50%;
            animation-delay: 10s;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(30px, -30px) rotate(90deg); }
            50% { transform: translate(-20px, 20px) rotate(180deg); }
            75% { transform: translate(20px, 30px) rotate(270deg); }
        }
        
        .navbar {
            background: rgba(26, 26, 46, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            padding: 15px 0;
        }
        
        .navbar .nav-link {
            color: rgba(255,255,255,0.85) !important;
            font-weight: 500;
            padding: 8px 16px !important;
            transition: all 0.3s ease;
        }
        
        .navbar .nav-link:hover {
            color: var(--secondary-color) !important;
        }
        
        .hero-content {
            position: relative;
            z-index: 10;
            padding: 150px 0 100px;
            color: white;
        }
        
        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.5);
            color: white;
        }
        
        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 30px;
            opacity: 1;
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            font-weight: 500;
        }
        
        .btn-hero {
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-primary-custom {
            background: var(--primary-color);
            color: white;
            border: none;
            box-shadow: 0 10px 30px rgba(239, 28, 93, 0.3);
        }
        
        .btn-primary-custom:hover {
            background: var(--accent-color);
            color: var(--dark-blue);
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(245, 128, 36, 0.5);
        }
        
        .btn-outline-custom {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-outline-custom:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-3px);
        }
        
        /* Features Section */
        .features-section {
            padding: 100px 0;
            background: linear-gradient(to bottom, #f8fafc 0%, white 100%);
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            border-color: var(--secondary-color);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
        }
        
        .icon-blue { background: linear-gradient(135deg, var(--primary-color), #ff4081); }
        .icon-yellow { background: linear-gradient(135deg, var(--secondary-color), #ffeb3b); }
        .icon-green { background: linear-gradient(135deg, var(--accent-color), #ff9800); }
        .icon-purple { background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); }
        .icon-red { background: linear-gradient(135deg, var(--accent-color), var(--secondary-color)); }
        .icon-cyan { background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); }
        
        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-blue);
            margin-bottom: 15px;
        }
        
        .feature-description {
            color: #64748b;
            line-height: 1.8;
        }
        
        /* Stats Section */
        .stats-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            padding: 80px 0;
            color: white;
        }
        
        .stat-item {
            text-align: center;
            padding: 30px;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .stat-label {
            font-size: 1.1rem;
            opacity: 1;
            font-weight: 600;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        
        /* CTA Section */
        .cta-section {
            padding: 100px 0;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            text-align: center;
        }
        
        .cta-title {
            font-size: 3rem;
            font-weight: 800;
            color: var(--dark-blue);
            margin-bottom: 20px;
        }
        
        .cta-subtitle {
            font-size: 1.3rem;
            color: var(--dark-blue);
            opacity: 0.8;
            margin-bottom: 40px;
        }
        
        /* Footer */
        .footer {
            background: var(--dark-blue);
            color: white;
            padding: 60px 0 30px;
        }
        
        .footer-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--secondary-color);
        }
        
        .footer-link {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: block;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .footer-link:hover {
            color: var(--secondary-color);
            padding-left: 10px;
        }
        
        .social-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .social-icon:hover {
            background: var(--secondary-color);
            color: var(--dark-blue);
            transform: translateY(-5px);
        }
        
        @media (max-width: 768px) {
            .hero-title { font-size: 2.5rem; }
            .hero-subtitle { font-size: 1.2rem; }
            .stat-number { font-size: 2.5rem; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-graduation-cap fa-2x me-2" style="color: var(--secondary-color);"></i>
                <span class="fw-bold">IPP</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#accueil">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#fonctionnalites">Fonctionnalités</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#apropos">À propos</a>
                    </li>
                    <li class="nav-item ms-3">
                        <a class="btn btn-primary-custom btn-sm px-4" href="login.php">
                            <i class="fas fa-sign-in-alt me-2"></i>Connexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="accueil">
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
        
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-7" data-aos="fade-right">
                    <h1 class="hero-title">Institut Polytechnique Panafricain</h1>
                    <p class="hero-subtitle">Plateforme de Gestion Académique Intelligente</p>
                    <p class="mb-4 opacity-75">Gérez votre établissement avec efficacité : étudiants, enseignants, notes, paiements et bien plus encore.</p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="login.php" class="btn btn-primary-custom btn-hero">
                            <i class="fas fa-rocket me-2"></i>Commencer
                        </a>
                        <a href="#fonctionnalites" class="btn btn-outline-custom btn-hero">
                            <i class="fas fa-info-circle me-2"></i>En savoir plus
                        </a>
                    </div>
                </div>
                <div class="col-lg-5 text-center" data-aos="fade-left" data-aos-delay="200">
                    <i class="fas fa-university" style="font-size: 15rem; color: rgba(251, 191, 36, 0.2);"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-item">
                        <div class="stat-number"><i class="fas fa-user-graduate"></i> 500+</div>
                        <div class="stat-label">Étudiants</div>
                    </div>
                </div>
                <div class="col-md-3 col-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-item">
                        <div class="stat-number"><i class="fas fa-chalkboard-teacher"></i> 50+</div>
                        <div class="stat-label">Enseignants</div>
                    </div>
                </div>
                <div class="col-md-3 col-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-item">
                        <div class="stat-number"><i class="fas fa-book"></i> 100+</div>
                        <div class="stat-label">Cours</div>
                    </div>
                </div>
                <div class="col-md-3 col-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-item">
                        <div class="stat-number"><i class="fas fa-trophy"></i> 95%</div>
                        <div class="stat-label">Taux de Réussite</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="fonctionnalites">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-4 fw-bold mb-3" style="color: var(--dark-blue);">Fonctionnalités Complètes</h2>
                <p class="lead text-muted">Une solution tout-en-un pour la gestion de votre établissement</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon icon-blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="feature-title">Gestion des Étudiants</h3>
                        <p class="feature-description">Inscriptions, dossiers académiques, suivi personnalisé et gestion des absences en temps réel.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon icon-yellow">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h3 class="feature-title">Espace Enseignants</h3>
                        <p class="feature-description">Saisie des notes, gestion des classes, partage de documents et communication facilitée.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon icon-green">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3 class="feature-title">Gestion Financière</h3>
                        <p class="feature-description">Suivi des paiements, frais de scolarité, génération de reçus et rapports financiers.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon icon-purple">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="feature-title">Tableaux de Bord BI</h3>
                        <p class="feature-description">Statistiques avancées, analyses en temps réel et rapports personnalisables pour la direction.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon icon-red">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 class="feature-title">Bulletins & Documents</h3>
                        <p class="feature-description">Génération automatique de bulletins, relevés de notes et documents administratifs.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="600">
                    <div class="feature-card">
                        <div class="feature-icon icon-cyan">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="feature-title">Sécurité & Confidentialité</h3>
                        <p class="feature-description">Protection des données, accès sécurisé et gestion des permissions par rôle.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Témoignages Section -->
    <section class="py-5" style="background: #f8fafc;" id="temoignages">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3" style="color: var(--dark-blue);">Ce qu'ils disent de nous</h2>
                <p class="lead text-muted">Témoignages de notre communauté</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 20px;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="fas fa-user text-white fa-lg"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Dr. Amadou Diallo</h6>
                                    <small class="text-muted">Directeur Académique</small>
                                </div>
                            </div>
                            <p class="text-muted mb-3">"Cette plateforme a révolutionné notre gestion académique. Le suivi des étudiants n'a jamais été aussi simple et efficace."</p>
                            <div class="text-warning">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 20px;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--secondary-color), var(--accent-color)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="fas fa-user text-white fa-lg"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Prof. Marie Kouassi</h6>
                                    <small class="text-muted">Enseignante</small>
                                </div>
                            </div>
                            <p class="text-muted mb-3">"La saisie des notes et le suivi des absences sont devenus un jeu d'enfant. Je gagne un temps précieux chaque semaine."</p>
                            <div class="text-warning">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 20px;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="fas fa-user-graduate text-white fa-lg"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Jean-Pierre Mbeki</h6>
                                    <small class="text-muted">Étudiant en GL3</small>
                                </div>
                            </div>
                            <p class="text-muted mb-3">"Je peux consulter mes notes et mon emploi du temps à tout moment. C'est vraiment pratique pour suivre ma progression."</p>
                            <div class="text-warning">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section" id="apropos">
        <div class="container" data-aos="zoom-in">
            <h2 class="cta-title">Prêt à Transformer Votre Gestion Académique ?</h2>
            <p class="cta-subtitle">Rejoignez-nous et découvrez une nouvelle façon de gérer votre établissement</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="login.php" class="btn btn-hero" style="background: var(--dark-blue); color: white; border: none;">
                    <i class="fas fa-sign-in-alt me-2"></i>Accéder à la Plateforme
                </a>
                <a href="#contact" class="btn btn-hero" style="background: transparent; color: var(--dark-blue); border: 2px solid var(--dark-blue);">
                    <i class="fas fa-envelope me-2"></i>Nous Contacter
                </a>
            </div>
        </div>
    </section>
    
    <!-- Contact Section -->
    <section class="py-5" style="background: var(--dark-blue);" id="contact">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0" data-aos="fade-right">
                    <h2 class="display-5 fw-bold text-white mb-4">Contactez-nous</h2>
                    <p class="text-white-50 mb-4">Vous avez des questions ? N'hésitez pas à nous contacter. Notre équipe est là pour vous aider.</p>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div style="width: 50px; height: 50px; background: var(--primary-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <i class="fas fa-map-marker-alt text-white"></i>
                        </div>
                        <div class="text-white">
                            <strong>Adresse</strong><br>
                            <span class="text-white-50">Ngor, Dakar - Sénégal</span>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div style="width: 50px; height: 50px; background: var(--secondary-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <i class="fas fa-phone text-white"></i>
                        </div>
                        <div class="text-white">
                            <strong>Téléphone</strong><br>
                            <span class="text-white-50">+221 XX XXX XX XX</span>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center">
                        <div style="width: 50px; height: 50px; background: var(--accent-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <i class="fas fa-envelope text-white"></i>
                        </div>
                        <div class="text-white">
                            <strong>Email</strong><br>
                            <span class="text-white-50">contact@ipp.sn</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="card border-0 shadow-lg" style="border-radius: 20px;">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4" style="color: var(--dark-blue);">Envoyez-nous un message</h5>
                            <form>
                                <div class="mb-3">
                                    <input type="text" class="form-control" placeholder="Votre nom" style="border-radius: 10px; padding: 12px 15px;">
                                </div>
                                <div class="mb-3">
                                    <input type="email" class="form-control" placeholder="Votre email" style="border-radius: 10px; padding: 12px 15px;">
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" placeholder="Sujet" style="border-radius: 10px; padding: 12px 15px;">
                                </div>
                                <div class="mb-3">
                                    <textarea class="form-control" rows="4" placeholder="Votre message" style="border-radius: 10px; padding: 12px 15px;"></textarea>
                                </div>
                                <button type="submit" class="btn w-100" style="background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); color: white; border-radius: 10px; padding: 12px;">
                                    <i class="fas fa-paper-plane me-2"></i>Envoyer le message
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h3 class="footer-title">
                        <i class="fas fa-graduation-cap me-2"></i>IPP
                    </h3>
                    <p class="text-white-50">Institut Polytechnique Panafricain - Excellence académique et innovation technologique au service de l'éducation.</p>
                    <div class="mt-3">
                        <a href="#" class="social-icon text-white">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-icon text-white">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-icon text-white">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="social-icon text-white">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="footer-title">Liens Rapides</h5>
                    <a href="#accueil" class="footer-link">Accueil</a>
                    <a href="#fonctionnalites" class="footer-link">Fonctionnalités</a>
                    <a href="#apropos" class="footer-link">À propos</a>
                    <a href="login.php" class="footer-link">Connexion</a>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="footer-title">Services</h5>
                    <a href="#" class="footer-link">Gestion Étudiants</a>
                    <a href="#" class="footer-link">Espace Enseignants</a>
                    <a href="#" class="footer-link">Gestion Financière</a>
                    <a href="#" class="footer-link">Tableaux de Bord</a>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="footer-title">Contact</h5>
                    <p class="text-white-50">
                        <i class="fas fa-map-marker-alt me-2"></i>Ngor, Dakar - Sénégal<br>
                        <i class="fas fa-phone me-2"></i>+221 XX XXX XX XX<br>
                        <i class="fas fa-envelope me-2"></i>contact@ipp.sn
                    </p>
                </div>
            </div>
            <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
            <div class="text-center text-white-50">
                <p class="mb-0">&copy; 2025 Institut Polytechnique Panafricain. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(26, 26, 46, 1)';
                navbar.style.boxShadow = '0 4px 30px rgba(0,0,0,0.3)';
            } else {
                navbar.style.background = 'rgba(26, 26, 46, 0.95)';
                navbar.style.boxShadow = '0 4px 20px rgba(0,0,0,0.2)';
            }
        });
        
        // Counter animation for stats
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target;
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 30);
        }
        
        // Observe stats section
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statNumbers = entry.target.querySelectorAll('.stat-number');
                    statNumbers.forEach(stat => {
                        const text = stat.textContent;
                        const number = parseInt(text.replace(/\D/g, ''));
                        if (number && !stat.classList.contains('animated')) {
                            stat.classList.add('animated');
                            // Keep the icon and suffix
                            const icon = stat.querySelector('i');
                            const suffix = text.includes('%') ? '%' : '+';
                            stat.innerHTML = icon ? icon.outerHTML + ' <span class="counter">0</span>' + suffix : '<span class="counter">0</span>' + suffix;
                            const counter = stat.querySelector('.counter');
                            animateCounter(counter, number);
                        }
                    });
                }
            });
        }, { threshold: 0.5 });
        
        const statsSection = document.querySelector('.stats-section');
        if (statsSection) {
            statsObserver.observe(statsSection);
        }
    </script>
</body>
</html>
