<?php
/**
 * Bharat SEO CRM - Installation Wizard
 * 
 * Run this ONCE to set up the database and admin user.
 * Delete this file after installation for security.
 */

$message = '';
$messageType = '';
$installed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? 'bharat_seo_crm');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = $_POST['db_pass'] ?? '';
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? 'admin123';
    
    try {
        // Test connection
        $dsn = "mysql:host={$dbHost};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        
        // Import SQL schema
        $sqlFile = __DIR__ . '/database.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception('database.sql file not found.');
        }
        
        $sql = file_get_contents($sqlFile);
        // Remove USE and CREATE DATABASE statements (we handle those above)
        $sql = preg_replace('/^(USE|CREATE DATABASE).*?;\s*$/mi', '', $sql);
        
        // Execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt) && $stmt !== '') {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    // Skip duplicate key errors (already exists)
                    if ($e->getCode() != '23000') {
                        // Ignore table already exists errors
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            error_log("Install SQL error: " . $e->getMessage());
                        }
                    }
                }
            }
        }
        
        // Create/update admin user with provided credentials
        $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = ?");
        $stmt->execute([$adminUser, $passwordHash, $passwordHash]);
        
        // Update config.php
        $configFile = __DIR__ . '/config.php';
        $configContent = file_get_contents($configFile);
        $configContent = preg_replace("/define\('DB_HOST',\s*'[^']*'\)/", "define('DB_HOST', '{$dbHost}')", $configContent);
        $configContent = preg_replace("/define\('DB_NAME',\s*'[^']*'\)/", "define('DB_NAME', '{$dbName}')", $configContent);
        $configContent = preg_replace("/define\('DB_USER',\s*'[^']*'\)/", "define('DB_USER', '{$dbUser}')", $configContent);
        $configContent = preg_replace("/define\('DB_PASS',\s*'[^']*'\)/", "define('DB_PASS', '{$dbPass}')", $configContent);
        file_put_contents($configFile, $configContent);
        
        $installed = true;
        $message = "Installation successful! Database created and admin user set up.";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = "Installation error: " . $e->getMessage();
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - Bharat SEO CRM</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .install-box { background: #fff; border-radius: 12px; padding: 40px; max-width: 520px; width: 100%; box-shadow: 0 25px 50px rgba(0,0,0,0.2); }
        .brand { text-align: center; margin-bottom: 32px; }
        .brand h1 { font-size: 24px; color: #0f172a; margin-bottom: 4px; }
        .brand p { color: #64748b; font-size: 14px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .btn { display: block; width: 100%; padding: 14px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .success-box { text-align: center; padding: 20px 0; }
        .success-box h2 { color: #065f46; margin-bottom: 12px; }
        .success-box a { display: inline-block; padding: 12px 24px; background: #2563eb; color: #fff; border-radius: 8px; font-weight: 600; text-decoration: none; margin-top: 16px; }
        .warning { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #92400e; margin-top: 16px; }
        hr { border: none; border-top: 1px solid #e2e8f0; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="install-box">
        <div class="brand">
            <h1>&#9733; Bharat SEO CRM</h1>
            <p>Lead Finder & Auto Audit Tool - Installation</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($installed): ?>
            <div class="success-box">
                <h2>&#10004; Installation Complete!</h2>
                <p>Your CRM is ready to use.</p>
                <p style="margin-top:12px;font-size:13px;color:#64748b;">
                    <strong>Username:</strong> <?php echo htmlspecialchars($adminUser); ?><br>
                    <strong>Password:</strong> <?php echo htmlspecialchars($adminPass); ?>
                </p>
                <a href="admin/login.php">Go to Admin Panel</a>
            </div>
            <div class="warning">
                <strong>Security:</strong> Delete this install.php file after installation is complete.
            </div>
        <?php else: ?>
            <form method="POST">
                <h3 style="font-size:15px;color:#334155;margin-bottom:16px;">Database Configuration</h3>
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" class="form-control" value="bharat_seo_crm" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Database Username</label>
                        <input type="text" name="db_user" class="form-control" value="root" required>
                    </div>
                    <div class="form-group">
                        <label>Database Password</label>
                        <input type="password" name="db_pass" class="form-control" value="">
                    </div>
                </div>
                
                <hr>
                
                <h3 style="font-size:15px;color:#334155;margin-bottom:16px;">Admin Account</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Admin Username</label>
                        <input type="text" name="admin_user" class="form-control" value="admin" required>
                    </div>
                    <div class="form-group">
                        <label>Admin Password</label>
                        <input type="password" name="admin_pass" class="form-control" value="admin123" required>
                    </div>
                </div>
                
                <button type="submit" class="btn">Install Now</button>
            </form>
            
            <div class="warning">
                <strong>Requirements:</strong> PHP 8.0+, MySQL 5.7+, cURL extension enabled, PDO MySQL extension.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
