<?php
/**
 * Point d'entrée principal
 * Route les requêtes vers les bons endpoints
 */

require_once 'config.php';

$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Router simple
if (preg_match('/^\/api\/v1\/([a-z]+)/', $request, $matches)) {
    $endpoint = $matches[1];
    
    switch ($endpoint) {
        case 'auth':
            require 'api/auth.php';
            break;
        case 'devices':
            require 'api/devices.php';
            break;
        case 'commands':
            require 'api/commands.php';
            break;
        case 'upload':
            require 'api/upload.php';
            break;
        default:
            sendError('Endpoint not found', 404);
    }
} elseif ($request === '/' || $request === '/panel') {
    require 'panel/dashboard.php';
} elseif (strpos($request, '/admin') === 0) {
    require 'admin/dashboard.php';
} else {
    sendError('Not found', 404);
}
?>
