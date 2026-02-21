<?php
/**
 * API Authentification
 */

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// ============================================
// REGISTER
// ============================================
if ($method === 'POST' && ($_GET['action'] ?? '') === 'register') {
    
    $username = $input['username'] ?? '';
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $input['password'] ?? '';
    
    if (!$email || strlen($password) < 8) {
        sendError('Email invalide ou mot de passe trop court');
    }
    
    // Vérifier si l'utilisateur existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendError('Email déjà utilisé', 409);
    }
    
    // Créer l'utilisateur
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $api_key = bin2hex(random_bytes(32));
    
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, api_key, trial_start, trial_end)
        VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY))
    ");
    
    if ($stmt->execute([$username ?: explode('@', $email)[0], $email, $hash, $api_key])) {
        sendJson([
            'success' => true,
            'api_key' => $api_key,
            'message' => 'Inscription réussie'
        ], 201);
    } else {
        sendError('Erreur lors de l\'inscription', 500);
    }
}

// ============================================
// LOGIN
// ============================================
if ($method === 'POST' && ($_GET['action'] ?? '') === 'login') {
    
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        
        if ($user['account_status'] !== 'active') {
            sendError('Compte ' . $user['account_status'], 403);
        }
        
        // Mettre à jour la dernière connexion
        $pdo->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?")
            ->execute([$_SERVER['REMOTE_ADDR'] ?? null, $user['id']]);
        
        logActivity($pdo, $user['id'], null, 'login');
        
        sendJson([
            'success' => true,
            'api_key' => $user['api_key'],
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'account_type' => $user['account_type'],
                'trial_end' => $user['trial_end'],
                'subscription_end' => $user['subscription_end']
            ]
        ]);
    } else {
        sendError('Email ou mot de passe incorrect', 401);
    }
}

// ============================================
// VERIFY
// ============================================
if ($method === 'GET' && ($_GET['action'] ?? '') === 'verify') {
    $user = checkApiKey($pdo);
    
    sendJson([
        'valid' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'account_type' => $user['account_type']
        ]
    ]);
}

sendError('Action non valide', 400);
?>
