<?php
require_once '../config/config.php';
require_login();

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT e.*, u.username, u.email, u.telephone, u.nom, u.prenom, e.classe_id, e.annee_academique_id
                          FROM etudiants e 
                          JOIN users u ON e.user_id = u.id 
                          WHERE e.id = ?");
    $stmt->execute([$id]);
    $etudiant = $stmt->fetch();
    
    if ($etudiant) {
        echo json_encode(['success' => true, 'etudiant' => $etudiant]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Étudiant introuvable']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
