# Institut Polytechnique Panafricain - Plateforme de Gestion d'École

## Description
Plateforme complète de gestion académique pour l'Institut Polytechnique Panafricain permettant la gestion des inscriptions, étudiants, enseignants, notes, absences, bulletins, emplois du temps et paiements.

## Technologies Utilisées
- **Backend**: PHP pur (PDO)
- **Base de données**: MySQL
- **Frontend**: HTML5, CSS3, Bootstrap 5
- **JavaScript**: Vanilla JS, jQuery
- **PDF**: mPDF / DomPDF (à installer)
- **Sécurité**: Sessions PHP, CSRF Protection, XSS Prevention

## Installation

### Prérequis
- XAMPP (Apache + MySQL + PHP 7.4+)
- Navigateur web moderne

### Étapes d'installation

1. **Cloner ou copier le projet**
   ```
   Placer le dossier 'ipp' dans C:\xampp\htdocs\
   ```

2. **Créer la base de données**
   - Démarrer XAMPP (Apache + MySQL)
   - Ouvrir phpMyAdmin: http://localhost/phpmyadmin
   - Importer le fichier: `database/schema.sql`
   - Ou exécuter le script SQL manuellement

3. **Configuration**
   - Vérifier les paramètres dans `config/database.php`
   - Par défaut: Host=localhost, User=root, Password=vide

4. **Accéder à l'application**
   ```
   URL: http://localhost/ipp
   ```

5. **Connexion par défaut**
   ```
   Utilisateur: admin
   Mot de passe: admin123
   ```

## Structure du Projet

```
ipp/
├── admin/                  # Module administration
│   ├── users.php          # Gestion utilisateurs
│   ├── filieres.php       # Gestion filières
│   ├── classes.php        # Gestion classes
│   ├── matieres.php       # Gestion matières
│   ├── salles.php         # Gestion salles
│   └── annees.php         # Années académiques
├── scolarite/             # Module scolarité
│   ├── etudiants.php      # Gestion étudiants
│   ├── inscriptions.php   # Inscriptions
│   ├── bulletins.php      # Génération bulletins
│   ├── absences.php       # Gestion absences
│   └── paiements.php      # Suivi paiements
├── enseignant/            # Module enseignant
│   ├── notes.php          # Saisie notes
│   ├── absences.php       # Saisie absences
│   └── documents.php      # Dépôt documents
├── etudiant/              # Module étudiant
│   ├── notes.php          # Consultation notes
│   ├── bulletins.php      # Téléchargement bulletins
│   ├── absences.php       # Consultation absences
│   └── emploi-temps.php   # Emploi du temps
├── emploi-temps/          # Module emploi du temps
├── bi/                    # Module BI Dashboard
├── config/                # Configuration
│   ├── config.php         # Configuration générale
│   └── database.php       # Configuration BDD
├── includes/              # Fichiers communs
│   ├── header.php         # En-tête
│   ├── footer.php         # Pied de page
│   ├── functions.php      # Fonctions utilitaires
│   └── security.php       # Fonctions sécurité
├── assets/                # Ressources statiques
│   ├── css/
│   └── js/
├── uploads/               # Fichiers uploadés
├── database/              # Scripts SQL
└── index.php              # Page d'accueil
```

## Fonctionnalités

### Module Administration
- ✅ Gestion des utilisateurs (tous rôles)
- ✅ Gestion des filières
- ✅ Gestion des niveaux
- ✅ Gestion des classes
- ✅ Gestion des matières
- ✅ Gestion des salles
- ✅ Gestion des années académiques
- ✅ Attribution matières-enseignants

### Module Scolarité
- ✅ Gestion des étudiants
- ✅ Inscriptions et réinscriptions
- ✅ Génération de bulletins PDF
- ✅ Gestion des absences
- ✅ Suivi des paiements

### Module Enseignant
- ✅ Saisie des notes
- ✅ Saisie des absences
- ✅ Dépôt de documents
- ✅ Consultation des classes

### Module Étudiant
- ✅ Consultation des notes
- ✅ Téléchargement des bulletins
- ✅ Consultation des absences
- ✅ Consultation emploi du temps
- ✅ Statut de paiement

### Module Emploi du Temps
- ✅ Création des emplois du temps
- ✅ Affectation des salles
- ✅ Détection de conflits
- ✅ Export PDF

### Module BI (Business Intelligence)
- ✅ Statistiques globales
- ✅ Répartition par filière
- ✅ Taux de réussite
- ✅ Taux d'absences
- ✅ Statuts de paiement
- ✅ Graphiques interactifs

## Profils Utilisateurs

### Administrateur
- Accès complet à toutes les fonctionnalités
- Gestion des utilisateurs et paramètres
- Configuration du système

### Scolarité
- Gestion académique complète
- Inscriptions et réinscriptions
- Génération de bulletins
- Suivi des paiements

### Enseignant
- Saisie des notes et absences
- Consultation des classes
- Dépôt de documents pédagogiques

### Étudiant
- Consultation des informations personnelles
- Accès aux notes et bulletins
- Consultation emploi du temps

### Direction
- Accès au module BI
- Statistiques et rapports
- Vue d'ensemble de l'établissement

## Sécurité

- **Authentification**: Sessions PHP sécurisées
- **Protection CSRF**: Tokens pour tous les formulaires
- **Protection XSS**: Échappement des données
- **Validation**: Validation côté serveur
- **Mots de passe**: Hashage avec password_hash()
- **Contrôle d'accès**: Vérification des rôles
- **Logs**: Traçabilité des actions

## Configuration

### Base de données
Modifier `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ipp_school');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Application
Modifier `config/config.php`:
```php
define('BASE_URL', 'http://localhost/ipp');
define('APP_NAME', 'Institut Polytechnique Panafricain');
```

## Génération de PDF

Pour activer la génération de bulletins PDF, installer mPDF:

```bash
composer require mpdf/mpdf
```

Ou télécharger manuellement depuis: https://github.com/mpdf/mpdf

## Support et Maintenance

### Logs
Les logs d'activité sont enregistrés dans la table `logs` de la base de données.

### Sauvegarde
Effectuer des sauvegardes régulières de la base de données via phpMyAdmin.

### Mise à jour
Consulter le fichier CHANGELOG.md pour les mises à jour.

## Dépannage

### Erreur de connexion à la base de données
- Vérifier que MySQL est démarré dans XAMPP
- Vérifier les identifiants dans `config/database.php`
- Vérifier que la base de données `ipp_school` existe

### Erreur 404
- Vérifier que le projet est dans `C:\xampp\htdocs\ipp`
- Vérifier l'URL: `http://localhost/ipp`

### Problème de session
- Vérifier que `session.save_path` est configuré dans php.ini
- Vider le cache du navigateur

## Contribution

Pour contribuer au projet:
1. Créer une branche pour votre fonctionnalité
2. Tester minutieusement
3. Documenter les changements
4. Soumettre une pull request

## Licence

© 2024 Institut Polytechnique Panafricain. Tous droits réservés.

## Contact

Pour toute question ou support:
- Email: support@ipp.edu
- Site web: www.ipp.edu

## Version

Version actuelle: 1.0.0
Date de sortie: Décembre 2024
