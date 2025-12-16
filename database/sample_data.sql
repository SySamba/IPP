-- Données d'exemple pour IPP School Management
-- À exécuter après l'importation du schema.sql

USE ipp_school;

-- Année académique active
INSERT INTO annees_academiques (libelle, date_debut, date_fin, statut) VALUES
('2024-2025', '2024-09-01', '2025-06-30', 'active');

SET @annee_id = LAST_INSERT_ID();

-- Périodes/Semestres
INSERT INTO periodes (annee_academique_id, libelle, numero, date_debut, date_fin) VALUES
(@annee_id, 'Semestre 1', 1, '2024-09-01', '2025-01-31'),
(@annee_id, 'Semestre 2', 2, '2025-02-01', '2025-06-30');

-- Classes pour chaque filière et niveau
INSERT INTO classes (code, libelle, filiere_id, niveau_id, annee_academique_id, effectif_max, statut) VALUES
-- Informatique
('INFO-L1-A', 'Classe A', 1, 1, @annee_id, 40, 'active'),
('INFO-L2-A', 'Classe A', 1, 2, @annee_id, 35, 'active'),
('INFO-L3-A', 'Classe A', 1, 3, @annee_id, 30, 'active'),
('INFO-M1-A', 'Classe A', 1, 4, @annee_id, 25, 'active'),
('INFO-M2-A', 'Classe A', 1, 5, @annee_id, 20, 'active'),

-- Génie Civil
('GC-L1-A', 'Classe A', 2, 1, @annee_id, 40, 'active'),
('GC-L2-A', 'Classe A', 2, 2, @annee_id, 35, 'active'),
('GC-L3-A', 'Classe A', 2, 3, @annee_id, 30, 'active'),

-- Génie Électrique
('GE-L1-A', 'Classe A', 3, 1, @annee_id, 40, 'active'),
('GE-L2-A', 'Classe A', 3, 2, @annee_id, 35, 'active'),

-- Génie Mécanique
('GM-L1-A', 'Classe A', 4, 1, @annee_id, 40, 'active'),
('GM-L2-A', 'Classe A', 4, 2, @annee_id, 35, 'active');

-- Matières
INSERT INTO matieres (code, libelle, coefficient, statut) VALUES
-- Matières générales
('MATH', 'Mathématiques', 3.0, 'active'),
('PHY', 'Physique', 2.5, 'active'),
('CHIM', 'Chimie', 2.0, 'active'),
('ANG', 'Anglais', 2.0, 'active'),
('FRA', 'Français', 2.0, 'active'),

-- Informatique
('PROG', 'Programmation', 3.0, 'active'),
('BDD', 'Bases de données', 2.5, 'active'),
('ALGO', 'Algorithmique', 3.0, 'active'),
('WEB', 'Développement Web', 2.5, 'active'),
('RESEAU', 'Réseaux informatiques', 2.5, 'active'),

-- Génie Civil
('RDM', 'Résistance des matériaux', 3.0, 'active'),
('CONST', 'Construction', 2.5, 'active'),
('TOPO', 'Topographie', 2.0, 'active'),

-- Génie Électrique
('ELEC', 'Électronique', 3.0, 'active'),
('AUTO', 'Automatique', 2.5, 'active'),
('ENER', 'Énergétique', 2.5, 'active'),

-- Génie Mécanique
('MECA', 'Mécanique générale', 3.0, 'active'),
('THERMO', 'Thermodynamique', 2.5, 'active'),
('FAB', 'Fabrication mécanique', 2.5, 'active');

-- Enseignants
INSERT INTO users (username, email, password, role, nom, prenom, telephone, statut) VALUES
('prof.diallo', 'diallo@ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant', 'Diallo', 'Amadou', '+225 0701020304', 'actif'),
('prof.kone', 'kone@ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant', 'Koné', 'Fatou', '+225 0702030405', 'actif'),
('prof.toure', 'toure@ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant', 'Touré', 'Ibrahim', '+225 0703040506', 'actif'),
('prof.traore', 'traore@ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant', 'Traoré', 'Mariam', '+225 0704050607', 'actif'),
('prof.coulibaly', 'coulibaly@ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant', 'Coulibaly', 'Sekou', '+225 0705060708', 'actif');

-- Association matières-classes pour Informatique L1
INSERT INTO classe_matieres (classe_id, matiere_id, enseignant_id, coefficient) VALUES
(1, 1, 2, 3.0),  -- Math
(1, 2, 3, 2.5),  -- Physique
(1, 4, 4, 2.0),  -- Anglais
(1, 6, 2, 3.0),  -- Programmation
(1, 8, 3, 3.0);  -- Algorithmique

-- Association matières-classes pour Informatique L2
INSERT INTO classe_matieres (classe_id, matiere_id, enseignant_id, coefficient) VALUES
(2, 1, 2, 3.0),  -- Math
(2, 6, 2, 3.0),  -- Programmation
(2, 7, 3, 2.5),  -- BDD
(2, 9, 4, 2.5),  -- Web
(2, 4, 4, 2.0);  -- Anglais

-- Étudiants pour Informatique L1
INSERT INTO users (username, email, password, role, nom, prenom, telephone, statut) VALUES
('etu.yao', 'yao@student.ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'etudiant', 'Yao', 'Kouassi', '+225 0711111111', 'actif'),
('etu.koffi', 'koffi@student.ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'etudiant', 'Koffi', 'Aya', '+225 0722222222', 'actif'),
('etu.bamba', 'bamba@student.ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'etudiant', 'Bamba', 'Moussa', '+225 0733333333', 'actif'),
('etu.soro', 'soro@student.ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'etudiant', 'Soro', 'Aminata', '+225 0744444444', 'actif'),
('etu.ouattara', 'ouattara@student.ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'etudiant', 'Ouattara', 'Abdoul', '+225 0755555555', 'actif');

-- Informations détaillées des étudiants
INSERT INTO etudiants (user_id, matricule, classe_id, annee_academique_id, date_naissance, lieu_naissance, sexe, adresse, ville, pays, nationalite, nom_tuteur, telephone_tuteur, statut) VALUES
(7, 'IPP202400001', 1, @annee_id, '2005-03-15', 'Abidjan', 'M', 'Cocody Angré', 'Abidjan', 'Côte d\'Ivoire', 'Ivoirienne', 'Yao Koffi', '+225 0711111110', 'actif'),
(8, 'IPP202400002', 1, @annee_id, '2005-07-22', 'Bouaké', 'F', 'Plateau', 'Abidjan', 'Côte d\'Ivoire', 'Ivoirienne', 'Koffi Jean', '+225 0722222220', 'actif'),
(9, 'IPP202400003', 1, @annee_id, '2004-11-10', 'Yamoussoukro', 'M', 'Marcory Zone 4', 'Abidjan', 'Côte d\'Ivoire', 'Ivoirienne', 'Bamba Seydou', '+225 0733333330', 'actif'),
(10, 'IPP202400004', 1, @annee_id, '2005-05-18', 'Korhogo', 'F', 'Abobo', 'Abidjan', 'Côte d\'Ivoire', 'Ivoirienne', 'Soro Lassina', '+225 0744444440', 'actif'),
(11, 'IPP202400005', 1, @annee_id, '2005-09-25', 'Daloa', 'M', 'Yopougon', 'Abidjan', 'Côte d\'Ivoire', 'Ivoirienne', 'Ouattara Mamadou', '+225 0755555550', 'actif');

-- Inscriptions
INSERT INTO inscriptions (etudiant_id, classe_id, annee_academique_id, date_inscription, type_inscription, statut) VALUES
(1, 1, @annee_id, '2024-09-01', 'nouvelle', 'validee'),
(2, 1, @annee_id, '2024-09-01', 'nouvelle', 'validee'),
(3, 1, @annee_id, '2024-09-01', 'nouvelle', 'validee'),
(4, 1, @annee_id, '2024-09-01', 'nouvelle', 'validee'),
(5, 1, @annee_id, '2024-09-01', 'nouvelle', 'validee');

-- Paiements
INSERT INTO paiements (etudiant_id, annee_academique_id, type_frais, montant_total, montant_paye, date_paiement, mode_paiement, statut, commentaire) VALUES
(1, @annee_id, 'Scolarité', 500000, 500000, '2024-09-05', 'virement', 'paye', 'Paiement complet'),
(2, @annee_id, 'Scolarité', 500000, 300000, '2024-09-10', 'especes', 'partiel', 'Première tranche'),
(3, @annee_id, 'Scolarité', 500000, 500000, '2024-09-03', 'cheque', 'paye', 'Paiement complet'),
(4, @annee_id, 'Scolarité', 500000, 250000, '2024-09-15', 'especes', 'partiel', 'Première tranche'),
(5, @annee_id, 'Scolarité', 500000, 0, NULL, NULL, 'non_paye', 'En attente');

-- Notes pour Semestre 1 (Étudiant 1 - Yao Kouassi)
INSERT INTO notes (etudiant_id, classe_matiere_id, periode_id, type_evaluation, note, note_sur, date_evaluation, commentaire) VALUES
-- Mathématiques
(1, 1, 1, 'devoir', 14, 20, '2024-10-15', 'Bon travail'),
(1, 1, 1, 'composition', 15, 20, '2024-12-10', 'Très bien'),
-- Physique
(1, 2, 1, 'devoir', 13, 20, '2024-10-20', 'Bien'),
(1, 2, 1, 'composition', 14, 20, '2024-12-12', 'Bon niveau'),
-- Anglais
(1, 3, 1, 'devoir', 16, 20, '2024-11-05', 'Excellent'),
(1, 3, 1, 'composition', 15, 20, '2024-12-15', 'Très bien'),
-- Programmation
(1, 4, 1, 'devoir', 17, 20, '2024-10-25', 'Excellent travail'),
(1, 4, 1, 'composition', 16, 20, '2024-12-18', 'Très bon niveau'),
-- Algorithmique
(1, 5, 1, 'devoir', 15, 20, '2024-11-10', 'Bien'),
(1, 5, 1, 'composition', 16, 20, '2024-12-20', 'Très bien');

-- Notes pour Étudiant 2 (Koffi Aya)
INSERT INTO notes (etudiant_id, classe_matiere_id, periode_id, type_evaluation, note, note_sur, date_evaluation, commentaire) VALUES
-- Mathématiques
(2, 1, 1, 'devoir', 16, 20, '2024-10-15', 'Excellent'),
(2, 1, 1, 'composition', 17, 20, '2024-12-10', 'Excellent travail'),
-- Physique
(2, 2, 1, 'devoir', 15, 20, '2024-10-20', 'Très bien'),
(2, 2, 1, 'composition', 16, 20, '2024-12-12', 'Excellent'),
-- Anglais
(2, 3, 1, 'devoir', 18, 20, '2024-11-05', 'Excellent'),
(2, 3, 1, 'composition', 17, 20, '2024-12-15', 'Excellent'),
-- Programmation
(2, 4, 1, 'devoir', 16, 20, '2024-10-25', 'Très bien'),
(2, 4, 1, 'composition', 18, 20, '2024-12-18', 'Excellent'),
-- Algorithmique
(2, 5, 1, 'devoir', 17, 20, '2024-11-10', 'Excellent'),
(2, 5, 1, 'composition', 17, 20, '2024-12-20', 'Excellent');

-- Notes pour Étudiant 3 (Bamba Moussa)
INSERT INTO notes (etudiant_id, classe_matiere_id, periode_id, type_evaluation, note, note_sur, date_evaluation, commentaire) VALUES
-- Mathématiques
(3, 1, 1, 'devoir', 12, 20, '2024-10-15', 'Assez bien'),
(3, 1, 1, 'composition', 13, 20, '2024-12-10', 'Bien'),
-- Physique
(3, 2, 1, 'devoir', 11, 20, '2024-10-20', 'Passable'),
(3, 2, 1, 'composition', 12, 20, '2024-12-12', 'Assez bien'),
-- Anglais
(3, 3, 1, 'devoir', 14, 20, '2024-11-05', 'Bien'),
(3, 3, 1, 'composition', 13, 20, '2024-12-15', 'Bien'),
-- Programmation
(3, 4, 1, 'devoir', 13, 20, '2024-10-25', 'Bien'),
(3, 4, 1, 'composition', 14, 20, '2024-12-18', 'Bien'),
-- Algorithmique
(3, 5, 1, 'devoir', 12, 20, '2024-11-10', 'Assez bien'),
(3, 5, 1, 'composition', 13, 20, '2024-12-20', 'Bien');

-- Absences
INSERT INTO absences (etudiant_id, classe_matiere_id, date_absence, heure_debut, heure_fin, type, motif) VALUES
(3, 1, '2024-10-10', '08:00:00', '10:00:00', 'non_justifiee', 'Absence non justifiée'),
(3, 4, '2024-11-15', '14:00:00', '16:00:00', 'justifiee', 'Visite médicale'),
(4, 2, '2024-10-25', '10:00:00', '12:00:00', 'justifiee', 'Problème familial'),
(5, 1, '2024-11-20', '08:00:00', '10:00:00', 'non_justifiee', 'Retard important');

-- Utilisateur scolarité
INSERT INTO users (username, email, password, role, nom, prenom, telephone, statut) VALUES
('scolarite', 'scolarite@ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'scolarite', 'Service', 'Scolarité', '+225 0700000000', 'actif');

-- Utilisateur direction
INSERT INTO users (username, email, password, role, nom, prenom, telephone, statut) VALUES
('direction', 'direction@ipp.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'direction', 'Directeur', 'Général', '+225 0700000001', 'actif');

-- Logs d'activité
INSERT INTO logs (user_id, action, table_name, record_id, details) VALUES
(1, 'Connexion', 'users', 1, 'Connexion administrateur'),
(2, 'Création étudiant', 'etudiants', 1, 'IPP202400001'),
(2, 'Création étudiant', 'etudiants', 2, 'IPP202400002');

SELECT 'Données d\'exemple insérées avec succès!' as Message;
SELECT CONCAT('Année académique ID: ', @annee_id) as Info;
SELECT 'Mot de passe par défaut pour tous les utilisateurs: admin123' as Important;
