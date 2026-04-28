<?php

namespace App\Middleware;

use App\Core\Config;
use App\Core\Database;
use App\Core\Response;

class InstallMiddleware
{
    public function handle(): bool
    {
        if ($this->isInstalled()) {
            return true;
        }

        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (str_starts_with($requestUri, '/install') || str_starts_with($requestUri, '/api/install')) {
            return true;
        }

        if (str_starts_with($requestUri, '/api/') && !str_starts_with($requestUri, '/api/install')) {
            Response::json(['success' => false, 'message' => 'Application not installed', 'redirect' => '/install'], 503);
            return false;
        }

        header('Location: /install');
        exit;
    }

    private function isInstalled(): bool
    {
        $lockFile = dirname(__DIR__, 2) . '/config/database.php';
        if (!file_exists($lockFile)) {
            return false;
        }

        $config = Config::getInstance();
        $dbName = $config->get('database.name');
        if (empty($dbName)) {
            return false;
        }

        try {
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            if ($pdo === null) {
                return false;
            }
            $result = $db->queryOne("SELECT COUNT(*) as count FROM install_lock WHERE installed = 1");
            return $result && $result['count'] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
