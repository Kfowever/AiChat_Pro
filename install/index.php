<?php
require_once __DIR__ . '/../app/Core/Polyfill.php';

$ROOT = realpath(__DIR__ . '/..');
if (!$ROOT) $ROOT = dirname(__DIR__);

$uploadsDir = $ROOT . '/uploads';
$configDir = $ROOT . '/config';
$storageDir = $ROOT . '/storage';
if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0755, true);
if (!is_dir($configDir)) @mkdir($configDir, 0755, true);
if (!is_dir($storageDir)) @mkdir($storageDir, 0755, true);

$requiredChecks = [
    'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'json' => extension_loaded('json'),
];

$recommendedChecks = [
    'mbstring' => extension_loaded('mbstring'),
    'openssl' => extension_loaded('openssl'),
    'curl' => extension_loaded('curl'),
    'fileinfo' => extension_loaded('fileinfo'),
    'uploads_writable' => is_writable($uploadsDir),
    'config_writable' => is_writable($configDir),
    'storage_writable' => is_writable($storageDir),
];

$allRequiredPassed = !in_array(false, $requiredChecks, true);
$canInstall = $allRequiredPassed && is_writable($uploadsDir) && is_writable($configDir);

$requiredLabels = [
    'php_version' => 'PHP 版本 (≥ 7.4) - 当前: ' . PHP_VERSION,
    'pdo_mysql' => 'PDO MySQL 扩展（必需）',
    'json' => 'JSON 扩展（必需）',
];

$recommendedLabels = [
    'mbstring' => 'MBString 扩展（推荐，缺失则中文截取不精确）',
    'openssl' => 'OpenSSL 扩展（推荐，HTTPS 通信需要）',
    'curl' => 'cURL 扩展（推荐，AI API 通信需要）',
    'fileinfo' => 'FileInfo 扩展（推荐，文件类型检测）',
    'uploads_writable' => 'uploads 目录写权限',
    'config_writable' => 'config 目录写权限',
    'storage_writable' => 'storage 目录写权限（限流缓存）',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AiChat Pro - 安装向导</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
.step-circle { width:32px;height:32px;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:bold;flex-shrink:0;transition:background-color .55s cubic-bezier(.34,1.56,.64,1),box-shadow .55s cubic-bezier(.34,1.56,.64,1),transform .35s ease; }
.sc-active { background-color:#2563eb;box-shadow:0 0 16px rgba(37,99,235,.45),0 0 4px rgba(37,99,235,.3);transform:scale(1.12); }
.sc-inactive { background-color:#374151;box-shadow:none;transform:scale(1); }
.sc-done { background-color:#10b981;box-shadow:0 0 12px rgba(16,185,129,.35);transform:scale(1.08); }

.sl-label { font-size:13px;transition:color .5s ease,opacity .4s ease; }
.sl-inactive { color:#9ca3af;opacity:.65; }
.sl-active { color:#e5e7eb;opacity:1; }
.sl-done { color:#10b981; }

.step-line { width:24px;height:2px;border-radius:1px;flex-shrink:0;transition:background-color .6s ease,width .5s ease; }
.sl-done-line { background-color:#2563eb;width:28px; }
.sl-pending-line { background-color:#4b5563; }

@keyframes cb{0%{transform:scale(0);opacity:0}50%{transform:scale(1.25)}100%{transform:scale(1);opacity:1}}
.ci{animation:cb .35s ease-out both}
.ci:nth-child(1){animation-delay:.05s}.ci:nth-child(2){animation-delay:.12s}.ci:nth-child(3){animation-delay:.19s}.ci:nth-child(4){animation-delay:.26s}
.ci:nth-child(5){animation-delay:.33s}.ci:nth-child(6){animation-delay:.40s}.ci:nth-child(7){animation-delay:.47s}.ci:nth-child(8){animation-delay:.54s}.ci:nth-child(9){animation-delay:.61s}

.panel { display:none; }
.panel-current { display:block; animation:fadeInUp .4s ease forwards; }
@keyframes fadeInUp { from{opacity:0;transform:translateX(30px)} to{opacity:1;transform:translateX(0)} }
</style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-2xl">
    <div class="text-center mb-8">
        <div style="font-size:3.5rem;margin-bottom:.75rem;">🚀</div>
        <h1 class="text-3xl font-bold mb-2">AiChat Pro 安装向导</h1>
        <p class="text-gray-400">几步完成安装，开始使用 AI 聊天平台</p>
    </div>

    <div class="flex items-center justify-center mb-8 gap-1 flex-wrap" id="stepIndicators">
        <div class="flex items-center gap-2"><div class="step-circle sc-active" id="sc1">1</div><span class="sl-label sl-active hidden sm:inline" id="sl1">环境检查</span></div>
        <div class="step-line sl-pending-line" id="slin1"></div>
        <div class="flex items-center gap-2"><div class="step-circle sc-inactive" id="sc2">2</div><span class="sl-label sl-inactive hidden sm:inline" id="sl2">数据库配置</span></div>
        <div class="step-line sl-pending-line" id="slin2"></div>
        <div class="flex items-center gap-2"><div class="step-circle sc-inactive" id="sc3">3</div><span class="sl-label sl-inactive hidden sm:inline" id="sl3">管理员设置</span></div>
        <div class="step-line sl-pending-line" id="slin3"></div>
        <div class="flex items-center gap-2"><div class="step-circle sc-inactive" id="sc4">4</div><span class="sl-label sl-inactive hidden sm:inline" id="sl4">完成安装</span></div>
    </div>

    <div class="bg-gray-800 rounded-2xl p-6" id="mainBox">

    <!-- Panel 1 -->
    <div id="panel1" class="panel panel-current">
        <h2 class="text-xl font-bold mb-4"><i class="fas fa-clipboard-check text-blue-400 mr-2"></i>环境检查</h2>
        <h3 class="text-sm font-semibold text-red-400 mb-2"><i class="fas fa-exclamation-circle mr-1"></i>必需项</h3>
        <div class="space-y-2 mb-4">
            <?php foreach ($requiredChecks as $key => $passed):
                $icon = $passed ? 'check-circle' : 'times-circle';
                $color = $passed ? 'text-green-400' : 'text-red-400';
                $text = $passed ? '通过' : '未通过';
            ?>
            <div class="ci flex items-center justify-between bg-gray-700/50 rounded-lg p-3">
                <span class="text-sm"><?php echo htmlspecialchars($requiredLabels[$key] ?? $key); ?></span>
                <span class="<?php echo $color; ?>"><i class="fas fa-<?php echo $icon; ?>"></i> <?php echo $text; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <h3 class="text-sm font-semibold text-yellow-400 mb-2"><i class="fas fa-exclamation-triangle mr-1"></i>推荐项（缺失不影响安装）</h3>
        <div class="space-y-2 mb-4">
            <?php foreach ($recommendedChecks as $key => $passed):
                $icon = $passed ? 'check-circle' : 'times-circle';
                $color = $passed ? 'text-green-400' : 'text-yellow-400';
                $text = $passed ? '通过' : '未通过';
            ?>
            <div class="ci flex items-center justify-between bg-gray-700/50 rounded-lg p-3">
                <span class="text-sm"><?php echo htmlspecialchars($recommendedLabels[$key] ?? $key); ?></span>
                <span class="<?php echo $color; ?>"><i class="fas fa-<?php echo $icon; ?>"></i> <?php echo $text; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="flex justify-end">
            <button onclick="goToStep(2)" id="btnNext1" class="bg-blue-600 hover:bg-blue-700 px-6 py-2.5 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed hover:shadow-lg hover:shadow-blue-500/25 active:scale-[0.97]" <?php echo $canInstall ? '' : 'disabled'; ?>>下一步 <i class="fas fa-arrow-right ml-1"></i></button>
        </div>
        <?php if (!$allRequiredPassed): ?>
        <div class="mt-3 bg-red-500/10 border border-red-500/30 rounded-lg p-3 text-sm text-red-400"><i class="fas fa-exclamation-triangle mr-2"></i>必需项未通过，请先解决后再继续安装</div>
        <?php endif; ?>
    </div>

    <!-- Panel 2 -->
    <div id="panel2" class="panel">
        <h2 class="text-xl font-bold mb-4"><i class="fas fa-database text-green-400 mr-2"></i>数据库配置</h2>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="text-sm text-gray-400 mb-1 block">数据库主机</label><input type="text" id="dbHost" value="localhost" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="text-sm text-gray-400 mb-1 block">端口</label><input type="number" id="dbPort" value="3306" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            </div>
            <div><label class="text-sm text-gray-400 mb-1 block">数据库名</label><input type="text" id="dbName" value="aichat_pro" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="text-sm text-gray-400 mb-1 block">用户名</label><input type="text" id="dbUser" value="root" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="text-sm text-gray-400 mb-1 block">密码</label><input type="password" id="dbPass" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div class="flex items-center gap-3">
                <button onclick="testDb()" id="btnTestDb" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg text-sm transition-all duration-200 hover:shadow-md active:scale-[0.96]"><i class="fas fa-plug mr-1"></i>测试连接</button>
                <span id="dbResult" class="text-sm"></span>
            </div>
        </div>
        <div class="flex justify-between mt-6">
            <button onclick="goToStep(1)" class="bg-gray-700 hover:bg-gray-600 px-6 py-2.5 rounded-lg transition-all duration-200 hover:shadow-md active:scale-[0.97]"><i class="fas fa-arrow-left mr-1"></i>上一步</button>
            <button onclick="goToStep(3)" id="btnNext2" class="bg-blue-600 hover:bg-blue-700 px-6 py-2.5 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed hover:shadow-lg hover:shadow-blue-500/25 active:scale-[0.97]" disabled>下一步 <i class="fas fa-arrow-right ml-1"></i></button>
        </div>
    </div>

    <!-- Panel 3 -->
    <div id="panel3" class="panel">
        <h2 class="text-xl font-bold mb-4"><i class="fas fa-user-shield text-purple-400 mr-2"></i>管理员与站点设置</h2>
        <div class="space-y-4">
            <div class="border-b border-gray-700 pb-4 mb-4">
                <h3 class="text-sm font-semibold text-gray-400 mb-3">管理员账户</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="text-sm text-gray-400 mb-1 block">管理员用户名</label><input type="text" id="adminUsername" value="admin" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="text-sm text-gray-400 mb-1 block">管理员密码</label><input type="password" id="adminPassword" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="至少8位"></div>
                </div>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-400 mb-3">站点信息</h3>
                <div class="space-y-3">
                    <div><label class="text-sm text-gray-400 mb-1 block">站点名称</label><input type="text" id="siteName" value="AiChat Pro" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                    <div><label class="text-sm text-gray-400 mb-1 block">站点描述</label><input type="text" id="siteDesc" value="现代化 AI 聊天平台" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                    <div><label class="text-sm text-gray-400 mb-1 block">站点 URL</label><input type="text" id="siteUrl" value="http://localhost" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                </div>
            </div>
        </div>
        <div class="flex justify-between mt-6">
            <button onclick="goToStep(2)" class="bg-gray-700 hover:bg-gray-600 px-6 py-2.5 rounded-lg transition-all duration-200 hover:shadow-md active:scale-[0.97]"><i class="fas fa-arrow-left mr-1"></i>上一步</button>
            <button onclick="doInstall()" class="bg-green-600 hover:bg-green-700 px-6 py-2.5 rounded-lg transition-all duration-200 hover:shadow-lg hover:shadow-green-500/25 active:scale-[0.97]"><i class="fas fa-rocket mr-1"></i>开始安装</button>
        </div>
    </div>

    <!-- Panel 4 -->
    <div id="panel4" class="panel">
        <div class="text-center">
            <div style="font-size:4rem;margin-bottom:.75rem;" id="doneIcon">✅</div>
            <h2 class="text-2xl font-bold mb-2">安装完成！</h2>
            <p class="text-gray-400 mb-6">AiChat Pro 已成功安装，现在可以开始使用了</p>
            <div class="space-y-3">
                <a href="/" class="block bg-blue-600 hover:bg-blue-700 px-6 py-3 rounded-xl transition-all duration-300 text-center hover:shadow-lg hover:shadow-blue-500/25 active:scale-[0.98]"><i class="fas fa-home mr-2"></i>进入首页</a>
                <a href="/admin" class="block bg-gray-700 hover:bg-gray-600 px-6 py-3 rounded-xl transition-all duration-300 text-center hover:shadow-md active:scale-[0.98]"><i class="fas fa-shield-alt mr-2"></i>进入管理后台</a>
            </div>
            <div class="mt-6 bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4 text-sm text-yellow-400"><i class="fas fa-exclamation-triangle mr-2"></i>安全提示：请妥善保管 .env 配置文件中的密钥信息。</div>
        </div>
    </div>

    <!-- Progress -->
    <div id="panelProgress" class="panel">
        <div class="text-center">
            <div class="mb-4"><i class="fas fa-spinner fa-spin text-4xl text-blue-400"></i></div>
            <h2 class="text-xl font-bold mb-2">正在安装...</h2>
            <p id="progStatus" class="text-gray-400">正在创建数据库表...</p>
            <div class="w-full bg-gray-700 rounded-full h-2 mt-4 overflow-hidden"><div id="progBar" class="bg-blue-600 rounded-full h-2 transition-all duration-500 ease-out" style="width:0%"></div></div>
        </div>
    </div>

    </div>
</div>

<script>
var cs = 1;
var installApiBase = localStorage.getItem('aichat_install_api_base') || '/api';

function apiCandidates() {
    var list = [installApiBase];
    if (list.indexOf('/api') === -1) list.push('/api');
    if (list.indexOf('/index.php/api') === -1) list.push('/index.php/api');
    return list;
}

async function postInstallApi(endpoint, payload) {
    var candidates = apiCandidates();
    for (var i = 0; i < candidates.length; i++) {
        var base = candidates[i];
        var resp = await fetch(base + endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
        var text = await resp.text();
        var ct = resp.headers.get('content-type') || '';
        if (resp.status === 404 && ct.indexOf('text/html') !== -1 && i < candidates.length - 1) {
            continue;
        }
        installApiBase = base;
        localStorage.setItem('aichat_install_api_base', installApiBase);
        try { return text ? JSON.parse(text) : {}; } catch(e) { return { success:false, message:text }; }
    }
    return { success:false, message:'网络错误' };
}

function goToStep(ns) {
    ns = parseInt(ns);
    if (isNaN(ns) || ns < 1 || ns > 4 || ns === cs) return;

    var cp = document.getElementById('panel' + cs);
    var np = document.getElementById('panel' + ns);

    cp.className = 'panel';
    np.className = 'panel panel-current';

    for (var i = 1; i <= 4; i++) {
        var c = document.getElementById('sc' + i);
        var l = document.getElementById('sl' + i);
        c.className = 'step-circle ' + (i < ns ? 'sc-done' : (i === ns ? 'sc-active' : 'sc-inactive'));
        l.className = 'sl-label ' + (i < ns ? 'sl-done' : (i === ns ? 'sl-active' : 'sl-inactive')) + ' hidden sm:inline';
    }
    for (var j = 1; j <= 3; j++) {
        document.getElementById('slin' + j).className = 'step-line ' + (j < ns ? 'sl-done-line' : 'sl-pending-line');
    }
    cs = ns;
}

async function testDb() {
    var d = { host:document.getElementById('dbHost').value, port:parseInt(document.getElementById('dbPort').value)||3306, username:document.getElementById('dbUser').value, password:document.getElementById('dbPass').value, name:document.getElementById('dbName').value };
    var r = document.getElementById('dbResult'), b = document.getElementById('btnTestDb');
    r.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 测试中...'; r.className = 'text-sm text-blue-400'; b.disabled = true;
    try {
        var res = await postInstallApi('/install/test-db', d); b.disabled = false;
        if (res && res.success) { r.innerHTML = '<i class="fas fa-check-circle"></i> 连接成功！'; r.className = 'text-sm text-green-400'; document.getElementById('btnNext2').disabled = false; }
        else { r.innerHTML = '<i class="fas fa-times-circle"></i> ' + (res ? res.message : '连接失败'); r.className = 'text-sm text-red-400'; }
    } catch(e) { b.disabled = false; r.innerHTML = '<i class="fas fa-times-circle"></i> 网络错误'; r.className = 'text-sm text-red-400'; }
}

async function doInstall() {
    var u = document.getElementById('adminUsername').value.trim();
    var p = document.getElementById('adminPassword').value;
    var n = document.getElementById('siteName').value.trim();
    if (!u || u.length < 3) { alert('管理员用户名至少3个字符'); return; }
    if (!p || p.length < 8) { alert('管理员密码至少8位'); return; }
    if (!n) { alert('请填写站点名称'); return; }

    document.getElementById('panel3').className = 'panel';
    document.getElementById('panelProgress').className = 'panel panel-current';

    var st = [{t:'正在生成配置文件...',p:20},{t:'正在创建数据库...',p:40},{t:'正在导入数据表...',p:60},{t:'正在创建管理员账户...',p:80},{t:'正在完成安装...',p:95}];
    for (var i = 0; i < st.length; i++) {
        document.getElementById('progStatus').textContent = st[i].t;
        document.getElementById('progBar').style.width = st[i].p + '%';
        await new Promise(function(r) { setTimeout(r, 500); });
    }

    var data = { db_host:document.getElementById('dbHost').value, db_port:parseInt(document.getElementById('dbPort').value)||3306, db_name:document.getElementById('dbName').value, db_user:document.getElementById('dbUser').value, db_pass:document.getElementById('dbPass').value, admin_username:u, admin_password:p, site_name:n, site_description:document.getElementById('siteDesc').value.trim(), site_url:document.getElementById('siteUrl').value.trim() };
    try {
        var result = await postInstallApi('/install/execute', data);
        document.getElementById('panelProgress').className = 'panel';
        if (result && result.success) {
            document.getElementById('progBar').style.width = '100%';
            var di = document.getElementById('doneIcon');
            di.textContent = ''; di.style.animation = 'none'; void di.offsetWidth;
            di.textContent = '✅'; di.style.animation = 'cb .5s ease-out both';
            goToStep(4);
        } else { alert('安装失败: ' + (result ? result.message : '未知错误')); goToStep(3); }
    } catch(e) { document.getElementById('panelProgress').className = 'panel'; alert('安装请求失败，请重试'); goToStep(3); }
}
</script>
</body>
</html>
