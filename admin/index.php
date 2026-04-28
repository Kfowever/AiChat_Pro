<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AiChat Pro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            color-scheme: dark;
            --bg: #080b12;
            --panel: #101622;
            --panel-soft: #151d2b;
            --line: #263244;
            --muted: #8b98aa;
            --text: #ecf3ff;
            --blue: #3b82f6;
            --green: #22c55e;
            --orange: #f97316;
            --red: #ef4444;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        button, input, select, textarea {
            font: inherit;
        }

        .login-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at 20% 10%, rgba(59, 130, 246, .16), transparent 28%),
                radial-gradient(circle at 80% 0%, rgba(34, 197, 94, .12), transparent 26%),
                var(--bg);
        }

        .login-card {
            width: min(420px, 100%);
            background: #101622;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 24px 80px rgba(0, 0, 0, .35);
        }

        .admin-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: auto 1fr;
        }

        .sidebar {
            width: 268px;
            height: 100vh;
            position: sticky;
            top: 0;
            background: #0d1320;
            border-right: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            transition: width .18s ease;
        }

        .sidebar.collapsed {
            width: 76px;
        }

        .brand {
            height: 64px;
            padding: 0 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--line);
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: #1d4ed8;
            color: white;
            flex: 0 0 auto;
        }

        .brand-text, .nav-label, .sidebar-foot-label {
            transition: opacity .12s ease;
            white-space: nowrap;
        }

        .sidebar.collapsed .brand-text,
        .sidebar.collapsed .nav-label,
        .sidebar.collapsed .sidebar-foot-label {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .nav {
            padding: 14px 12px;
            display: grid;
            gap: 4px;
        }

        .nav-btn {
            height: 42px;
            border: 0;
            border-radius: 8px;
            color: #a8b3c4;
            background: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 12px;
            cursor: pointer;
        }

        .nav-btn i {
            width: 20px;
            text-align: center;
            font-size: 15px;
        }

        .nav-btn:hover {
            background: #151d2b;
            color: white;
        }

        .nav-btn.active {
            color: white;
            background: #1d4ed8;
        }

        .sidebar-foot {
            margin-top: auto;
            padding: 12px;
            border-top: 1px solid var(--line);
        }

        .main {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            background: rgba(13, 19, 32, .82);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(14px);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .content {
            padding: 22px;
            max-width: 1500px;
            width: 100%;
            margin: 0 auto;
        }

        .section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .section-title {
            font-size: 24px;
            line-height: 1.2;
            font-weight: 760;
            letter-spacing: 0;
        }

        .section-subtitle {
            margin-top: 5px;
            color: var(--muted);
            font-size: 13px;
        }

        .grid-cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .metric-card, .panel, .table-wrap {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .metric-card {
            padding: 16px;
            min-height: 118px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .metric-icon {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: #172033;
        }

        .panel {
            padding: 16px;
        }

        .panel-title {
            font-size: 15px;
            font-weight: 720;
            margin-bottom: 14px;
        }

        .two-col {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr);
            gap: 14px;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .field, .select, .textarea {
            background: #121a28;
            border: 1px solid #314057;
            color: var(--text);
            border-radius: 8px;
            outline: none;
        }

        .field, .select {
            height: 38px;
            padding: 0 11px;
        }

        .textarea {
            width: 100%;
            min-height: 88px;
            resize: vertical;
            padding: 10px 11px;
        }

        .field:focus, .select:focus, .textarea:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .18);
        }

        .btn {
            height: 38px;
            border: 0;
            border-radius: 8px;
            padding: 0 13px;
            color: white;
            background: #1e293b;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 650;
            font-size: 13px;
        }

        .btn:hover {
            filter: brightness(1.08);
        }

        .btn-primary {
            background: var(--blue);
        }

        .btn-danger {
            background: rgba(239, 68, 68, .18);
            color: #fecaca;
        }

        .btn-ghost {
            background: transparent;
            color: #a8b3c4;
            border: 1px solid var(--line);
        }

        .icon-btn {
            width: 38px;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th, td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #151d2b;
            color: #b7c2d1;
            font-weight: 700;
            white-space: nowrap;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .table-wrap {
            overflow: auto;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge.active, .badge.completed, .badge.refunded {
            background: rgba(34, 197, 94, .14);
            color: #86efac;
        }

        .badge.inactive, .badge.banned, .badge.failed {
            background: rgba(239, 68, 68, .14);
            color: #fca5a5;
        }

        .badge.pending {
            background: rgba(249, 115, 22, .14);
            color: #fdba74;
        }

        .badge-toggle {
            border: 0;
            cursor: pointer;
            transition: transform .12s ease, filter .12s ease;
        }

        .badge-toggle:hover {
            transform: translateY(-1px);
            filter: brightness(1.08);
        }

        .muted {
            color: var(--muted);
        }

        .bars {
            display: grid;
            gap: 10px;
        }

        .bar-row {
            display: grid;
            grid-template-columns: minmax(90px, 150px) 1fr auto;
            gap: 10px;
            align-items: center;
            font-size: 13px;
        }

        .bar-track {
            height: 9px;
            background: #1b2535;
            border-radius: 999px;
            overflow: hidden;
        }

        .bar-fill {
            min-width: 3px;
            height: 100%;
            background: linear-gradient(90deg, #38bdf8, #22c55e);
        }

        .drawer-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .48);
            z-index: 40;
            display: none;
        }

        .drawer-backdrop.open {
            display: block;
        }

        .drawer {
            position: fixed;
            top: 0;
            right: 0;
            width: min(760px, calc(100vw - 18px));
            height: 100vh;
            background: #0f1623;
            border-left: 1px solid var(--line);
            z-index: 50;
            transform: translateX(100%);
            transition: transform .18s ease;
            display: flex;
            flex-direction: column;
            box-shadow: -24px 0 80px rgba(0, 0, 0, .32);
        }

        .drawer.open {
            transform: translateX(0);
        }

        .drawer-head {
            min-height: 64px;
            padding: 0 18px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .drawer-body {
            padding: 18px;
            overflow: auto;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .form-grid .full {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            color: #a8b3c4;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .toast {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 70;
            background: #111827;
            color: white;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px 14px;
            min-width: 220px;
            display: none;
        }

        .toast.show {
            display: block;
        }

        .empty {
            padding: 34px;
            text-align: center;
            color: var(--muted);
        }

        .checkbox-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
        }

        .check-row {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #121a28;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 9px 10px;
            font-size: 13px;
        }

        @media (max-width: 1100px) {
            .grid-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .two-col {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 820px) {
            .admin-shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: fixed;
                z-index: 30;
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .content {
                padding: 16px;
            }

            .grid-cards, .form-grid, .checkbox-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div id="loginView" class="login-shell">
        <div class="login-card">
            <div class="flex items-center gap-3 mb-6">
                <div class="brand-mark"><i class="fas fa-shield-halved"></i></div>
                <div>
                    <h1 class="text-xl font-bold">AiChat Pro 管理后台</h1>
                    <p class="text-sm muted mt-1">管理员登录</p>
                </div>
            </div>
            <div class="space-y-3">
                <input id="adminUsername" class="field w-full" placeholder="管理员用户名" autocomplete="username">
                <input id="adminPassword" type="password" class="field w-full" placeholder="密码" autocomplete="current-password">
                <button onclick="login()" class="btn btn-primary w-full">
                    <i class="fas fa-right-to-bracket"></i>
                    登录
                </button>
            </div>
        </div>
    </div>

    <div id="adminView" class="admin-shell hidden">
        <aside id="sidebar" class="sidebar">
            <div class="brand">
                <div class="brand-mark"><i class="fas fa-robot"></i></div>
                <div class="brand-text min-w-0">
                    <div class="font-bold leading-tight">AiChat Pro</div>
                    <div class="text-xs muted">管理控制台</div>
                </div>
            </div>
            <nav id="nav" class="nav"></nav>
            <div class="sidebar-foot">
                <button onclick="toggleSidebarCompact()" class="nav-btn w-full" title="折叠侧栏">
                    <i class="fas fa-angles-left"></i>
                    <span class="sidebar-foot-label">折叠侧栏</span>
                </button>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div class="flex items-center gap-3 min-w-0">
                    <button onclick="toggleMobileSidebar()" class="btn btn-ghost icon-btn lg:hidden" title="菜单">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="min-w-0">
                        <div id="pageTitle" class="font-bold truncate">概览</div>
                        <div id="pageSubtitle" class="text-xs muted truncate">实时运营概况</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="refreshCurrent()" class="btn btn-ghost icon-btn" title="刷新">
                        <i class="fas fa-rotate"></i>
                    </button>
                    <button onclick="logout()" class="btn btn-danger">
                        <i class="fas fa-right-from-bracket"></i>
                        <span>退出</span>
                    </button>
                </div>
            </header>
            <div id="content" class="content"></div>
        </main>
    </div>

    <div id="drawerBackdrop" class="drawer-backdrop" onclick="closeDrawer()"></div>
    <aside id="drawer" class="drawer">
        <div class="drawer-head">
            <div>
                <div id="drawerTitle" class="font-bold">编辑</div>
                <div id="drawerSubtitle" class="text-xs muted mt-1"></div>
            </div>
            <button onclick="closeDrawer()" class="btn btn-ghost icon-btn" title="关闭"><i class="fas fa-times"></i></button>
        </div>
        <div id="drawerBody" class="drawer-body"></div>
    </aside>
    <div id="toast" class="toast"></div>

<script>
const API_BASE = '/api';
const navItems = [
    ['dashboard', '概览', '实时运营概况', 'fa-chart-line'],
    ['users', '用户', '搜索、编辑、额度、封禁', 'fa-users'],
    ['models', '模型', '上下文、提示词、价格', 'fa-microchip'],
    ['plans', '套餐', '权益与模型授权', 'fa-layer-group'],
    ['transactions', '交易', '消费与充值流水', 'fa-receipt'],
    ['settings', '站点', '注册、公告、默认模型', 'fa-sliders'],
    ['system', '系统', '运行环境与扩展', 'fa-server'],
    ['admins', '管理员', '后台账号管理', 'fa-user-shield'],
];

const state = {
    token: localStorage.getItem('aichat_admin_token'),
    apiBase: localStorage.getItem('aichat_admin_api_base') || API_BASE,
    section: localStorage.getItem('aichat_admin_section') || 'dashboard',
    sidebarCollapsed: localStorage.getItem('aichat_admin_sidebar') === '1',
    users: { page: 1, search: '', status: '', data: null },
    transactions: { page: 1, search: '', type: '', status: '', data: null },
    dashboard: null,
    models: [],
    plans: [],
    settings: {},
    admins: [],
    system: null,
};

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

function money(value, digits = 4) {
    return '$' + Number(value || 0).toFixed(digits);
}

function millionPriceFromK(value) {
    return Number(value || 0) * 1000;
}

function kPriceFromMillion(value) {
    return Number(value || 0) / 1000;
}

function shortDate(value) {
    if (!value) return '-';
    return String(value).replace('T', ' ').slice(0, 16);
}

function valueOrDefault(value, fallback = 0) {
    const num = Number(value);
    return Number.isFinite(num) ? num : fallback;
}

function statusLabel(value) {
    const map = {
        active: '启用',
        inactive: '停用',
        banned: '封禁',
        pending: '待处理',
        completed: '完成',
        failed: '失败',
        refunded: '已退款',
    };
    return map[value] || String(value || '-');
}

function transactionTypeLabel(value) {
    const map = {
        purchase: '购买',
        usage: '消费',
        refund: '退款',
        quota_grant: '额度发放',
    };
    return map[value] || String(value || '-');
}

function periodLabel(value) {
    const map = {
        monthly: '月度',
        weekly: '周度',
    };
    return map[value] || String(value || '-');
}

function badge(value) {
    const raw = String(value || '-');
    return `<span class="badge ${esc(raw)}"><i class="fas fa-circle text-[7px]"></i>${esc(statusLabel(raw))}</span>`;
}

function statusToggleBadge(modelId, value) {
    const raw = String(value || '-');
    const next = raw === 'active' ? 'inactive' : 'active';
    return `<button type="button" class="badge badge-toggle ${esc(raw)}" title="点击切换为${esc(statusLabel(next))}" onclick="toggleModelStatus(${Number(modelId)}, '${esc(raw)}')"><i class="fas fa-circle text-[7px]"></i>${esc(statusLabel(raw))}</button>`;
}

function toast(message, ok = true) {
    const el = document.getElementById('toast');
    el.textContent = message;
    el.style.borderColor = ok ? 'rgba(34,197,94,.45)' : 'rgba(239,68,68,.55)';
    el.classList.add('show');
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(() => el.classList.remove('show'), 2600);
}

async function adminApi(endpoint, options = {}) {
    const headers = { 'Content-Type': 'application/json' };
    if (state.token) headers.Authorization = `Bearer ${state.token}`;
    const config = { ...options, headers: { ...headers, ...options.headers } };
    if (config.body && typeof config.body === 'object') config.body = JSON.stringify(config.body);
    const candidates = state.apiBase === '/api'
        ? ['/api', '/index.php/api']
        : [state.apiBase, '/api', '/index.php/api'];

    for (let i = 0; i < candidates.length; i++) {
        const base = candidates[i];
        const response = await fetch(`${base}${endpoint}`, config);
        const text = await response.text();

        if (response.status === 404 && i < candidates.length - 1) continue;
        state.apiBase = base;
        localStorage.setItem('aichat_admin_api_base', base);

        let data = {};
        try { data = text ? JSON.parse(text) : {}; } catch (e) { data = { success: false, message: text || '请求失败' }; }
        if (!data.message && data.error) {
            data.message = data.error;
        }
        if (response.status === 401 || response.status === 403) {
            localStorage.removeItem('aichat_admin_token');
            state.token = null;
            showLogin();
        }
        return data;
    }

    return { success: false, message: '网络错误' };
}

async function login() {
    const username = document.getElementById('adminUsername').value.trim();
    const password = document.getElementById('adminPassword').value;
    if (!username || !password) return toast('请输入用户名和密码', false);

    const result = await adminApi('/admin/login', { method: 'POST', body: { username, password } });
    if (!result.success) return toast(result.message || '登录失败', false);
    state.token = result.data.token;
    localStorage.setItem('aichat_admin_token', state.token);
    showAdmin();
    await refreshCurrent();
}

function logout() {
    localStorage.removeItem('aichat_admin_token');
    state.token = null;
    showLogin();
}

function showLogin() {
    document.getElementById('loginView').classList.remove('hidden');
    document.getElementById('adminView').classList.add('hidden');
}

function showAdmin() {
    document.getElementById('loginView').classList.add('hidden');
    document.getElementById('adminView').classList.remove('hidden');
    renderNav();
    document.getElementById('sidebar').classList.toggle('collapsed', state.sidebarCollapsed);
    renderSection();
}

function renderNav() {
    document.getElementById('nav').innerHTML = navItems.map(item => `
        <button class="nav-btn ${state.section === item[0] ? 'active' : ''}" onclick="setSection('${item[0]}')" title="${esc(item[1])}">
            <i class="fas ${item[3]}"></i>
            <span class="nav-label">${esc(item[1])}</span>
        </button>
    `).join('');
}

function setSection(section) {
    state.section = section;
    localStorage.setItem('aichat_admin_section', section);
    renderNav();
    renderSection();
    refreshCurrent();
    toggleMobileSidebar(false);
}

function setTitle() {
    const item = navItems.find(n => n[0] === state.section) || navItems[0];
    document.getElementById('pageTitle').textContent = item[1];
    document.getElementById('pageSubtitle').textContent = item[2];
}

function renderSection() {
    setTitle();
    const content = document.getElementById('content');
    const renderers = {
        dashboard: renderDashboard,
        users: renderUsers,
        models: renderModels,
        plans: renderPlans,
        transactions: renderTransactions,
        settings: renderSettings,
        system: renderSystem,
        admins: renderAdmins,
    };
    content.innerHTML = renderers[state.section] ? renderers[state.section]() : '';
}

async function refreshCurrent() {
    const loaders = {
        dashboard: loadDashboard,
        users: loadUsers,
        models: loadModels,
        plans: loadPlans,
        transactions: loadTransactions,
        settings: loadSettings,
        system: loadSystem,
        admins: loadAdmins,
    };
    if (loaders[state.section]) await loaders[state.section]();
}

function sectionHead(title, subtitle, action = '') {
    return `
        <div class="section-head">
            <div>
                <h1 class="section-title">${esc(title)}</h1>
                <p class="section-subtitle">${esc(subtitle)}</p>
            </div>
            <div>${action}</div>
        </div>
    `;
}

async function loadDashboard() {
    const result = await adminApi('/admin/dashboard');
    if (!result.success) return toast(result.message || '加载概览失败', false);
    state.dashboard = result.data;
    renderSection();
}

function renderDashboard() {
    const d = state.dashboard;
    if (!d) return loadingView('正在加载概览...');
    const metrics = [
        ['用户总数', d.total_users, 'fa-users', '#60a5fa'],
        ['今日活跃', d.active_today, 'fa-signal', '#4ade80'],
        ['总对话', d.total_chats, 'fa-comments', '#c084fc'],
        ['总消耗', money(d.total_consumed), 'fa-coins', '#fb923c'],
    ];
    return `
        ${sectionHead('运营概览', '用户、消耗、模型和套餐分布', `<button onclick="refreshCurrent()" class="btn btn-primary"><i class="fas fa-rotate"></i>刷新</button>`)}
        <section class="grid-cards mb-4">
            ${metrics.map(m => `
                <div class="metric-card">
                    <div class="flex items-center justify-between">
                        <span class="muted text-sm">${esc(m[0])}</span>
                        <span class="metric-icon" style="color:${m[3]}"><i class="fas ${m[2]}"></i></span>
                    </div>
                    <div class="text-3xl font-bold">${esc(m[1])}</div>
                    <div class="text-xs muted">周活 ${esc(d.active_week || 0)} / 月活 ${esc(d.active_month || 0)}</div>
                </div>
            `).join('')}
        </section>
        <section class="two-col mb-4">
            <div class="panel">
                <div class="panel-title">近 7 日消耗</div>
                ${renderTrendBars(d.usage_trend || [], 'total', value => money(value))}
            </div>
            <div class="panel">
                <div class="panel-title">套餐分布</div>
                ${renderBars(d.plan_stats || [], 'display_name', 'subscriber_count')}
            </div>
        </section>
        <section class="two-col">
            <div class="panel">
                <div class="panel-title">模型使用排行</div>
                ${renderBars(d.model_usage || [], 'display_name', 'message_count')}
            </div>
            <div class="panel">
                <div class="panel-title">最近注册用户</div>
                ${renderRecentUsers(d.recent_users || [])}
            </div>
        </section>
    `;
}

function renderTrendBars(rows, valueKey, formatter) {
    const max = Math.max(1, ...rows.map(r => Number(r[valueKey] || 0)));
    if (!rows.length) return `<div class="empty">暂无数据</div>`;
    return `<div class="bars">${rows.map(row => `
        <div class="bar-row">
            <div class="truncate muted">${esc(row.day || '-')}</div>
            <div class="bar-track"><div class="bar-fill" style="width:${Math.max(4, Number(row[valueKey] || 0) / max * 100)}%"></div></div>
            <div>${esc(formatter(row[valueKey]))}</div>
        </div>
    `).join('')}</div>`;
}

function renderBars(rows, labelKey, valueKey) {
    const max = Math.max(1, ...rows.map(r => Number(r[valueKey] || 0)));
    if (!rows.length) return `<div class="empty">暂无数据</div>`;
    return `<div class="bars">${rows.map(row => `
        <div class="bar-row">
            <div class="truncate">${esc(row[labelKey] || row.name || '-')}</div>
            <div class="bar-track"><div class="bar-fill" style="width:${Math.max(4, Number(row[valueKey] || 0) / max * 100)}%"></div></div>
            <div class="muted">${esc(row[valueKey] || 0)}</div>
        </div>
    `).join('')}</div>`;
}

function renderRecentUsers(users) {
    if (!users.length) return `<div class="empty">暂无用户</div>`;
    return `
        <div class="table-wrap">
            <table>
                <thead><tr><th>用户</th><th>套餐</th><th>余额</th><th>状态</th></tr></thead>
                <tbody>
                    ${users.map(u => `
                        <tr>
                            <td><div class="font-semibold">${esc(u.username)}</div><div class="muted">${esc(u.email)}</div></td>
                            <td>${esc(u.plan_display_name || u.plan_id)}</td>
                            <td>${money(u.quota_balance)}</td>
                            <td>${badge(u.status)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function loadUsers() {
    const q = new URLSearchParams({
        page: state.users.page,
        per_page: 20,
        search: state.users.search,
        status: state.users.status,
    });
    const result = await adminApi('/admin/users?' + q.toString());
    if (!result.success) return toast(result.message || '加载用户失败', false);
    state.users.data = result.data;
    renderSection();
}

function renderUsers() {
    const result = state.users.data;
    const users = result?.data || [];
    return `
        ${sectionHead('用户管理', '搜索用户，修改资料、套餐、额度和状态')}
        <div class="toolbar">
            <div class="filters">
                <input id="userSearch" class="field w-72" placeholder="搜索用户名或邮箱" value="${esc(state.users.search)}">
                <select id="userStatus" class="select">
                    <option value="">全部状态</option>
                    <option value="active" ${state.users.status === 'active' ? 'selected' : ''}>启用</option>
                    <option value="banned" ${state.users.status === 'banned' ? 'selected' : ''}>封禁</option>
                </select>
                <button onclick="applyUserFilters()" class="btn btn-primary"><i class="fas fa-search"></i>搜索</button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>用户</th><th>套餐</th><th>额度</th><th>状态</th><th>注册时间</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    ${users.length ? users.map(u => `
                        <tr>
                            <td>${esc(u.id)}</td>
                            <td><div class="font-semibold">${esc(u.username)}</div><div class="muted">${esc(u.email)}</div></td>
                            <td>${esc(u.plan_display_name || u.plan_id)}</td>
                            <td><div>${money(u.quota_balance)}</div><div class="muted">已用 ${money(u.total_used)}</div></td>
                            <td>${badge(u.status)}</td>
                            <td>${esc(shortDate(u.created_at))}</td>
                            <td><button onclick="openUserDrawer(${Number(u.id)})" class="btn btn-ghost"><i class="fas fa-pen"></i>编辑</button></td>
                        </tr>
                    `).join('') : `<tr><td colspan="7"><div class="empty">没有匹配用户</div></td></tr>`}
                </tbody>
            </table>
        </div>
        ${renderPager(result, 'changeUserPage')}
    `;
}

function applyUserFilters() {
    state.users.search = document.getElementById('userSearch').value.trim();
    state.users.status = document.getElementById('userStatus').value;
    state.users.page = 1;
    loadUsers();
}

function changeUserPage(page) {
    state.users.page = page;
    loadUsers();
}

async function openUserDrawer(id) {
    await ensurePlans();
    const result = await adminApi(`/admin/users/${id}`);
    if (!result.success) return toast(result.message || '加载用户失败', false);
    const u = result.data;
    openDrawer(`编辑用户 #${u.id}`, u.email, `
        <div class="form-grid">
            ${inputField('userUsername', '用户名', u.username)}
            ${inputField('userEmail', '邮箱', u.email, 'email')}
            ${selectField('userPlan', '套餐', state.plans.map(p => [p.id, p.display_name]), u.plan_id)}
            ${selectField('userStatusEdit', '状态', [['active','启用'], ['banned','封禁']], u.status)}
            ${inputField('userQuota', '剩余额度', u.quota_balance, 'number', '0.0001')}
            ${inputField('userUsed', '累计使用', u.total_used, 'number', '0.0001')}
            <label class="check-row full">
                <input id="userAutoRenew" type="checkbox" ${Number(u.auto_renew) ? 'checked' : ''}>
                <span>自动续费</span>
            </label>
            <div class="panel full">
                <div class="panel-title">用户统计</div>
                <div class="grid grid-cols-3 gap-3 text-sm">
                    <div><div class="muted">对话</div><div class="text-xl font-bold">${esc(u.stats?.chat_count || 0)}</div></div>
                    <div><div class="muted">消息</div><div class="text-xl font-bold">${esc(u.stats?.message_count || 0)}</div></div>
                    <div><div class="muted">月消耗</div><div class="text-xl font-bold">${money(u.stats?.monthly_usage)}</div></div>
                </div>
            </div>
            <div class="full flex flex-wrap gap-2">
                <button onclick="saveUser(${Number(u.id)})" class="btn btn-primary"><i class="fas fa-floppy-disk"></i>保存资料</button>
                <button onclick="resetUserPassword(${Number(u.id)})" class="btn btn-ghost"><i class="fas fa-key"></i>重置密码</button>
                <button onclick="adjustUserQuota(${Number(u.id)}, 'add')" class="btn btn-ghost"><i class="fas fa-plus"></i>增加额度</button>
                <button onclick="adjustUserQuota(${Number(u.id)}, 'deduct')" class="btn btn-ghost"><i class="fas fa-minus"></i>扣减额度</button>
                <button onclick="deleteUser(${Number(u.id)})" class="btn btn-danger"><i class="fas fa-trash"></i>删除用户</button>
            </div>
        </div>
    `);
}

async function saveUser(id) {
    const payload = {
        username: document.getElementById('userUsername').value.trim(),
        email: document.getElementById('userEmail').value.trim(),
        plan_id: Number(document.getElementById('userPlan').value),
        status: document.getElementById('userStatusEdit').value,
        quota_balance: Number(document.getElementById('userQuota').value),
        total_used: Number(document.getElementById('userUsed').value),
        auto_renew: document.getElementById('userAutoRenew').checked ? 1 : 0,
    };
    const result = await adminApi(`/admin/users/${id}`, { method: 'PUT', body: payload });
    toast(result.message || (result.success ? '已保存' : '保存失败'), result.success);
    if (result.success) {
        await loadUsers();
        closeDrawer();
    }
}

async function resetUserPassword(id) {
    const password = prompt('输入新密码（至少 8 位）');
    if (password === null) return;
    const result = await adminApi(`/admin/users/${id}/password`, { method: 'PUT', body: { password } });
    toast(result.message || (result.success ? '密码已重置' : '重置失败'), result.success);
}

async function adjustUserQuota(id, action) {
    const raw = prompt(action === 'add' ? '增加多少额度？' : '扣减多少额度？', '1');
    if (raw === null) return;
    const amount = Number(raw);
    if (!amount || amount <= 0) return toast('请输入有效额度', false);
    const result = await adminApi(`/admin/users/${id}/quota`, { method: 'PUT', body: { action, amount } });
    toast(result.message || (result.success ? '额度已更新' : '操作失败'), result.success);
    if (result.success) openUserDrawer(id);
}

async function deleteUser(id) {
    if (!confirm('确定删除这个用户？此操作会删除其关联数据。')) return;
    const result = await adminApi(`/admin/users/${id}`, { method: 'DELETE' });
    toast(result.message || (result.success ? '用户已删除' : '删除失败'), result.success);
    if (result.success) {
        closeDrawer();
        await loadUsers();
    }
}

async function loadModels() {
    const result = await adminApi('/admin/models');
    if (!result.success) return toast(result.message || '加载模型失败', false);
    state.models = result.data || [];
    renderSection();
}

function renderModels() {
    return `
        ${sectionHead('模型配置', '管理 API、上下文、系统提示词、价格和每日限制', `<button onclick="openModelDrawer()" class="btn btn-primary"><i class="fas fa-plus"></i>新增模型</button>`)}
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>模型</th><th>提供商</th><th>上下文</th><th>输出</th><th>温度</th><th>价格 / 百万 Token</th><th>状态</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    ${state.models.length ? state.models.map(m => `
                        <tr>
                            <td><div class="font-semibold">${esc(m.display_name)}</div><div class="muted">${esc(m.model_id)}</div></td>
                            <td>${esc(m.provider)}</td>
                            <td>${Number(m.max_context_tokens || 0).toLocaleString()}</td>
                            <td>${Number(m.max_output_tokens || 0).toLocaleString()}</td>
                            <td>${esc(m.default_temperature)}</td>
                            <td><div>入 ${money(millionPriceFromK(m.pricing_input), 4)}</div><div class="muted">出 ${money(millionPriceFromK(m.pricing_output), 4)}</div></td>
                            <td>${statusToggleBadge(m.id, m.status)}</td>
                            <td><button onclick="openModelDrawer(${Number(m.id)})" class="btn btn-ghost"><i class="fas fa-pen"></i>编辑</button></td>
                        </tr>
                    `).join('') : `<tr><td colspan="8"><div class="empty">暂无模型</div></td></tr>`}
                </tbody>
            </table>
        </div>
    `;
}

function openModelDrawer(id = null) {
    const m = id ? state.models.find(item => Number(item.id) === Number(id)) : {};
    const title = id ? `编辑模型 #${id}` : '新增模型';
    const caps = normalizeCapabilities(m.capabilities);
    openDrawer(title, m?.model_id || '', `
        <div class="form-grid">
            ${inputField('modelName', '内部标识', m.name || m.model_id || '')}
            ${inputField('modelDisplayName', '显示名称', m.display_name || '')}
            ${inputField('modelProvider', '提供商', m.provider || 'openai')}
            ${inputField('modelId', '模型 ID', m.model_id || '')}
            ${inputField('modelBaseUrl', 'API 基础地址', m.api_base_url || '', 'url')}
            ${inputField('modelApiKey', 'API 密钥', m.api_key || '', 'password')}
            ${inputField('modelContext', '上下文长度', m.max_context_tokens || 4096, 'number')}
            ${inputField('modelOutput', '最大输出', m.max_output_tokens || 2048, 'number')}
            ${inputField('modelTemp', '默认温度', m.default_temperature || 0.7, 'number', '0.01')}
            ${inputField('modelDailyLimit', '每日调用限制', m.daily_limit || 0, 'number')}
            ${inputField('modelPriceIn', '输入价格 / 百万 Token', millionPriceFromK(m.pricing_input || 0), 'number', '0.0001')}
            ${inputField('modelPriceOut', '输出价格 / 百万 Token', millionPriceFromK(m.pricing_output || 0), 'number', '0.0001')}
            ${inputField('modelSort', '排序', m.sort_order || 0, 'number')}
            ${selectField('modelStatus', '状态', [['active','启用'], ['inactive','停用']], m.status || 'active')}
            ${textareaField('modelSystemPrompt', '系统提示词', m.system_prompt || 'You are a helpful AI assistant. Answer clearly, honestly, and in the user language when practical.')}
            ${textareaField('modelDescription', '描述', m.description || '')}
            <div class="full">
                <label class="form-label">能力标签</label>
                <div class="checkbox-list">
                    ${['streaming','vision','function_calling','deep_thinking','tools','json_mode'].map(cap => `
                        <label class="check-row">
                            <input type="checkbox" data-capability="${cap}" ${caps.includes(cap) ? 'checked' : ''}>
                            <span>${esc(cap)}</span>
                        </label>
                    `).join('')}
                </div>
            </div>
            <div class="full flex gap-2">
                <button onclick="saveModel(${id ? Number(id) : 'null'})" class="btn btn-primary"><i class="fas fa-floppy-disk"></i>保存模型</button>
                ${id ? `<button onclick="deleteModel(${Number(id)})" class="btn btn-danger"><i class="fas fa-trash"></i>删除模型</button>` : ''}
            </div>
        </div>
    `);
}

function normalizeCapabilities(raw) {
    if (Array.isArray(raw)) return raw;
    try {
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return String(raw || '').split(',').map(v => v.trim()).filter(Boolean);
    }
}

async function saveModel(id) {
    const capabilities = [...document.querySelectorAll('[data-capability]:checked')].map(el => el.dataset.capability);
    const payload = {
        name: document.getElementById('modelName').value.trim(),
        display_name: document.getElementById('modelDisplayName').value.trim(),
        provider: document.getElementById('modelProvider').value.trim(),
        model_id: document.getElementById('modelId').value.trim(),
        api_base_url: document.getElementById('modelBaseUrl').value.trim(),
        api_key: document.getElementById('modelApiKey').value,
        max_context_tokens: valueOrDefault(document.getElementById('modelContext').value, 4096),
        max_output_tokens: valueOrDefault(document.getElementById('modelOutput').value, 2048),
        default_temperature: valueOrDefault(document.getElementById('modelTemp').value, 0.7),
        daily_limit: valueOrDefault(document.getElementById('modelDailyLimit').value, 0),
        pricing_input: kPriceFromMillion(valueOrDefault(document.getElementById('modelPriceIn').value, 0)),
        pricing_output: kPriceFromMillion(valueOrDefault(document.getElementById('modelPriceOut').value, 0)),
        sort_order: valueOrDefault(document.getElementById('modelSort').value, 0),
        status: document.getElementById('modelStatus').value,
        system_prompt: document.getElementById('modelSystemPrompt').value,
        description: document.getElementById('modelDescription').value,
        capabilities,
    };
    const endpoint = id ? `/admin/models/${id}` : '/admin/models';
    const method = id ? 'PUT' : 'POST';
    const result = await adminApi(endpoint, { method, body: payload });
    toast(result.message || (result.success ? '模型已保存' : '保存失败'), result.success);
    if (result.success) {
        closeDrawer();
        await loadModels();
    }
}

async function toggleModelStatus(id, currentStatus) {
    const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const result = await adminApi(`/admin/models/${id}`, { method: 'PUT', body: { status: nextStatus } });
    toast(result.message || (result.success ? `模型已${nextStatus === 'active' ? '启用' : '停用'}` : '状态切换失败'), result.success);
    if (!result.success) return;
    const target = state.models.find(model => Number(model.id) === Number(id));
    if (target) target.status = nextStatus;
    renderSection();
}

async function deleteModel(id) {
    if (!confirm('确定删除这个模型配置？')) return;
    const result = await adminApi(`/admin/models/${id}`, { method: 'DELETE' });
    toast(result.message || (result.success ? '模型已删除' : '删除失败'), result.success);
    if (result.success) {
        closeDrawer();
        await loadModels();
    }
}

async function loadPlans() {
    await Promise.all([ensureModels(), (async () => {
        const result = await adminApi('/admin/plans');
        if (!result.success) return toast(result.message || '加载套餐失败', false);
        state.plans = result.data || [];
    })()]);
    renderSection();
}

async function ensurePlans() {
    if (state.plans.length) return;
    const result = await adminApi('/admin/plans');
    if (result.success) state.plans = result.data || [];
}

async function ensureModels() {
    if (state.models.length) return;
    const result = await adminApi('/admin/models');
    if (result.success) state.models = result.data || [];
}

function renderPlans() {
    return `
        ${sectionHead('套餐管理', '配置价格、额度、功能和可用模型', `<button onclick="openPlanDrawer()" class="btn btn-primary"><i class="fas fa-plus"></i>新增套餐</button>`)}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            ${state.plans.map(plan => `
                <div class="panel">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <div class="font-bold text-lg">${esc(plan.display_name)}</div>
                            <div class="muted text-sm">${esc(plan.name)}</div>
                        </div>
                        ${badge(plan.status)}
                    </div>
                    <div class="text-3xl font-bold mb-2">${money(plan.price, 2)}<span class="text-sm muted font-normal"> / 月</span></div>
                    <div class="muted text-sm mb-4">额度 ${money(plan.quota_amount, 2)} / ${esc(periodLabel(plan.quota_period))}，订阅用户 ${esc(plan.subscriber_count || 0)}</div>
                    <button onclick="openPlanDrawer(${Number(plan.id)})" class="btn btn-ghost"><i class="fas fa-pen"></i>编辑套餐</button>
                </div>
            `).join('') || `<div class="panel empty">暂无套餐</div>`}
        </div>
    `;
}

async function openPlanDrawer(id = null) {
    await ensureModels();
    const plan = id ? state.plans.find(p => Number(p.id) === Number(id)) : {};
    let modelIds = [];
    if (id) {
        const result = await adminApi(`/admin/plans/${id}/models`);
        if (result.success) modelIds = result.data || [];
    }
    const features = parseFeatures(plan.features).join('\n');
    openDrawer(id ? `编辑套餐 #${id}` : '新增套餐', plan?.display_name || '', `
        <div class="form-grid">
            ${inputField('planName', '套餐标识', plan.name || '')}
            ${inputField('planDisplayName', '显示名称', plan.display_name || '')}
            ${inputField('planPrice', '月价格', plan.price || 0, 'number', '0.01')}
            ${inputField('planQuota', '发放额度', plan.quota_amount || 0, 'number', '0.01')}
            ${selectField('planPeriod', '额度周期', [['monthly','月度'], ['weekly','周度']], plan.quota_period || 'monthly')}
            ${selectField('planStatus', '状态', [['active','启用'], ['inactive','停用']], plan.status || 'active')}
            ${inputField('planSort', '排序', plan.sort_order || 0, 'number')}
            ${textareaField('planFeatures', '功能列表（每行一项）', features)}
            <div class="full">
                <label class="form-label">可用模型</label>
                <div class="checkbox-list">
                    ${state.models.map(m => `
                        <label class="check-row">
                            <input type="checkbox" data-plan-model="${Number(m.id)}" ${modelIds.includes(Number(m.id)) ? 'checked' : ''}>
                            <span>${esc(m.display_name)}</span>
                        </label>
                    `).join('')}
                </div>
            </div>
            <div class="full flex gap-2">
                <button onclick="savePlan(${id ? Number(id) : 'null'})" class="btn btn-primary"><i class="fas fa-floppy-disk"></i>保存套餐</button>
                ${id ? `<button onclick="deletePlan(${Number(id)})" class="btn btn-danger"><i class="fas fa-trash"></i>删除套餐</button>` : ''}
            </div>
        </div>
    `);
}

function parseFeatures(raw) {
    if (Array.isArray(raw)) return raw;
    try {
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return String(raw || '').split('\n').map(v => v.trim()).filter(Boolean);
    }
}

async function savePlan(id) {
    const payload = {
        name: document.getElementById('planName').value.trim(),
        display_name: document.getElementById('planDisplayName').value.trim(),
        price: Number(document.getElementById('planPrice').value),
        quota_amount: Number(document.getElementById('planQuota').value),
        quota_period: document.getElementById('planPeriod').value,
        status: document.getElementById('planStatus').value,
        sort_order: Number(document.getElementById('planSort').value),
        features: document.getElementById('planFeatures').value.split('\n').map(v => v.trim()).filter(Boolean),
    };
    const endpoint = id ? `/admin/plans/${id}` : '/admin/plans';
    const method = id ? 'PUT' : 'POST';
    const result = await adminApi(endpoint, { method, body: payload });
    if (!result.success) return toast(result.message || '保存失败', false);
    const planId = id || result.data?.id;
    const modelIds = [...document.querySelectorAll('[data-plan-model]:checked')].map(el => Number(el.dataset.planModel));
    if (planId) await adminApi(`/admin/plans/${planId}/models`, { method: 'PUT', body: { model_ids: modelIds } });
    toast(result.message || '套餐已保存');
    closeDrawer();
    state.plans = [];
    await loadPlans();
}

async function deletePlan(id) {
    if (!confirm('确定删除这个套餐？')) return;
    const result = await adminApi(`/admin/plans/${id}`, { method: 'DELETE' });
    toast(result.message || (result.success ? '套餐已删除' : '删除失败'), result.success);
    if (result.success) {
        closeDrawer();
        state.plans = [];
        await loadPlans();
    }
}

async function loadTransactions() {
    const q = new URLSearchParams({
        page: state.transactions.page,
        search: state.transactions.search,
        type: state.transactions.type,
        status: state.transactions.status,
    });
    const result = await adminApi('/admin/transactions?' + q.toString());
    if (!result.success) return toast(result.message || '加载交易失败', false);
    state.transactions.data = result.data;
    renderSection();
}

function renderTransactions() {
    const result = state.transactions.data;
    const rows = result?.data || [];
    return `
        ${sectionHead('交易流水', '查看用户消费、购买、退款和额度发放记录')}
        <div class="toolbar">
            <div class="filters">
                <input id="txSearch" class="field w-72" placeholder="搜索用户、邮箱或描述" value="${esc(state.transactions.search)}">
                <select id="txType" class="select">
                    ${['', 'purchase', 'usage', 'refund', 'quota_grant'].map(v => `<option value="${v}" ${state.transactions.type === v ? 'selected' : ''}>${v ? transactionTypeLabel(v) : '全部类型'}</option>`).join('')}
                </select>
                <select id="txStatus" class="select">
                    ${['', 'pending', 'completed', 'failed', 'refunded'].map(v => `<option value="${v}" ${state.transactions.status === v ? 'selected' : ''}>${v ? statusLabel(v) : '全部状态'}</option>`).join('')}
                </select>
                <button onclick="applyTransactionFilters()" class="btn btn-primary"><i class="fas fa-search"></i>搜索</button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>用户</th><th>类型</th><th>金额</th><th>状态</th><th>描述</th><th>时间</th></tr></thead>
                <tbody>
                    ${rows.length ? rows.map(t => `
                        <tr>
                            <td>${esc(t.id)}</td>
                            <td><div class="font-semibold">${esc(t.username)}</div><div class="muted">${esc(t.email)}</div></td>
                            <td>${esc(transactionTypeLabel(t.type))}</td>
                            <td>${money(t.amount, 6)}</td>
                            <td>${badge(t.payment_status)}</td>
                            <td>${renderTransactionDescription(t)}</td>
                            <td>${esc(shortDate(t.created_at))}</td>
                        </tr>
                    `).join('') : `<tr><td colspan="7"><div class="empty">暂无流水</div></td></tr>`}
                </tbody>
            </table>
        </div>
        ${renderPager(result, 'changeTransactionPage')}
    `;
}

function renderTransactionDescription(t) {
    const count = Number(t.transaction_count || 1);
    const totalTokens = Number(t.total_tokens || 0);
    const inputTokens = Number(t.input_tokens || 0);
    const outputTokens = Number(t.output_tokens || 0);
    const parts = [`<div>${esc(t.description || '-')}</div>`];
    if (count > 1) {
        parts.push(`<div class="muted">已合并 ${count.toLocaleString()} 笔同类交易，只显示最新一笔时间</div>`);
    }
    if (totalTokens > 0) {
        parts.push(`<div class="muted">Token ${totalTokens.toLocaleString()}（入 ${inputTokens.toLocaleString()} / 出 ${outputTokens.toLocaleString()}）</div>`);
    }
    return parts.join('');
}

function applyTransactionFilters() {
    state.transactions.search = document.getElementById('txSearch').value.trim();
    state.transactions.type = document.getElementById('txType').value;
    state.transactions.status = document.getElementById('txStatus').value;
    state.transactions.page = 1;
    loadTransactions();
}

function changeTransactionPage(page) {
    state.transactions.page = page;
    loadTransactions();
}

async function loadSettings() {
    await ensureModels();
    const result = await adminApi('/admin/settings');
    if (!result.success) return toast(result.message || '加载设置失败', false);
    state.settings = result.data || {};
    renderSection();
}

function renderSettings() {
    const s = state.settings || {};
    return `
        ${sectionHead('站点设置', '调整注册、公告、默认模型和基础信息')}
        <div class="panel">
            <div class="form-grid">
                ${inputField('settingSiteName', '站点名称', s.site_name || '')}
                ${inputField('settingSiteUrl', '站点 URL', s.site_url || '')}
                ${selectField('settingDefaultModel', '默认模型', state.models.map(m => [m.model_id, m.display_name]), s.default_model || '')}
                ${inputField('settingMaxHistory', '最大聊天历史', s.max_chat_history || 100, 'number')}
                ${inputField('settingContact', '联系邮箱', s.contact_email || '', 'email')}
                ${inputField('settingIcp', 'ICP备案号', s.icp_number || '')}
                ${textareaField('settingDescription', '站点描述', s.site_description || '')}
                ${textareaField('settingAnnouncement', '公告', s.site_announcement || '')}
                <label class="check-row">
                    <input id="settingAllowRegistration" type="checkbox" ${s.allow_registration !== '0' ? 'checked' : ''}>
                    <span>开放注册</span>
                </label>
                <label class="check-row">
                    <input id="settingMaintenance" type="checkbox" ${s.maintenance_mode === '1' ? 'checked' : ''}>
                    <span>维护模式</span>
                </label>
                <div class="full">
                    <button onclick="saveSettings()" class="btn btn-primary"><i class="fas fa-floppy-disk"></i>保存设置</button>
                </div>
            </div>
        </div>
    `;
}

async function saveSettings() {
    const payload = {
        ...state.settings,
        site_name: document.getElementById('settingSiteName').value.trim(),
        site_url: document.getElementById('settingSiteUrl').value.trim(),
        default_model: document.getElementById('settingDefaultModel').value,
        max_chat_history: document.getElementById('settingMaxHistory').value,
        contact_email: document.getElementById('settingContact').value.trim(),
        icp_number: document.getElementById('settingIcp').value.trim(),
        site_description: document.getElementById('settingDescription').value,
        site_announcement: document.getElementById('settingAnnouncement').value,
        allow_registration: document.getElementById('settingAllowRegistration').checked ? '1' : '0',
        maintenance_mode: document.getElementById('settingMaintenance').checked ? '1' : '0',
    };
    const result = await adminApi('/admin/settings', { method: 'PUT', body: payload });
    toast(result.message || (result.success ? '设置已保存' : '保存失败'), result.success);
    if (result.success) {
        state.settings = payload;
    }
}

async function loadSystem() {
    const result = await adminApi('/admin/system/info');
    if (!result.success) return toast(result.message || '加载系统信息失败', false);
    state.system = result.data;
    renderSection();
}

function renderSystem() {
    const info = state.system;
    if (!info) return loadingView('正在加载系统信息...');
    const exts = info.extensions || {};
    return `
        ${sectionHead('系统信息', '运行环境、数据库和 PHP 扩展状态')}
        <section class="grid-cards mb-4">
            ${[
                ['PHP', `${info.php_version} / ${info.php_sapi}`, 'fa-code'],
                ['MySQL', info.mysql_version, 'fa-database'],
                ['磁盘可用', info.disk_free || '-', 'fa-hard-drive'],
                ['磁盘总量', info.disk_total || '-', 'fa-server'],
            ].map(item => `
                <div class="metric-card">
                    <div class="flex items-center justify-between"><span class="muted">${esc(item[0])}</span><span class="metric-icon"><i class="fas ${item[2]}"></i></span></div>
                    <div class="text-xl font-bold break-all">${esc(item[1])}</div>
                </div>
            `).join('')}
        </section>
        <div class="panel">
            <div class="panel-title">PHP 扩展</div>
            <div class="flex flex-wrap gap-2">
                ${Object.keys(exts).map(key => `<span class="badge ${exts[key] ? 'active' : 'inactive'}">${esc(key)}</span>`).join('')}
            </div>
        </div>
    `;
}

async function loadAdmins() {
    const result = await adminApi('/admin/admins');
    if (!result.success) return toast(result.message || '加载管理员失败', false);
    state.admins = result.data || [];
    renderSection();
}

function renderAdmins() {
    return `
        ${sectionHead('管理员账号', '创建或移除后台管理员')}
        <section class="two-col">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>用户名</th><th>创建时间</th><th>操作</th></tr></thead>
                    <tbody>
                        ${state.admins.map(a => `
                            <tr>
                                <td>${esc(a.id)}</td>
                                <td>${esc(a.username)}</td>
                                <td>${esc(shortDate(a.created_at))}</td>
                                <td><button onclick="deleteAdmin(${Number(a.id)})" class="btn btn-danger"><i class="fas fa-trash"></i>删除</button></td>
                            </tr>
                        `).join('') || `<tr><td colspan="4"><div class="empty">暂无管理员</div></td></tr>`}
                    </tbody>
                </table>
            </div>
            <div class="panel">
                <div class="panel-title">新增管理员</div>
                <div class="space-y-3">
                    <input id="newAdminName" class="field w-full" placeholder="用户名">
                    <input id="newAdminPassword" type="password" class="field w-full" placeholder="密码（至少 8 位）">
                    <button onclick="createAdmin()" class="btn btn-primary"><i class="fas fa-plus"></i>创建管理员</button>
                </div>
            </div>
        </section>
    `;
}

async function createAdmin() {
    const username = document.getElementById('newAdminName').value.trim();
    const password = document.getElementById('newAdminPassword').value;
    const result = await adminApi('/admin/admins', { method: 'POST', body: { username, password } });
    toast(result.message || (result.success ? '管理员已创建' : '创建失败'), result.success);
    if (result.success) loadAdmins();
}

async function deleteAdmin(id) {
    if (!confirm('确定删除这个管理员？')) return;
    const result = await adminApi(`/admin/admins/${id}`, { method: 'DELETE' });
    toast(result.message || (result.success ? '管理员已删除' : '删除失败'), result.success);
    if (result.success) loadAdmins();
}

function renderPager(result, callback) {
    if (!result) return '';
    const page = Number(result.page || 1);
    const totalPages = Number(result.total_pages || result.last_page || 1);
    if (totalPages <= 1) return '';
    return `
        <div class="flex items-center justify-between mt-4 text-sm muted">
            <span>第 ${page} / ${totalPages} 页，共 ${esc(result.total || 0)} 条</span>
            <div class="flex gap-2">
                <button class="btn btn-ghost" ${page <= 1 ? 'disabled' : ''} onclick="${callback}(${page - 1})">上一页</button>
                <button class="btn btn-ghost" ${page >= totalPages ? 'disabled' : ''} onclick="${callback}(${page + 1})">下一页</button>
            </div>
        </div>
    `;
}

function loadingView(text) {
    return `<div class="panel empty">${esc(text)}</div>`;
}

function inputField(id, label, value = '', type = 'text', step = '') {
    return `
        <div>
            <label class="form-label" for="${id}">${esc(label)}</label>
            <input id="${id}" type="${type}" ${step ? `step="${step}"` : ''} class="field w-full" value="${esc(value)}">
        </div>
    `;
}

function selectField(id, label, options, selected) {
    return `
        <div>
            <label class="form-label" for="${id}">${esc(label)}</label>
            <select id="${id}" class="select w-full">
                ${options.map(([value, text]) => `<option value="${esc(value)}" ${String(value) === String(selected) ? 'selected' : ''}>${esc(text)}</option>`).join('')}
            </select>
        </div>
    `;
}

function textareaField(id, label, value = '') {
    return `
        <div class="full">
            <label class="form-label" for="${id}">${esc(label)}</label>
            <textarea id="${id}" class="textarea">${esc(value)}</textarea>
        </div>
    `;
}

function openDrawer(title, subtitle, body) {
    document.getElementById('drawerTitle').textContent = title;
    document.getElementById('drawerSubtitle').textContent = subtitle || '';
    document.getElementById('drawerBody').innerHTML = body;
    document.getElementById('drawerBackdrop').classList.add('open');
    document.getElementById('drawer').classList.add('open');
}

function closeDrawer() {
    document.getElementById('drawerBackdrop').classList.remove('open');
    document.getElementById('drawer').classList.remove('open');
}

function toggleSidebarCompact() {
    state.sidebarCollapsed = !state.sidebarCollapsed;
    localStorage.setItem('aichat_admin_sidebar', state.sidebarCollapsed ? '1' : '0');
    document.getElementById('sidebar').classList.toggle('collapsed', state.sidebarCollapsed);
}

function toggleMobileSidebar(force) {
    const sidebar = document.getElementById('sidebar');
    if (typeof force === 'boolean') {
        sidebar.classList.toggle('open', force);
    } else {
        sidebar.classList.toggle('open');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('adminPassword').addEventListener('keydown', event => {
        if (event.key === 'Enter') login();
    });
    if (state.token) {
        showAdmin();
        refreshCurrent();
    } else {
        showLogin();
    }
});
</script>
</body>
</html>
