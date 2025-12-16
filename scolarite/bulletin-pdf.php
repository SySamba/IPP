<?php
require_once '../config/config.php';
require_role(['admin', 'scolarite']);

$page_title = 'Bulletin PDF';
$db = Database::getInstance()->getConnection();

// Récupérer les paramètres
$etudiant_id = intval($_GET['etudiant_id'] ?? 0);
$periode_id = intval($_GET['periode_id'] ?? 1); // 1 = Semestre 1, 2 = Semestre 2

if (!$etudiant_id) {
    set_flash_message('Étudiant non spécifié', 'danger');
    redirect('scolarite/bulletins.php');
}

// Récupérer l'étudiant avec informations
$stmt = $db->prepare("SELECT e.*, u.nom, u.prenom, c.libelle as classe, c.id as classe_id,
                      f.libelle as filiere, n.libelle as niveau, aa.libelle as annee, aa.id as annee_id
                      FROM etudiants e
                      JOIN users u ON e.user_id = u.id
                      JOIN classes c ON e.classe_id = c.id
                      JOIN filieres f ON c.filiere_id = f.id
                      JOIN niveaux n ON c.niveau_id = n.id
                      JOIN annees_academiques aa ON e.annee_academique_id = aa.id
                      WHERE e.id = ?");
$stmt->execute([$etudiant_id]);
$etudiant = $stmt->fetch();

if (!$etudiant) {
    set_flash_message('Étudiant introuvable', 'danger');
    redirect('scolarite/bulletins.php');
}

// Calculer l'effectif de la classe
$stmt = $db->prepare("SELECT COUNT(*) as effectif FROM etudiants WHERE classe_id = ? AND annee_academique_id = ? AND statut = 'actif'");
$stmt->execute([$etudiant['classe_id'], $etudiant['annee_id']]);
$effectif_classe = $stmt->fetch()['effectif'];

// Fonction pour obtenir l'appréciation
function get_appreciation($moyenne) {
    if ($moyenne >= 18) return 'Excellent';
    if ($moyenne >= 16) return 'Très bien';
    if ($moyenne >= 14) return 'Bien';
    if ($moyenne >= 12) return 'Assez bien';
    if ($moyenne >= 10) return 'Passable';
    if ($moyenne >= 8) return 'Insuffisant';
    return 'Médiocre';
}

// Vérifier si la colonne semestre existe
$has_semestre = false;
try {
    $db->query("SELECT semestre FROM notes LIMIT 1");
    $has_semestre = true;
} catch (Exception $e) {
    // La colonne n'existe pas encore
}

// Récupérer les notes de l'étudiant pour le semestre
if ($has_semestre) {
    $stmt = $db->prepare("SELECT n.*, cm.coefficient as coef_matiere, m.libelle as matiere, m.code as matiere_code
                          FROM notes n
                          JOIN classe_matieres cm ON n.classe_matiere_id = cm.id
                          JOIN matieres m ON cm.matiere_id = m.id
                          WHERE n.etudiant_id = ? AND n.semestre = ?
                          ORDER BY m.libelle");
    $stmt->execute([$etudiant_id, $periode_id]);
} else {
    $stmt = $db->prepare("SELECT n.*, cm.coefficient as coef_matiere, m.libelle as matiere, m.code as matiere_code
                          FROM notes n
                          JOIN classe_matieres cm ON n.classe_matiere_id = cm.id
                          JOIN matieres m ON cm.matiere_id = m.id
                          WHERE n.etudiant_id = ?
                          ORDER BY m.libelle");
    $stmt->execute([$etudiant_id]);
}
$notes = $stmt->fetchAll();

// Calculer moyenne par matière
$matieres_data = [];
foreach ($notes as $note) {
    $matiere_key = $note['matiere_code'];
    if (!isset($matieres_data[$matiere_key])) {
        $matieres_data[$matiere_key] = [
            'matiere' => $note['matiere'],
            'notes' => [],
            'coef' => $note['coef_matiere'],
            'total_points' => 0,
            'total_coef' => 0
        ];
    }
    $note_sur_20 = ($note['note'] / $note['note_sur']) * 20;
    $matieres_data[$matiere_key]['notes'][] = [
        'note' => $note_sur_20,
        'coef' => $note['coefficient']
    ];
    $matieres_data[$matiere_key]['total_points'] += $note_sur_20 * $note['coefficient'];
    $matieres_data[$matiere_key]['total_coef'] += $note['coefficient'];
}

// Calculer moyennes des matières et moyenne générale
$somme_moyennes = 0;
$somme_coefs = 0;
$matieres = [];

foreach ($matieres_data as $key => $data) {
    if ($data['total_coef'] > 0) {
        $moyenne_matiere = $data['total_points'] / $data['total_coef'];
        $matieres[$key] = [
            'matiere' => $data['matiere'],
            'moyenne' => $moyenne_matiere,
            'coef' => $data['coef'],
            'appreciation' => get_appreciation($moyenne_matiere)
        ];
        $somme_moyennes += $moyenne_matiere * $data['coef'];
        $somme_coefs += $data['coef'];
    }
}

$moyenne_generale = $somme_coefs > 0 ? $somme_moyennes / $somme_coefs : 0;

// Calculer le rang de l'étudiant dans la classe
$stmt = $db->prepare("
    SELECT etudiant_id, AVG(moyenne_matiere * coef_matiere) / AVG(coef_matiere) as moyenne_gen
    FROM (
        SELECT n.etudiant_id, m.id as matiere_id, cm.coefficient as coef_matiere,
               AVG((n.note / n.note_sur) * 20 * n.coefficient) / AVG(n.coefficient) as moyenne_matiere
        FROM notes n
        JOIN classe_matieres cm ON n.classe_matiere_id = cm.id
        JOIN matieres m ON cm.matiere_id = m.id
        JOIN etudiants e ON n.etudiant_id = e.id
        WHERE e.classe_id = ? AND e.statut = 'actif'
        GROUP BY n.etudiant_id, m.id, cm.coefficient
    ) as moyennes_matieres
    GROUP BY etudiant_id
    ORDER BY moyenne_gen DESC
");
$stmt->execute([$etudiant['classe_id']]);
$classement = $stmt->fetchAll();

$rang = 0;
foreach ($classement as $index => $row) {
    if ($row['etudiant_id'] == $etudiant_id) {
        $rang = $index + 1;
        break;
    }
}

// Calculer la moyenne de la classe
$moyenne_classe = 0;
if (count($classement) > 0) {
    $total = 0;
    foreach ($classement as $row) {
        $total += $row['moyenne_gen'];
    }
    $moyenne_classe = $total / count($classement);
}

$bulletin = [
    'periode' => ['libelle' => 'Semestre ' . $periode_id],
    'matieres' => $matieres,
    'moyenne_generale' => $moyenne_generale,
    'rang' => $rang,
    'effectif' => $effectif_classe,
    'moyenne_classe' => $moyenne_classe,
    'appreciation_generale' => get_appreciation($moyenne_generale),
    'semestre' => $periode_id
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulletin - <?php echo escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: white;
        }
        .bulletin-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 15mm;
        }
        .bulletin-header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .bulletin-header h3 {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        .bulletin-header h5 {
            font-size: 14px;
            margin: 5px 0;
        }
        .logo-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin: 0 auto;
        }
        .qr-placeholder {
            width: 60px;
            height: 60px;
            border: 2px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
        }
        .bulletin-info {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            margin-right: 5px;
        }
        .info-value {
            color: #333;
        }
        .bulletin-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 12px;
        }
        .bulletin-table thead th {
            background: #667eea;
            color: white;
            padding: 8px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #5568d3;
        }
        .bulletin-table tbody td {
            padding: 6px 8px;
            border: 1px solid #dee2e6;
        }
        .bulletin-table tfoot td {
            background: #f8f9fa;
            padding: 8px;
            border: 1px solid #dee2e6;
            font-weight: bold;
        }
        .note-cell {
            font-weight: bold;
            font-size: 13px;
        }
        .note-pass {
            color: #28a745;
        }
        .note-fail {
            color: #dc3545;
        }
        .appreciation-excellent { color: #28a745; font-weight: 600; }
        .appreciation-bien { color: #17a2b8; font-weight: 600; }
        .appreciation-assez-bien { color: #007bff; font-weight: 600; }
        .appreciation-passable { color: #ffc107; font-weight: 600; }
        .appreciation-insuffisant { color: #dc3545; font-weight: 600; }
        .bulletin-results {
            display: flex;
            gap: 15px;
            margin: 15px 0;
        }
        .result-box {
            flex: 1;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .moyenne-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .result-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .result-value {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
        }
        .result-appreciation {
            font-size: 13px;
            font-weight: 600;
            margin-top: 5px;
        }
        .bulletin-footer {
            margin-top: 20px;
        }
        .appreciation-text {
            background: #f8f9fa;
            padding: 10px;
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .signatures {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
        }
        .signature-box {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            border-top: 2px solid #333;
            margin-bottom: 5px;
        }
        .signature-label {
            font-size: 11px;
            font-weight: 600;
            color: #666;
        }
        @media print {
            body * {
                visibility: hidden;
            }
            .bulletin-container, .bulletin-container * {
                visibility: visible;
            }
            .bulletin-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                max-width: 100%;
                padding: 10mm;
                box-shadow: none;
                page-break-after: always;
            }
            .no-print {
                display: none !important;
            }
        }
        @page {
            size: A4;
            margin: 10mm;
        }
    </style>
</head>
<body>
<div class="bulletin-container">
    <!-- En-tête compact -->
    <div class="bulletin-header">
        <div class="row align-items-center">
            <div class="col-3 text-center">
                <div class="logo-circle">
                    <i class="fas fa-graduation-cap fa-3x"></i>
                </div>
            </div>
            <div class="col-6 text-center">
                <h3 class="mb-1">INSTITUT POLYTECHNIQUE PANAFRICAIN</h3>
                <h5 class="text-primary mb-0">BULLETIN DE NOTES - <?php echo escape_html($bulletin['periode']['libelle']); ?></h5>
                <small>Année académique: <?php echo escape_html($etudiant['annee']); ?></small>
            </div>
            <div class="col-3 text-end">
                <div class="qr-placeholder">
                    <i class="fas fa-qrcode fa-3x text-muted"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Informations étudiant compactes -->
    <div class="bulletin-info">
        <div class="row g-2">
            <div class="col-6">
                <span class="info-label">Nom et Prénom:</span>
                <span class="info-value"><?php echo escape_html($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></span>
            </div>
            <div class="col-3">
                <span class="info-label">Matricule:</span>
                <span class="info-value"><?php echo escape_html($etudiant['matricule']); ?></span>
            </div>
            <div class="col-3">
                <span class="info-label">Classe:</span>
                <span class="info-value"><?php echo escape_html($etudiant['classe']); ?></span>
            </div>
        </div>
    </div>

    <?php if (empty($bulletin['matieres'])): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Aucune note enregistrée pour cette période.
    </div>
    <?php else: ?>
    
    <!-- Tableau des notes compact -->
    <table class="bulletin-table">
        <thead>
            <tr>
                <th>Matière</th>
                <th width="60">Coef.</th>
                <th width="70">Note/20</th>
                <th width="70">Total</th>
                <th width="120">Appréciation</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_points = 0;
            $total_coefs = 0;
            foreach ($bulletin['matieres'] as $data): 
                $total = $data['moyenne'] * $data['coef'];
                $total_points += $total;
                $total_coefs += $data['coef'];
            ?>
            <tr>
                <td><?php echo escape_html($data['matiere']); ?></td>
                <td class="text-center"><?php echo number_format($data['coef'], 1); ?></td>
                <td class="text-center note-cell <?php echo $data['moyenne'] >= 10 ? 'note-pass' : 'note-fail'; ?>">
                    <?php echo number_format($data['moyenne'], 2); ?>
                </td>
                <td class="text-center"><?php echo number_format($total, 2); ?></td>
                <td class="text-center appreciation-<?php 
                    echo $data['moyenne'] >= 16 ? 'excellent' : 
                        ($data['moyenne'] >= 14 ? 'bien' : 
                        ($data['moyenne'] >= 12 ? 'assez-bien' : 
                        ($data['moyenne'] >= 10 ? 'passable' : 'insuffisant'))); 
                ?>">
                    <?php echo $data['appreciation']; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td><strong>TOTAL</strong></td>
                <td class="text-center"><strong><?php echo number_format($total_coefs, 1); ?></strong></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>
    
    <!-- Résultats compacts -->
    <div class="bulletin-results">
        <div class="result-box moyenne-box">
            <div class="result-label">MOYENNE GÉNÉRALE</div>
            <div class="result-value"><?php echo number_format($bulletin['moyenne_generale'], 2); ?>/20</div>
            <div class="result-appreciation appreciation-<?php 
                echo $bulletin['moyenne_generale'] >= 16 ? 'excellent' : 
                    ($bulletin['moyenne_generale'] >= 14 ? 'bien' : 
                    ($bulletin['moyenne_generale'] >= 12 ? 'assez-bien' : 
                    ($bulletin['moyenne_generale'] >= 10 ? 'passable' : 'insuffisant'))); 
            ?>">
                <?php echo $bulletin['appreciation_generale']; ?>
            </div>
        </div>
        <div class="result-box">
            <div class="result-label">RANG</div>
            <div class="result-value"><?php echo $bulletin['rang']; ?><sup>e</sup>/<?php echo $bulletin['effectif']; ?></div>
        </div>
        <div class="result-box">
            <div class="result-label">MOYENNE CLASSE</div>
            <div class="result-value"><?php echo number_format($bulletin['moyenne_classe'], 2); ?>/20</div>
        </div>
    </div>
    
    <!-- Appréciation et signatures -->
    <div class="bulletin-footer">
        <div class="appreciation-text">
            <strong>Appréciation:</strong>
            <?php
            if ($bulletin['moyenne_generale'] >= 16) {
                echo "Excellent travail ! Continuez sur cette lancée.";
            } elseif ($bulletin['moyenne_generale'] >= 14) {
                echo "Très bon travail. Vous êtes sur la bonne voie.";
            } elseif ($bulletin['moyenne_generale'] >= 12) {
                echo "Bon travail. Vous pouvez encore vous améliorer.";
            } elseif ($bulletin['moyenne_generale'] >= 10) {
                echo "Travail satisfaisant. Des efforts supplémentaires sont nécessaires.";
            } else {
                echo "Résultats insuffisants. Un travail sérieux et régulier s'impose.";
            }
            ?>
        </div>
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Le Directeur des Études</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Le Directeur</div>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-3 no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <a href="bulletins.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
    
    <?php endif; ?>
</div>
</body>
</html>
