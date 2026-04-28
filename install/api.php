<?php
require_once __DIR__ . '/../app/Core/Polyfill.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

$ROOT = realpath(__DIR__ . '/..');
if (!$ROOT) $ROOT = dirname(__DIR__);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$raw = file_get_contents('php://input');
if ($raw && isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $_POST = array_merge($_POST, $json);
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (in_array($action, ['test-db', 'execute', 'upgrade'], true) && isAlreadyInstalled($ROOT)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Application is already installed']);
    exit;
}

switch ($action) {
    case 'test-db':
        testDb();
        break;
    case 'execute':
        executeInstall($ROOT);
        break;
    case 'upgrade':
        executeUpgrade($ROOT);
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
exit;

function sanitizeDbName($name) {
    $name = trim($name);
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) { return ''; }
    return $name;
}

function sanitizeDbHost($host) {
    $host = trim($host);
    if (!preg_match('/^[a-zA-Z0-9.\-]+$/', $host)) { return ''; }
    return $host;
}

function testDb() {
    $host = sanitizeDbHost($_POST['host'] ?? '');
    $port = (int)($_POST['port'] ?? 3306);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = sanitizeDbName($_POST['name'] ?? '');

    if (!$host || !$username) {
        echo json_encode(['success' => false, 'message' => '请填写数据库主机和用户名']);
        return;
    }
    if ($port < 1 || $port > 65535) {
        echo json_encode(['success' => false, 'message' => '端口号无效']);
        return;
    }
    try {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 5,
        ]);
        if ($name) {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        echo json_encode(['success' => true, 'message' => '数据库连接成功']);
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'message' => '连接失败，请检查配置']);
    }
}

function executeInstall($ROOT) {
    if (isAlreadyInstalled($ROOT)) {
        echo json_encode(['success' => false, 'message' => 'Application is already installed']);
        return;
    }

    $dbHost = sanitizeDbHost($_POST['db_host'] ?? '');
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = sanitizeDbName($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $adminUser = trim($_POST['admin_username'] ?? '');
    $adminPass = $_POST['admin_password'] ?? '';
    $siteName = trim($_POST['site_name'] ?? 'AiChat Pro');
    $siteDesc = trim($_POST['site_description'] ?? '');
    $siteUrl = trim($_POST['site_url'] ?? 'http://localhost');

    if (!$dbHost || !$dbName || !$dbUser || !$adminUser || !$adminPass || !$siteName) {
        echo json_encode(['success' => false, 'message' => '请填写所有必填项']);
        return;
    }
    if ($dbPort < 1 || $dbPort > 65535) {
        echo json_encode(['success' => false, 'message' => '端口号无效']);
        return;
    }
    if (strlen($adminUser) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $adminUser)) {
        echo json_encode(['success' => false, 'message' => '管理员用户名至少3个字母数字字符']);
        return;
    }
    if (strlen($adminPass) < 8) {
        echo json_encode(['success' => false, 'message' => '管理员密码至少8位']);
        return;
    }

    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
        $pdo = new \PDO($dsn, $dbUser, $dbPass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");

        $schemaFile = $ROOT . '/sql/schema.sql';
        if (!file_exists($schemaFile)) { throw new \Exception('Schema not found'); }
        $schema = stripSqlComments(file_get_contents($schemaFile));
        $statements = array_filter(array_map('trim', explode(';', $schema)), function($s) { return strlen($s) > 0; });
        foreach ($statements as $i => $sql) {
            try {
                $pdo->exec($sql . ';');
            } catch (\PDOException $e) {
                throw new \Exception('SQL error on statement #' . ($i + 1) . ': ' . substr($e->getMessage(), 0, 200));
            }
        }

        $hash = password_hash($adminPass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)");
        $stmt->execute([$adminUser, $hash]);

        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute(['site_name', $siteName]);
        if ($siteDesc) { $stmt->execute(['site_description', $siteDesc]); }

        $pdo->exec("DELETE FROM install_lock");
        $stmt = $pdo->prepare("INSERT INTO install_lock (installed, installed_at) VALUES (1, NOW())");
        $stmt->execute();

        $dbConfig = ['host'=>$dbHost,'port'=>$dbPort,'name'=>$dbName,'username'=>$dbUser,'password'=>$dbPass,'charset'=>'utf8mb4'];
        file_put_contents($ROOT . '/config/database.php', "<?php\n\nreturn " . var_export($dbConfig, true) . ";\n", LOCK_EX);

        $jwtSecret = bin2hex(random_bytes(32));
        $envContent = "APP_NAME=" . envValue($siteName) . "\nAPP_URL=" . envValue($siteUrl) . "\nAPP_DEBUG=false\nAPP_ENV=production\nJWT_SECRET={$jwtSecret}\nDB_HOST=" . envValue($dbHost) . "\nDB_PORT={$dbPort}\nDB_NAME=" . envValue($dbName) . "\nDB_USER=" . envValue($dbUser) . "\nDB_PASS=" . envValue($dbPass) . "\n";
        file_put_contents($ROOT . '/.env', $envContent, LOCK_EX);

        $uploadsDir = $ROOT . '/uploads';
        if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0755, true);

        echo json_encode(['success' => true, 'message' => 'Installation completed successfully']);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Installation failed: ' . $e->getMessage()]);
    }
}

function stripSqlComments($schema) {
    $lines = preg_split('/\r\n|\r|\n/', $schema);
    $sql = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
            continue;
        }
        $sql .= $line . "\n";
    }
    return $sql;
}

function envValue($value) {
    return str_replace(["\r", "\n"], ' ', trim((string)$value));
}

function isAlreadyInstalled($ROOT) {
    $dbConfigFile = $ROOT . '/config/database.php';
    if (!file_exists($dbConfigFile)) {
        return false;
    }

    try {
        $dbConfig = require $dbConfigFile;
        if (empty($dbConfig['name'])) {
            return false;
        }
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4";
        $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $row = $pdo->query("SELECT COUNT(*) as c FROM install_lock WHERE installed=1")->fetch(\PDO::FETCH_ASSOC);
        return $row && (int)$row['c'] > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

function executeUpgrade($ROOT) {
    $dbConfigFile = $ROOT . '/config/database.php';
    if (!file_exists($dbConfigFile)) {
        echo json_encode(['success' => false, 'message' => 'Database config not found']);
        return;
    }

    try {
        $dbConfig = require $dbConfigFile;
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4";
        $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $upgrades = [];

        $cols = $pdo->query("SHOW COLUMNS FROM model_configs")->fetchAll(\PDO::FETCH_COLUMN);
        $newCols = [
            'api_key' => "ALTER TABLE `model_configs` ADD COLUMN `api_key` VARCHAR(500) DEFAULT NULL",
            'api_base_url' => "ALTER TABLE `model_configs` ADD COLUMN `api_base_url` VARCHAR(500) DEFAULT NULL",
            'max_context_tokens' => "ALTER TABLE `model_configs` ADD COLUMN `max_context_tokens` INT UNSIGNED DEFAULT 4096",
            'max_output_tokens' => "ALTER TABLE `model_configs` ADD COLUMN `max_output_tokens` INT UNSIGNED DEFAULT 2048",
            'default_temperature' => "ALTER TABLE `model_configs` ADD COLUMN `default_temperature` DECIMAL(3,2) DEFAULT 0.70",
            'description' => "ALTER TABLE `model_configs` ADD COLUMN `description` TEXT DEFAULT NULL",
            'capabilities' => "ALTER TABLE `model_configs` ADD COLUMN `capabilities` JSON DEFAULT NULL",
            'daily_limit' => "ALTER TABLE `model_configs` ADD COLUMN `daily_limit` INT UNSIGNED DEFAULT 0",
        ];
        foreach ($newCols as $col => $sql) {
            if (!in_array($col, $cols)) {
                $pdo->exec($sql);
                $upgrades[] = "Added model_configs.{$col}";
            }
        }

        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('plan_models', $tables)) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `plan_models` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `plan_id` TINYINT UNSIGNED NOT NULL,
                `model_id` INT UNSIGNED NOT NULL,
                UNIQUE KEY `uk_plan_model` (`plan_id`, `model_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $upgrades[] = "Created plan_models table";
        }

        $existingSettings = $pdo->query("SELECT setting_key FROM site_settings")->fetchAll(\PDO::FETCH_COLUMN);
        $newSettings = [
            'site_announcement' => '',
            'maintenance_mode' => '0',
            'contact_email' => '',
            'icp_number' => '',
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($newSettings as $key => $value) {
            if (!in_array($key, $existingSettings)) {
                $stmt->execute([$key, $value]);
                $upgrades[] = "Added setting {$key}";
            }
        }

        echo json_encode(['success' => true, 'message' => 'Upgrade completed', 'upgrades' => $upgrades]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Upgrade failed: ' . $e->getMessage()]);
    }
}
