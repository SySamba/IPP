-- Base de données pour la Plateforme de Gestion d'École
-- Institut Polytechnique Panafricain

CREATE DATABASE IF NOT EXISTS ipp_school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ipp_school;

-- Table des utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'scolarite', 'enseignant', 'etudiant', 'direction') NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20),
    photo VARCHAR(255),
    statut ENUM('actif', 'inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des années académiques
CREATE TABLE annees_academiques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    statut ENUM('active', 'inactive') DEFAULT 'inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_annee (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des filières
CREATE TABLE filieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    description TEXT,
    statut ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des niveaux
CREATE TABLE niveaux (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    libelle VARCHAR(50) NOT NULL,
    ordre INT NOT NULL,
    statut ENUM('actif', 'inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des classes
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    filiere_id INT NOT NULL,
    niveau_id INT NOT NULL,
    annee_academique_id INT NOT NULL,
    effectif_max INT DEFAULT 50,
    statut ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE CASCADE,
    FOREIGN KEY (niveau_id) REFERENCES niveaux(id) ON DELETE CASCADE,
    FOREIGN KEY (annee_academique_id) REFERENCES annees_academiques(id) ON DELETE CASCADE,
    INDEX idx_filiere (filiere_id),
    INDEX idx_niveau (niveau_id),
    INDEX idx_annee (annee_academique_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des matières
CREATE TABLE matieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    coefficient DECIMAL(3,1) NOT NULL DEFAULT 1.0,
    description TEXT,
    statut ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison matières-classes
CREATE TABLE classe_matieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classe_id INT NOT NULL,
    matiere_id INT NOT NULL,
    enseignant_id INT,
    coefficient DECIMAL(3,1) DEFAULT 1.0,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE,
    FOREIGN KEY (enseignant_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_classe_matiere (classe_id, matiere_id),
    INDEX idx_enseignant (enseignant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des étudiants
CREATE TABLE etudiants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    matricule VARCHAR(50) UNIQUE NOT NULL,
    classe_id INT NOT NULL,
    annee_academique_id INT NOT NULL,
    date_naissance DATE NOT NULL,
    lieu_naissance VARCHAR(100),
    sexe ENUM('M', 'F') NOT NULL,
    adresse TEXT,
    ville VARCHAR(100),
    pays VARCHAR(100),
    nationalite VARCHAR(100),
    nom_tuteur VARCHAR(100),
    telephone_tuteur VARCHAR(20),
    photo VARCHAR(255),
    statut ENUM('actif', 'inactif', 'suspendu', 'diplome') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE RESTRICT,
    FOREIGN KEY (annee_academique_id) REFERENCES annees_academiques(id) ON DELETE RESTRICT,
    INDEX idx_matricule (matricule),
    INDEX idx_statut (statut),
    INDEX idx_classe (classe_id),
    INDEX idx_annee (annee_academique_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des inscriptions
CREATE TABLE inscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL,
    classe_id INT NOT NULL,
    annee_academique_id INT NOT NULL,
    date_inscription DATE NOT NULL,
    type_inscription ENUM('nouvelle', 'reinscription') NOT NULL,
    statut ENUM('en_cours', 'validee', 'annulee') DEFAULT 'en_cours',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (annee_academique_id) REFERENCES annees_academiques(id) ON DELETE CASCADE,
    UNIQUE KEY unique_inscription (etudiant_id, annee_academique_id),
    INDEX idx_classe (classe_id),
    INDEX idx_annee (annee_academique_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des semestres/périodes
CREATE TABLE periodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annee_academique_id INT NOT NULL,
    libelle VARCHAR(50) NOT NULL,
    numero INT NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    statut ENUM('active', 'inactive') DEFAULT 'inactive',
    FOREIGN KEY (annee_academique_id) REFERENCES annees_academiques(id) ON DELETE CASCADE,
    INDEX idx_annee (annee_academique_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des notes
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL,
    classe_matiere_id INT NOT NULL,
    periode_id INT NOT NULL,
    type_note ENUM('devoir', 'composition', 'examen', 'cc', 'tp') NOT NULL,
    note DECIMAL(5,2) NOT NULL,
    note_sur DECIMAL(5,2) DEFAULT 20.00,
    coefficient DECIMAL(3,1) DEFAULT 1.0,
    date_evaluation DATE NOT NULL,
    commentaire TEXT,
    saisi_par INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_matiere_id) REFERENCES classe_matieres(id) ON DELETE CASCADE,
    FOREIGN KEY (periode_id) REFERENCES periodes(id) ON DELETE CASCADE,
    FOREIGN KEY (saisi_par) REFERENCES users(id),
    INDEX idx_etudiant (etudiant_id),
    INDEX idx_periode (periode_id),
    INDEX idx_classe_matiere (classe_matiere_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des absences
CREATE TABLE absences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL,
    classe_matiere_id INT NOT NULL,
    date_absence DATE NOT NULL,
    heure_debut TIME,
    heure_fin TIME,
    type ENUM('justifiee', 'non_justifiee') DEFAULT 'non_justifiee',
    motif TEXT,
    justificatif VARCHAR(255),
    saisi_par INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_matiere_id) REFERENCES classe_matieres(id) ON DELETE CASCADE,
    FOREIGN KEY (saisi_par) REFERENCES users(id),
    INDEX idx_etudiant (etudiant_id),
    INDEX idx_date (date_absence),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des paiements
CREATE TABLE paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL,
    annee_academique_id INT NOT NULL,
    type_frais VARCHAR(100) NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    montant_paye DECIMAL(10,2) DEFAULT 0.00,
    statut ENUM('paye', 'non_paye', 'partiel') DEFAULT 'non_paye',
    date_paiement DATE,
    mode_paiement VARCHAR(50),
    commentaire TEXT,
    enregistre_par INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (annee_academique_id) REFERENCES annees_academiques(id) ON DELETE CASCADE,
    FOREIGN KEY (enregistre_par) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_etudiant (etudiant_id),
    INDEX idx_statut (statut),
    INDEX idx_annee (annee_academique_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des salles
CREATE TABLE salles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    capacite INT NOT NULL,
    type VARCHAR(50),
    equipements TEXT,
    statut ENUM('disponible', 'indisponible', 'maintenance') DEFAULT 'disponible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des emplois du temps
CREATE TABLE emplois_temps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classe_id INT NOT NULL,
    classe_matiere_id INT NOT NULL,
    salle_id INT,
    jour ENUM('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi') NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    annee_academique_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_matiere_id) REFERENCES classe_matieres(id) ON DELETE CASCADE,
    FOREIGN KEY (salle_id) REFERENCES salles(id) ON DELETE SET NULL,
    FOREIGN KEY (annee_academique_id) REFERENCES annees_academiques(id) ON DELETE CASCADE,
    INDEX idx_classe (classe_id),
    INDEX idx_jour (jour),
    INDEX idx_salle (salle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des documents
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    fichier VARCHAR(255) NOT NULL,
    type_document VARCHAR(50),
    classe_matiere_id INT,
    uploaded_by INT NOT NULL,
    visible_pour ENUM('tous', 'classe', 'enseignants') DEFAULT 'classe',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (classe_matiere_id) REFERENCES classe_matieres(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_classe_matiere (classe_matiere_id),
    INDEX idx_uploaded_by (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des bulletins générés
CREATE TABLE bulletins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL,
    periode_id INT NOT NULL,
    annee_academique_id INT NOT NULL,
    moyenne_generale DECIMAL(5,2),
    rang INT,
    effectif_classe INT,
    appreciation TEXT,
    fichier_pdf VARCHAR(255),
    genere_par INT,
    date_generation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (periode_id) REFERENCES periodes(id) ON DELETE CASCADE,
    FOREIGN KEY (annee_academique_id) REFERENCES annees_academiques(id) ON DELETE CASCADE,
    FOREIGN KEY (genere_par) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_bulletin (etudiant_id, periode_id),
    INDEX idx_periode (periode_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des logs d'activité
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des données initiales

-- Utilisateur admin par défaut (mot de passe: admin123)
INSERT INTO users (username, email, password, role, nom, prenom, telephone, statut) VALUES
('admin', 'admin@ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrateur', 'Système', '+225 0000000000', 'actif');

-- Niveaux par défaut
INSERT INTO niveaux (code, libelle, ordre, statut) VALUES
('L1', 'Licence 1', 1, 'actif'),
('L2', 'Licence 2', 2, 'actif'),
('L3', 'Licence 3', 3, 'actif'),
('M1', 'Master 1', 4, 'actif'),
('M2', 'Master 2', 5, 'actif');

-- Filières par défaut
INSERT INTO filieres (code, libelle, description, statut) VALUES
('INFO', 'Informatique', 'Génie Informatique et Télécommunications', 'active'),
('GENIE_CIVIL', 'Génie Civil', 'Génie Civil et Construction', 'active'),
('GENIE_ELEC', 'Génie Électrique', 'Génie Électrique et Énergétique', 'active'),
('GENIE_MECA', 'Génie Mécanique', 'Génie Mécanique et Industriel', 'active');
