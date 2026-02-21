<?php
/**
 * API Commandes
 */

require_once '../config.php';
$user = checkApiKey($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$device_id = $_GET['device_id'] ?? '';

if (!$device_id) {
    sendError('device_id requis');
}

// Vérifier que l'appareil appartient à l'utilisateur
$stmt = $pdo->prepare("SELECT id FROM devices WHERE device_id = ? AND user_id = ?");
$stmt->execute([$device_id, $user['id']]);
$device = $stmt->fetch();

if (!$device) {
    sendError('Appareil non trouvé', 404);
}

$deviceDbId = $device['id'];

// ============================================
// GET - Récupérer les commandes en attente
// ============================================
if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT * FROM commands
        WHERE device_id = ? AND status = 'pending'
        ORDER BY created_at ASC
        LIMIT 50
    ");
    $stmt->execute([$deviceDbId]);
    $commands = $stmt->fetchAll();
    
    // Marquer comme envoyées
    if (!empty($commands)) {
        $ids = array_column($commands, 'id');
        $pdo->prepare("UPDATE commands SET status = 'sent' WHERE id IN (" . implode(',', $ids) . ")")
            ->execute();
    }
    
    sendJson(['commands' => $commands]);
}

// ============================================
// POST - Ajouter une commande
// ============================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $command = $input['command'] ?? '';
    $params = $input['params'] ?? '';
    
    $stmt = $pdo->prepare("
        INSERT INTO commands (device_id, command, parameters, status)
        VALUES (?, ?, ?, 'pending')
    ");
    
    if ($stmt->execute([$deviceDbId, $command, $params])) {
        logActivity($pdo, $user['id'], $deviceDbId, 'command_sent', $command);
        sendJson(['success' => true, 'command_id' => $pdo->lastInsertId()]);
    } else {
        sendError('Erreur lors de l\'ajout', 500);
    }
}

// ============================================
// PUT - Mettre à jour le résultat
// ============================================
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $command_id = $_GET['id'] ?? 0;
    $status = $input['status'] ?? 'executed';
    $result = $input['result'] ?? '';
    $error = $input['error'] ?? '';
    
    $stmt = $pdo->prepare("
        UPDATE commands
        SET status = ?, executed_at = NOW(), result = ?, error = ?
        WHERE id = ? AND device_id = ?
    ");
    $stmt->execute([$status, $result, $error, $command_id, $deviceDbId]);
    
    sendJson(['success' => true]);
}

sendError('Méthode non autorisée', 405);
?>
