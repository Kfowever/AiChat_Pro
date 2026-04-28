<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

$ROOT = realpath(__DIR__);
if (!$ROOT) { $ROOT = __DIR__; }

require_once $ROOT . '/app/Core/Polyfill.php';

$coreFiles = [
    $ROOT . '/app/Core/Config.php',
    $ROOT . '/app/Core/Database.php',
    $ROOT . '/app/Core/JWT.php',
    $ROOT . '/app/Core/Request.php',
    $ROOT . '/app/Core/Response.php',
    $ROOT . '/app/Core/Validator.php',
    $ROOT . '/app/Core/RateLimiter.php',
    $ROOT . '/app/Core/Router.php',
];
foreach ($coreFiles as $f) {
    if (file_exists($f)) {
        require_once $f;
    }
}

spl_autoload_register(function ($class) use ($ROOT) {
    $prefix = 'App\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) return;
    $relative = str_replace('\\', '/', substr($class, $len));
    $file = $ROOT . '/app/' . $relative . '.php';
    if (file_exists($file)) require_once $file;
});

try {
    \App\Core\Config::getInstance();
} catch (\Throwable $e) {}

$uri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
if ($uri === false || $uri === null) { $uri = '/'; }
$uri = '/' . trim($uri, '/');
if ($uri === '//') $uri = '/';

// Compatibility: some Nginx/PHP setups do not rewrite /api/* to index.php.
// Support /index.php/api/* fallback paths used by frontend auto-detection.
if (str_starts_with($uri, '/index.php/')) {
    $uri = '/' . ltrim(substr($uri, strlen('/index.php')), '/');
    if ($uri === '//') {
        $uri = '/';
    }
} elseif ($uri === '/index.php') {
    $uri = '/';
}

$forbidden = ['/.env', '/.env.', '/.git', '/.trae', '/composer.', '/package.', '/sql/', '/config/', '/storage/', '/docker/', '/uploads/files/', '/install/api'];
foreach ($forbidden as $path) {
    if (str_starts_with($uri, $path)) {
        http_response_code(403);
        exit;
    }
}

$ext = '';
if (($pos = strrpos($uri, '.')) !== false) {
    $ext = strtolower(substr($uri, $pos + 1));
}
$staticExts = ['css','js','png','jpg','jpeg','gif','webp','svg','ico','woff','woff2','ttf','eot','map'];

if ($ext && in_array($ext, $staticExts)) {
    $relPath = ltrim(str_replace('/', DIRECTORY_SEPARATOR, $uri), DIRECTORY_SEPARATOR);
    $filePath = $ROOT . DIRECTORY_SEPARATOR . $relPath;
    $realPath = realpath($filePath);
    if ($realPath && str_starts_with($realPath, realpath($ROOT)) && is_file($realPath)) {
        $mimeMap = [
            'css'=>'text/css','js'=>'application/javascript','png'=>'image/png',
            'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif',
            'webp'=>'image/webp','svg'=>'image/svg+xml','ico'=>'image/x-icon',
            'woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf',
            'eot'=>'application/vnd.ms-fontobject','map'=>'application/json',
        ];
        header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400');
        readfile($realPath);
    } else {
        http_response_code(404);
    }
    exit;
}

if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    $allowedOrigins = \App\Core\Config::getInstance()->get('app.cors.allowed_origins', []);
    if (in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
    }
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com data:; img-src 'self' data: blob:; connect-src 'self'; base-uri 'self'; frame-ancestors 'self'");
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

function isInstalled() {
    $dbConfigFile = __DIR__ . '/config/database.php';
    if (!file_exists($dbConfigFile)) return false;
    try {
        $db = \App\Core\Database::getInstance();
        $pdo = $db->getPdo();
        if ($pdo) {
            $r = $db->queryOne("SELECT COUNT(*) as c FROM install_lock WHERE installed=1");
            return $r && isset($r['c']) && (int)$r['c'] > 0;
        }
    } catch (\Throwable $e) {}
    return false;
}

$installed = isInstalled();

if ($installed && str_starts_with($uri, '/install')) {
    header('Location: /');
    exit;
}

if (!$installed && !str_starts_with($uri, '/install') && !str_starts_with($uri, '/api/install')) {
    header('Location: /install');
    exit;
}

if (str_starts_with($uri, '/api/')) {
    require_once $ROOT . '/routes/api.php';
    \App\Core\Router::dispatch();
    exit;
}

$pageFile = null;
if (str_starts_with($uri, '/install')) {
    $pageFile = $ROOT . '/install/index.php';
} elseif (str_starts_with($uri, '/admin')) {
    $pageFile = $ROOT . '/admin/index.php';
} else {
    $pageFile = $ROOT . '/index_page.php';
}

if ($pageFile && file_exists($pageFile)) {
    require_once $pageFile;
    exit;
}

http_response_code(404);
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>404</title></head><body style="display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;background:#0f172a;color:#e2e8f0"><div style="text-align:center;padding:2rem"><h1 style="font-size:5rem;margin:0;color:#3b82f6">404</h1><p style="font-size:1.2rem;margin-top:1rem">Page not found</p><a href="/" style="color:#60a5fa;text-decoration:none;margin-top:1rem;display:inline-block">← Back to Home</a></div></body></html>';
