<?php
/**
 * Script d'installation
 */

echo "========================================\n";
echo "GhostOS - Installation\n";
echo "========================================\n\n";

// Vérifier PHP
echo "Vérification PHP... " . (version_compare(PHP_VERSION, '7.4.0', '>=') ? "OK" : "FAILED") . "\n";

// Vérifier extensions
$extensions = ['pdo_mysql', 'json', 'gd', 'curl', 'openssl'];
foreach ($extensions as $ext) {
    echo "Extension $ext... " . (extension_loaded($ext) ? "OK" : "FAILED") . "\n";
}

// Demander configuration DB
echo "\nConfiguration base de données:\n";
$db_host = readline("Hôte [localhost]: ") ?: 'localhost';
$db_name = readline("Nom de la base [ghost_os]: ") ?: 'ghost_os';
$db_user = readline("Utilisateur: ");
$db_pass = readline("Mot de passe: ");

// Tester connexion
try {
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    echo "Connexion MySQL: OK\n";
    
    // Créer base
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name");
    $pdo->exec("USE $db_name");
    
    // Importer SQL
    $sql = file_get_contents('database.sql');
    $pdo->exec($sql);
    echo "Base de données créée: OK\n";
    
} catch (PDOException $e) {
    die("Erreur MySQL: " . $e->getMessage() . "\n");
}

// Créer fichier config
$config = "<?php
define('DB_HOST', '$db_host');
define('DB_NAME', '$db_name');
define('DB_USER', '$db_user');
define('DB_PASS', '$db_pass');
define('DB_CHARSET', 'utf8mb4');
?>";

file_put_contents('config.php', $config);
echo "Fichier config.php créé\n";

// Créer dossiers
$dirs = ['uploads', 'uploads/screens', 'uploads/files', 'logs'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    echo "Dossier $dir créé\n";
}

// Mot de passe admin par défaut
echo "\n";
$admin_email = readline("Email admin [admin@ghostos.com]: ") ?: 'admin@ghostos.com';
$admin_pass = readline("Mot de passe admin [Admin123!]: ") ?: 'Admin123!';

$hash = password_hash($admin_pass, PASSWORD_BCRYPT);
$api_key = 'ghost_' . bin2hex(random_bytes(16));

$pdo->prepare("
    UPDATE users SET email = ?, password_hash = ?, api_key = ? WHERE username = 'admin'
")->execute([$admin_email, $hash, $api_key]);

echo "Admin créé\n";
echo "Email: $admin_email\n";
echo "API Key: $api_key\n";

echo "\n========================================\n";
echo "INSTALLATION TERMINÉE!\n";
echo "========================================\n";
echo "Panel: http://votre-domaine/panel/\n";
echo "Admin: http://votre-domaine/admin/\n";
echo "API: http://votre-domaine/api/\n";
echo "WebSocket: ws://votre-domaine:8080\n";
