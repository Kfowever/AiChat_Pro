<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

class InstallController
{
    public function check(Request $request): void
    {
        $checks = [
            'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'fileinfo' => extension_loaded('fileinfo'),
            'curl' => extension_loaded('curl'),
            'uploads_writable' => is_writable(dirname(__DIR__, 2) . '/uploads'),
            'config_writable' => is_writable(dirname(__DIR__, 2) . '/config'),
            'storage_writable' => is_writable(dirname(__DIR__, 2) . '/storage'),
        ];

        $allPassed = !in_array(false, $checks, true);

        Response::success([
            'checks' => $checks,
            'all_passed' => $allPassed,
            'php_version' => PHP_VERSION,
        ]);
    }

    public function testDb(Request $request): void
    {
        if ($this->isInstalled()) {
            Response::forbidden('Application is already installed');
            return;
        }

        $data = $request->body();
        $host = $this->sanitizeHost($data['host'] ?? '');
        $port = (int)($data['port'] ?? 3306);
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $name = $this->sanitizeDbName($data['name'] ?? '');

        if (!$host || !$username) {
            Response::error('请填写数据库主机和用户名');
            return;
        }

        if ($port < 1 || $port > 65535) {
            Response::error('端口号无效');
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
            Response::success(null, 'Database connection successful');
        } catch (\PDOException $e) {
            Response::error('Connection failed');
        }
    }

    public function execute(Request $request): void
    {
        if ($this->isInstalled()) {
            Response::forbidden('Application is already installed');
            return;
        }

        $data = $request->body();

        $dbHost = $this->sanitizeHost($data['db_host'] ?? '');
        $dbPort = (int)($data['db_port'] ?? 3306);
        $dbName = $this->sanitizeDbName($data['db_name'] ?? '');
        $dbUser = trim($data['db_user'] ?? '');
        $dbPass = $data['db_pass'] ?? '';
        $adminUser = trim($data['admin_username'] ?? '');
        $adminPass = $data['admin_password'] ?? '';
        $siteName = trim($data['site_name'] ?? 'AiChat Pro');

        if (!$dbHost || !$dbName || !$dbUser || !$adminUser || !$adminPass || !$siteName) {
            Response::error('请填写所有必填项');
            return;
        }

        if ($dbPort < 1 || $dbPort > 65535) {
            Response::error('端口号无效');
            return;
        }

        if (strlen($adminUser) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $adminUser)) {
            Response::error('管理员用户名至少3个字母数字字符');
            return;
        }

        if (strlen($adminPass) < 8) {
            Response::error('管理员密码至少8位');
            return;
        }

        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new \PDO($dsn, $dbUser, $dbPass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            $this->runSchema($pdo, dirname(__DIR__, 2) . '/sql/schema.sql');

            $hash = password_hash($adminPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
            $stmt->execute([$adminUser, $hash]);

            $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$siteName, 'site_name']);
            if (!empty($data['site_description'])) {
                $stmt->execute([trim($data['site_description']), 'site_description']);
            }

            $pdo->exec("DELETE FROM install_lock");
            $pdo->exec("INSERT INTO install_lock (installed, installed_at) VALUES (1, NOW())");

            $dbConfig = [
                'host' => $dbHost,
                'port' => $dbPort,
                'name' => $dbName,
                'username' => $dbUser,
                'password' => $dbPass,
                'charset' => 'utf8mb4',
            ];
            file_put_contents(dirname(__DIR__, 2) . '/config/database.php', "<?php\n\nreturn " . var_export($dbConfig, true) . ";\n", LOCK_EX);

            $jwtSecret = bin2hex(random_bytes(32));
            $envContent = "APP_NAME=" . $this->envValue($siteName) . "\n"
                . "APP_URL=" . $this->envValue(trim($data['site_url'] ?? 'http://localhost')) . "\n"
                . "APP_DEBUG=false\n"
                . "APP_ENV=production\n"
                . "JWT_SECRET={$jwtSecret}\n"
                . "DB_HOST=" . $this->envValue($dbHost) . "\n"
                . "DB_PORT={$dbPort}\n"
                . "DB_NAME=" . $this->envValue($dbName) . "\n"
                . "DB_USER=" . $this->envValue($dbUser) . "\n"
                . "DB_PASS=" . $this->envValue($dbPass) . "\n";
            file_put_contents(dirname(__DIR__, 2) . '/.env', $envContent, LOCK_EX);

            Response::success(null, 'Installation completed successfully');
        } catch (\Exception $e) {
            Response::error('Installation failed. Please check your configuration.', 500);
        }
    }

    private function sanitizeHost($host): string
    {
        $host = trim($host);
        if (!preg_match('/^[a-zA-Z0-9.\-]+$/', $host)) {
            return '';
        }
        return $host;
    }

    private function sanitizeDbName($name): string
    {
        $name = trim($name);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            return '';
        }
        return $name;
    }

    private function runSchema(\PDO $pdo, string $schemaFile): void
    {
        if (!file_exists($schemaFile)) {
            throw new \RuntimeException('Schema not found');
        }

        $schema = file_get_contents($schemaFile);
        $lines = preg_split('/\r\n|\r|\n/', $schema);
        $sql = '';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
                continue;
            }
            $sql .= $line . "\n";
        }

        $statements = array_filter(array_map('trim', explode(';', $sql)), function ($statement) {
            return $statement !== '';
        });

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    private function envValue(string $value): string
    {
        return str_replace(["\r", "\n"], ' ', trim($value));
    }

    private function isInstalled(): bool
    {
        try {
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            if (!$pdo) {
                return false;
            }
            $result = $db->queryOne("SELECT COUNT(*) as c FROM install_lock WHERE installed = 1");
            return $result && (int)$result['c'] > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
