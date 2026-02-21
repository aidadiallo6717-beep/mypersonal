<?php
/**
 * Configuration du serveur GhostOS
 */

// ============================================
// BASE DE DONNÉES
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'ghost_os');
define('DB_USER', 'ghost_user');
define('DB_PASS', 'VotreMotDePasseIci123!');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// CHEMINS
// ============================================
define('BASE_URL', 'https://votre-domaine.com');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('LOG_DIR', __DIR__ . '/logs/');

// Créer les dossiers si nécessaire
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!is_dir(LOG_DIR)) mkdir(LOG_DIR, 0755, true);

// ============================================
// SÉCURITÉ
// ============================================
define('JWT_SECRET', 'votre_secret_jwt_tres_long_et_secure_2026');
define('API_RATE_LIMIT', 1000); // Requêtes par heure
define('SESSION_TIMEOUT', 3600); // 1 heure

// ============================================
// CONNEXION BASE DE DONNÉES
// ============================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed']));
}

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Envoie une réponse JSON
 */
function sendJson($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Envoie une erreur
 */
function sendError($message, $code = 400) {
    sendJson(['error' => $message], $code);
}

/**
 * Vérifie une clé API
 */
function checkApiKey($pdo) {
    $headers = getallheaders();
    $api_key = $headers['X-API-Key'] ?? '';
    
    if (!$api_key) {
        sendError('API key required', 401);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = ?");
    $stmt->execute([$api_key]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('Invalid API key', 403);
    }
    
    return $user;
}

/**
 * Journalise une activité
 */
function logActivity($pdo, $user_id, $device_id, $action, $details = null) {
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, device_id, action, details, ip)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $device_id,
        $action,
        $details ? json_encode($details) : null,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
?>
