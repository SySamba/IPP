# Guide d'installation - IPP School Management

## Prérequis

- **XAMPP** (Apache + MySQL + PHP 7.4 ou supérieur)
- Navigateur web moderne (Chrome, Firefox, Edge)
- Minimum 2 GB d'espace disque

## Installation pas à pas

### 1. Installation de XAMPP

1. Télécharger XAMPP depuis: https://www.apachefriends.org/
2. Installer XAMPP dans `C:\xampp`
3. Démarrer le panneau de contrôle XAMPP
4. Démarrer les services **Apache** et **MySQL**

### 2. Copie des fichiers

1. Copier le dossier `ipp` dans `C:\xampp\htdocs\`
2. Le chemin final doit être: `C:\xampp\htdocs\ipp`

### 3. Configuration de la base de données

#### Option A: Via phpMyAdmin (Recommandé)

1. Ouvrir phpMyAdmin: http://localhost/phpmyadmin
2. Cliquer sur "Nouveau" dans le panneau de gauche
3. Créer une base de données nommée: `ipp_school`
4. Sélectionner l'encodage: `utf8mb4_unicode_ci`
5. Cliquer sur l'onglet "Importer"
6. Sélectionner le fichier: `C:\xampp\htdocs\ipp\database\schema.sql`
7. Cliquer sur "Exécuter"

#### Option B: Via ligne de commande

```bash
cd C:\xampp\mysql\bin
mysql -u root -p
CREATE DATABASE ipp_school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ipp_school;
SOURCE C:\xampp\htdocs\ipp\database\schema.sql;
EXIT;
```

### 4. Configuration de l'application

1. Ouvrir le fichier: `C:\xampp\htdocs\ipp\config\database.php`
2. Vérifier les paramètres de connexion:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ipp_school');
define('DB_USER', 'root');
define('DB_PASS', '');  // Laisser vide par défaut
```

3. Si vous avez défini un mot de passe pour MySQL, modifier `DB_PASS`

### 5. Vérification des permissions

1. Vérifier que le dossier `uploads` est accessible en écriture
2. Sous Windows, clic droit sur le dossier > Propriétés > Sécurité
3. S'assurer que l'utilisateur a les droits de lecture/écriture

### 6. Accès à l'application

1. Ouvrir votre navigateur
2. Accéder à: http://localhost/ipp
3. Vous devriez voir la page de connexion

### 7. Première connexion

**Identifiants par défaut:**
- **Utilisateur:** admin
- **Mot de passe:** admin123

**⚠️ IMPORTANT:** Changez immédiatement le mot de passe après la première connexion!

## Configuration initiale

### 1. Créer une année académique

1. Se connecter en tant qu'admin
2. Aller dans: **Administration > Années académiques**
3. Cliquer sur "Nouvelle année"
4. Remplir les informations:
   - Libellé: 2024-2025
   - Date début: 01/09/2024
   - Date fin: 30/06/2025
   - Statut: Active
5. Enregistrer

### 2. Créer des périodes/semestres

1. Dans la liste des années, cliquer sur l'icône calendrier
2. Créer les périodes (ex: Semestre 1, Semestre 2)
3. Définir les dates pour chaque période

### 3. Configurer les filières

1. Aller dans: **Administration > Filières**
2. Les filières par défaut sont déjà créées
3. Modifier ou ajouter selon vos besoins

### 4. Créer des classes

1. Aller dans: **Administration > Classes**
2. Créer une nouvelle classe
3. Associer: Filière, Niveau, Année académique
4. Définir l'effectif maximum

### 5. Associer les matières aux classes

1. Dans la liste des classes, cliquer sur l'icône livre
2. Ajouter les matières pour cette classe
3. Définir les coefficients
4. Assigner les enseignants (optionnel)

### 6. Créer des utilisateurs

#### Créer un enseignant:
1. **Administration > Utilisateurs**
2. Nouveau utilisateur
3. Rôle: Enseignant
4. Remplir les informations

#### Créer un étudiant:
1. **Scolarité > Étudiants**
2. Nouvel étudiant
3. Remplir toutes les informations
4. Le système crée automatiquement le compte utilisateur

### 7. Inscrire les étudiants

1. **Scolarité > Inscriptions**
2. Nouvelle inscription
3. Sélectionner l'étudiant, la classe et l'année
4. Valider l'inscription

## Dépannage

### Erreur "Cannot connect to database"

**Solution:**
1. Vérifier que MySQL est démarré dans XAMPP
2. Vérifier les identifiants dans `config/database.php`
3. Vérifier que la base de données `ipp_school` existe

### Erreur 404 - Page not found

**Solution:**
1. Vérifier que le dossier est bien dans `C:\xampp\htdocs\ipp`
2. Vérifier l'URL: http://localhost/ipp (pas http://localhost/ipp/ipp)
3. Redémarrer Apache dans XAMPP

### Erreur "Session not working"

**Solution:**
1. Ouvrir `php.ini` (via XAMPP Control Panel > Config)
2. Chercher `session.save_path`
3. S'assurer que le chemin existe et est accessible en écriture
4. Redémarrer Apache

### Problème d'upload de fichiers

**Solution:**
1. Vérifier que le dossier `uploads` existe
2. Vérifier les permissions du dossier
3. Dans `php.ini`, vérifier:
   - `upload_max_filesize = 10M`
   - `post_max_size = 10M`
4. Redémarrer Apache

### Erreur "Headers already sent"

**Solution:**
1. Vérifier qu'il n'y a pas d'espace avant `<?php` dans les fichiers
2. Vérifier l'encodage des fichiers (UTF-8 sans BOM)
3. Vider le cache du navigateur

## Configuration avancée

### Changer l'URL de base

Si vous installez dans un sous-dossier différent:

1. Ouvrir `config/config.php`
2. Modifier la ligne:
```php
define('BASE_URL', 'http://localhost/votre_dossier');
```

### Activer les erreurs PHP (développement uniquement)

Dans `config/config.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

**⚠️ Désactiver en production!**

### Configurer l'envoi d'emails (optionnel)

Pour activer l'envoi d'emails, installer PHPMailer:
```bash
composer require phpmailer/phpmailer
```

### Sauvegardes automatiques

1. Créer un script de sauvegarde
2. Utiliser le planificateur de tâches Windows
3. Exporter régulièrement la base de données

## Maintenance

### Sauvegarde de la base de données

**Via phpMyAdmin:**
1. Sélectionner la base `ipp_school`
2. Onglet "Exporter"
3. Format: SQL
4. Télécharger le fichier

**Via ligne de commande:**
```bash
cd C:\xampp\mysql\bin
mysqldump -u root -p ipp_school > backup.sql
```

### Restauration

**Via phpMyAdmin:**
1. Sélectionner la base `ipp_school`
2. Onglet "Importer"
3. Sélectionner le fichier de sauvegarde
4. Exécuter

### Mise à jour de l'application

1. Sauvegarder la base de données
2. Sauvegarder le dossier `uploads`
3. Remplacer les fichiers de l'application
4. Exécuter les scripts de migration si nécessaire
5. Vider le cache du navigateur

## Sécurité en production

### ⚠️ Avant de mettre en production:

1. **Changer le mot de passe admin**
2. **Désactiver l'affichage des erreurs:**
   ```php
   error_reporting(0);
   ini_set('display_errors', 0);
   ```
3. **Définir un mot de passe MySQL**
4. **Activer HTTPS**
5. **Configurer des sauvegardes automatiques**
6. **Restreindre l'accès à phpMyAdmin**
7. **Mettre à jour régulièrement PHP et MySQL**

## Support

### Documentation
- Consulter le fichier `README.md`
- Consulter les commentaires dans le code

### Logs
- Logs Apache: `C:\xampp\apache\logs\error.log`
- Logs PHP: Vérifier `php.ini` pour `error_log`
- Logs application: Table `logs` dans la base de données

### Ressources
- Documentation PHP: https://www.php.net/docs.php
- Documentation MySQL: https://dev.mysql.com/doc/
- Documentation Bootstrap: https://getbootstrap.com/docs/

## Checklist de déploiement

- [ ] XAMPP installé et fonctionnel
- [ ] Base de données créée et importée
- [ ] Configuration vérifiée
- [ ] Première connexion réussie
- [ ] Mot de passe admin changé
- [ ] Année académique créée
- [ ] Périodes configurées
- [ ] Classes créées
- [ ] Utilisateurs créés
- [ ] Sauvegarde configurée

## Performances

### Optimisation MySQL

Dans `my.ini` (XAMPP):
```ini
innodb_buffer_pool_size = 256M
max_connections = 100
query_cache_size = 32M
```

### Optimisation PHP

Dans `php.ini`:
```ini
memory_limit = 256M
max_execution_time = 60
opcache.enable = 1
```

## Contact

Pour toute question ou problème:
- Email: support@ipp.edu
- Documentation: Consulter README.md
