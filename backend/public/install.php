<?php
/**
 * CoinHit Installation Wizard
 * Auto-install script for ThemeForest customers
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('INSTALL_DIR', dirname(__DIR__));
define('PUBLIC_DIR', __DIR__);

// Check if already installed
if (file_exists(INSTALL_DIR . '/.installed')) {
    die('Application is already installed. Delete .installed file to reinstall.');
}

$step = $_GET['step'] ?? 1;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CoinHit Installation Wizard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden; }
        .header { background: #2563eb; color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .content { padding: 40px; }
        .step-indicator { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .step { flex: 1; text-align: center; padding: 10px; position: relative; }
        .step.active { color: #2563eb; font-weight: bold; }
        .step.completed { color: #10b981; }
        .step::after { content: ''; position: absolute; top: 50%; right: -50%; width: 100%; height: 2px; background: #e5e7eb; z-index: -1; }
        .step:last-child::after { display: none; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #374151; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 5px; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #2563eb; }
        .btn { display: inline-block; padding: 12px 30px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; border: none; font-size: 16px; cursor: pointer; transition: all 0.3s; }
        .btn:hover { background: #1d4ed8; transform: translateY(-2px); }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        .check-item { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #e5e7eb; }
        .check-item .status { font-weight: bold; }
        .check-item .status.pass { color: #10b981; }
        .check-item .status.fail { color: #ef4444; }
        .progress-bar { width: 100%; height: 30px; background: #e5e7eb; border-radius: 15px; overflow: hidden; margin: 20px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #2563eb, #10b981); transition: width 0.5s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 CoinHit Installation</h1>
            <p>Welcome! Let's set up your betting prediction platform</p>
        </div>
        
        <div class="content">
            <?php
            switch ($step) {
                case 1:
                    displayWelcome();
                    break;
                case 2:
                    checkRequirements();
                    break;
                case 3:
                    databaseSetup();
                    break;
                case 4:
                    adminSetup();
                    break;
                case 5:
                    finalizeInstallation();
                    break;
                default:
                    displayWelcome();
            }
            ?>
        </div>
    </div>
</body>
</html>

<?php

function displayWelcome() {
    ?>
    <h2>Welcome to CoinHit!</h2>
    <p style="margin: 20px 0; line-height: 1.6;">
        This wizard will help you install CoinHit betting prediction platform on your server.
        The installation process will take approximately 5 minutes.
    </p>
    
    <div class="alert alert-warning">
        <strong>Before you begin:</strong><br>
        • Make sure you have a MySQL database created<br>
        • Have your database credentials ready<br>
        • Ensure your server meets the minimum requirements
    </div>
    
    <h3 style="margin-top: 30px;">What will be installed?</h3>
    <ul style="margin: 15px 0; padding-left: 20px; line-height: 2;">
        <li>✅ Database tables and structure</li>
        <li>✅ Sample data (optional)</li>
        <li>✅ Admin user account</li>
        <li>✅ Configuration files</li>
    </ul>
    
    <div style="margin-top: 40px;">
        <a href="?step=2" class="btn">Start Installation →</a>
    </div>
    <?php
}

function checkRequirements() {
    $checks = [
        'PHP Version >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'Phalcon Extension' => extension_loaded('phalcon'),
        'PDO Extension' => extension_loaded('pdo'),
        'MySQL Extension' => extension_loaded('pdo_mysql'),
        'MBString Extension' => extension_loaded('mbstring'),
        'JSON Extension' => extension_loaded('json'),
        'OpenSSL Extension' => extension_loaded('openssl'),
        'Cache Directory Writable' => is_writable(INSTALL_DIR . '/cache') || mkdir(INSTALL_DIR . '/cache', 0775, true),
        'Logs Directory Writable' => is_writable(INSTALL_DIR . '/logs') || mkdir(INSTALL_DIR . '/logs', 0775, true),
    ];
    
    $allPassed = !in_array(false, $checks);
    
    ?>
    <h2>Server Requirements Check</h2>
    
    <div style="margin: 30px 0;">
        <?php foreach ($checks as $name => $passed): ?>
            <div class="check-item">
                <span><?= $name ?></span>
                <span class="status <?= $passed ? 'pass' : 'fail' ?>">
                    <?= $passed ? '✓ PASS' : '✗ FAIL' ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($allPassed): ?>
        <div class="alert alert-success">
            ✓ All requirements met! You can proceed with the installation.
        </div>
        <a href="?step=3" class="btn">Continue →</a>
    <?php else: ?>
        <div class="alert alert-error">
            ✗ Some requirements are not met. Please fix the issues above before continuing.
        </div>
        <a href="?step=2" class="btn btn-secondary">Check Again</a>
    <?php endif; ?>
    <?php
}

function databaseSetup() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dbHost = $_POST['db_host'];
        $dbPort = $_POST['db_port'];
        $dbName = $_POST['db_name'];
        $dbUser = $_POST['db_user'];
        $dbPass = $_POST['db_pass'];
        
        // Test connection
        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            
            // Create tables
            createTables($pdo);
            
            // Save credentials
            saveEnvFile($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
            
            $_SESSION['db_configured'] = true;
            header('Location: ?step=4');
            exit;
            
        } catch (PDOException $e) {
            $error = "Database connection failed: " . $e->getMessage();
        }
    }
    ?>
    <h2>Database Configuration</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>Database Host</label>
            <input type="text" name="db_host" value="localhost" required>
        </div>
        
        <div class="form-group">
            <label>Database Port</label>
            <input type="number" name="db_port" value="3306" required>
        </div>
        
        <div class="form-group">
            <label>Database Name</label>
            <input type="text" name="db_name" required>
        </div>
        
        <div class="form-group">
            <label>Database Username</label>
            <input type="text" name="db_user" required>
        </div>
        
        <div class="form-group">
            <label>Database Password</label>
            <input type="password" name="db_pass">
        </div>
        
        <button type="submit" class="btn">Test & Continue →</button>
    </form>
    <?php
}

function adminSetup() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Save admin credentials to session
        $_SESSION['admin_email'] = $_POST['admin_email'];
        $_SESSION['admin_password'] = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
        $_SESSION['admin_name'] = $_POST['admin_name'];
        
        header('Location: ?step=5');
        exit;
    }
    ?>
    <h2>Admin Account Setup</h2>
    
    <form method="POST">
        <div class="form-group">
            <label>Admin Name</label>
            <input type="text" name="admin_name" required>
        </div>
        
        <div class="form-group">
            <label>Admin Email</label>
            <input type="email" name="admin_email" required>
        </div>
        
        <div class="form-group">
            <label>Admin Password</label>
            <input type="password" name="admin_password" minlength="6" required>
        </div>
        
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="admin_password_confirm" minlength="6" required>
        </div>
        
        <button type="submit" class="btn">Create Admin Account →</button>
    </form>
    <?php
}

function finalizeInstallation() {
    // Create admin user
    if (isset($_SESSION['admin_email'])) {
        createAdminUser($_SESSION['admin_email'], $_SESSION['admin_password'], $_SESSION['admin_name']);
    }
    
    // Mark as installed
    file_put_contents(INSTALL_DIR . '/.installed', date('Y-m-d H:i:s'));
    
    // Clear session
    session_destroy();
    ?>
    <div class="alert alert-success">
        <h3 style="margin-bottom: 10px;">🎉 Installation Complete!</h3>
        <p>CoinHit has been successfully installed on your server.</p>
    </div>
    
    <div class="progress-bar">
        <div class="progress-fill" style="width: 100%;">100%</div>
    </div>
    
    <h3>Next Steps:</h3>
    <ol style="margin: 20px 0; padding-left: 20px; line-height: 2;">
        <li>Delete the <code>install.php</code> file for security</li>
        <li>Login to admin panel with your credentials</li>
        <li>Configure your site settings</li>
        <li>Start adding matches and predictions</li>
    </ol>
    
    <div style="margin-top: 40px;">
        <a href="/" class="btn">Go to Homepage →</a>
        <a href="/admin" class="btn" style="margin-left: 10px;">Go to Admin Panel →</a>
    </div>
    
    <div class="alert alert-warning" style="margin-top: 30px;">
        <strong>Important:</strong> Please delete the <code>install.php</code> file from your server for security reasons.
    </div>
    <?php
}

function createTables($pdo) {
    $sql = file_get_contents(INSTALL_DIR . '/database.sql');
    if ($sql) {
        $pdo->exec($sql);
    }
}

function saveEnvFile($host, $port, $name, $user, $pass) {
    $jwtSecret = bin2hex(random_bytes(32));
    
    $content = "APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_HOST=$host
DB_PORT=$port
DB_NAME=$name
DB_USER=$user
DB_PASS=$pass

JWT_SECRET=$jwtSecret
JWT_EXPIRATION=86400

CACHE_DRIVER=file
CACHE_LIFETIME=3600
";
    
    file_put_contents(INSTALL_DIR . '/.env', $content);
}

function createAdminUser($email, $password, $name) {
    require_once INSTALL_DIR . '/vendor/autoload.php';
    
    $envFile = INSTALL_DIR . '/.env';
    if (file_exists($envFile)) {
        $dotenv = Dotenv\Dotenv::createImmutable(INSTALL_DIR);
        $dotenv->load();
    }
    
    try {
        $pdo = new PDO(
            "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASS']
        );
        
        $stmt = $pdo->prepare("INSERT INTO users (email, username, password, full_name, role, membership_tier, is_active, is_verified, created_at) VALUES (?, ?, ?, ?, 'admin', 'premium', 1, 1, NOW())");
        $stmt->execute([$email, 'admin', $password, $name]);
    } catch (PDOException $e) {
        // Ignore if already exists
    }
}
?>
