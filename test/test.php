<?php
// vtu_api.php
// Stockage des contrôles VTU dans un fichier JSON sur le serveur
// et purge automatique des données de plus de 40 jours.

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$file = __DIR__ . '/vtu_data.json';
$daysToKeep = 40;

function loadRecords($file) {
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    if (!$json) return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveRecords($file, $records) {
    file_put_contents($file, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function purgeOld($records, $daysToKeep) {
    $limit = time() - ($daysToKeep * 24 * 60 * 60);
    $filtered = [];
    foreach ($records as $rec) {
        if (!isset($rec['date'])) {
            $filtered[] = $rec;
            continue;
        }
        $ts = strtotime($rec['date']);
        if ($ts === false || $ts >= $limit) {
            $filtered[] = $rec;
        }
    }
    return $filtered;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method === 'GET') {
    if ($action === 'csv') {
        // Export CSV
        $records = purgeOld(loadRecords($file), $daysToKeep);
        saveRecords($file, $records);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="verification_vtu.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['date', 'agent', 'item', 'etat', 'remarque', 'obsGenerale'], ';');

        foreach ($records as $rec) {
            $date = isset($rec['date']) ? $rec['date'] : '';
            $agent = isset($rec['conducteur']) ? $rec['conducteur'] : '';
            $obsGen = isset($rec['observations']) ? $rec['observations'] : '';
            $items = isset($rec['items']) && is_array($rec['items']) ? $rec['items'] : [];
            foreach ($items as $it) {
                $itemName = isset($it['itemName']) ? $it['itemName'] : '';
                $etat = isset($it['etat']) ? $it['etat'] : '';
                $rem = isset($it['remarque']) ? $it['remarque'] : '';
                fputcsv($output, [$date, $agent, $itemName, $etat, $rem, $obsGen], ';');
            }
        }
        fclose($output);
        exit;
    }

    // GET normal : renvoie la liste JSON
    header('Content-Type: application/json; charset=utf-8');
    $records = purgeOld(loadRecords($file), $daysToKeep);
    saveRecords($file, $records);
    echo json_encode($records, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    // Effacer toutes les données
    if ($action === 'clear') {
        saveRecords($file, []);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }

    // Ajout d’un nouveau contrôle
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON invalide']);
        exit;
    }

    $records = loadRecords($file);
    $records[] = $data;
    $records = purgeOld($records, $daysToKeep);
    saveRecords($file, $records);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($records, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo "Méthode non autorisée";
