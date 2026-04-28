<?php

namespace App\Core;

class Config
{
    private static $instance = null;
    private $config = [];

    private function __construct()
    {
        $this->loadEnv();
        $this->loadConfigFiles();
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnv(): void
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    $parts = explode('=', $line, 2);
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    if (!empty($key) && !isset($_ENV[$key])) {
                        putenv("$key=$value");
                        $_ENV[$key] = $value;
                    }
                }
            }
        }
    }

    private function loadConfigFiles(): void
    {
        $configDir = dirname(__DIR__, 2) . '/config';
        $files = glob($configDir . '/*.php');
        foreach ($files as $file) {
            $name = basename($file, '.php');
            $this->config[$name] = require $file;
        }
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                $envKey = strtoupper(implode('_', $keys));
                $envValue = getenv($envKey);
                if ($envValue !== false) {
                    return $envValue;
                }
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }

    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    public function all(): array
    {
        return $this->config;
    }
}
