const API_BASE_DEFAULT = '/api';
let API_BASE = localStorage.getItem('aichat_api_base') || API_BASE_DEFAULT;
let token = localStorage.getItem('aichat_token');
let currentUser = null;
let currentChatId = null;
let webSearchEnabled = false;
let deepThinkEnabled = false;
let uploadedFile = null;
let isStreaming = false;
let availableModels = [];

function setApiBase(base) {
    API_BASE = base;
    localStorage.setItem('aichat_api_base', API_BASE);
}

function getApiBase() {
    return API_BASE;
}

function apiBaseCandidates() {
    const list = [API_BASE];
    if (!list.includes('/api')) list.push('/api');
    if (!list.includes('/index.php/api')) list.push('/index.php/api');
    return list;
}

async function api(endpoint, options = {}) {
    const headers = { 'Content-Type': 'application/json' };
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    const config = { ...options, headers: { ...headers, ...options.headers } };
    if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
        config.body = JSON.stringify(config.body);
    }
    try {
        const candidates = apiBaseCandidates();
        for (let i = 0; i < candidates.length; i++) {
            const base = candidates[i];
            const response = await fetch(`${base}${endpoint}`, config);
            const text = await response.text();

            if (response.status === 404 && i < candidates.length - 1) {
                continue;
            }

            setApiBase(base);

            let data = {};
            try { data = text ? JSON.parse(text) : {}; } catch (e) { data = { success: false, message: text }; }

            if (response.status === 401) {
                localStorage.removeItem('aichat_token');
                token = null;
                currentUser = null;
                updateAuthState();
                showAuthModal('login');
                return null;
            }
            return data;
        }
        return { success: false, message: 'Network error' };
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Network error' };
    }
}

function showAuthModal(mode = 'login') {
    if (mode === 'register') {
        showRegister();
    } else {
        showLogin();
    }
    document.getElementById('authModal').classList.remove('hidden');
}

function hideAuthModal() {
    document.getElementById('authModal').classList.add('hidden');
}

function showLogin() {
    document.getElementById('loginForm').classList.remove('hidden');
    document.getElementById('registerForm').classList.add('hidden');
}

function showRegister() {
    document.getElementById('loginForm').classList.add('hidden');
    document.getElementById('registerForm').classList.remove('hidden');
}

async function login() {
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    if (!email || !password) { alert('请填写邮箱和密码'); return; }

    const result = await api('/auth/login', {
        method: 'POST',
        body: { email, password }
    });

    if (result && result.success) {
        token = result.data.token;
        currentUser = result.data.user;
        localStorage.setItem('aichat_token', token);
        hideAuthModal();
        initApp();
    } else {
        alert(result?.message || '登录失败');
    }
}

async function register() {
    const username = document.getElementById('regUsername').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value;
    if (!username || !email || !password) { alert('请填写所有字段'); return; }
    if (password.length < 8) { alert('密码至少8位'); return; }

    const result = await api('/auth/register', {
        method: 'POST',
        body: { username, email, password }
    });

    if (result && result.success) {
        token = result.data.token;
        currentUser = result.data.user;
        localStorage.setItem('aichat_token', token);
        hideAuthModal();
        initApp();
    } else {
        alert(result?.message || '注册失败');
    }
}

function logout() {
    localStorage.removeItem('aichat_token');
    token = null;
    currentUser = null;
    currentChatId = null;
    closeModal('profileModal');
    updateAuthState();
    showWelcome();
}

async function initApp() {
    if (!token) {
        currentUser = null;
        updateAuthState();
        return;
    }

    const result = await api('/auth/me');
    if (result && result.success) {
        currentUser = result.data;
        updateUserInfo();
        loadChatList();
        loadModels();
        loadUsageStats();
    } else {
        localStorage.removeItem('aichat_token');
        token = null;
        currentUser = null;
        updateAuthState();
    }
}

function updateAuthState() {
    const loggedIn = !!currentUser;
    document.getElementById('loginButton')?.classList.toggle('hidden', loggedIn);
    document.getElementById('userInfo')?.classList.toggle('hidden', !loggedIn);
    document.getElementById('quotaInfo')?.classList.toggle('hidden', !loggedIn);

    if (!loggedIn) {
        const chatList = document.getElementById('chatList');
        if (chatList) {
            chatList.innerHTML = `
                <div class="px-3 py-4 text-sm text-gray-500">
                    登录后查看和同步聊天记录
                </div>
            `;
        }
        const selector = document.getElementById('modelSelector');
        if (selector) {
            selector.innerHTML = '<option>登录后选择模型</option>';
        }
        document.getElementById('userName').textContent = '';
        document.getElementById('quotaInfo').textContent = '';
    }
}

function updateUserInfo() {
    if (!currentUser) return;
    updateAuthState();
    document.getElementById('userName').textContent = currentUser.username;
    document.getElementById('profileName').textContent = currentUser.username;
    document.getElementById('profileEmail').textContent = currentUser.email;
    document.getElementById('quotaInfo').textContent = `余额: $${parseFloat(currentUser.quota_balance || 0).toFixed(2)}`;
    document.getElementById('statPlan').textContent = currentUser.plan && currentUser.plan.display_name ? currentUser.plan.display_name : (currentUser.plan_id || 'Free');

    if (currentUser.avatar) {
        const avatar = escapeHtml(currentUser.avatar);
        document.getElementById('userAvatar').innerHTML = `<img src="${avatar}" class="w-8 h-8 rounded-full object-cover" alt="">`;
        document.getElementById('profileAvatar').innerHTML = `<img src="${avatar}" class="w-20 h-20 rounded-full object-cover" alt="">`;
    }
}

async function loadUsageStats() {
    const result = await api('/user/usage');
    if (result && result.success) {
        const stats = result.data;
        document.getElementById('statBalance').textContent = `$${parseFloat(stats.quota_balance || 0).toFixed(2)}`;
        document.getElementById('statUsed').textContent = `$${parseFloat(stats.monthly_used || 0).toFixed(2)}`;
        document.getElementById('statChats').textContent = stats.monthly_chats || 0;
    }
}

async function loadModels() {
    if (!token) {
        updateAuthState();
        return;
    }
    const result = await api('/models');
    if (result && result.success) {
        availableModels = Array.isArray(result.data) ? result.data : [];
        const selector = document.getElementById('modelSelector');
        const previousValue = selector.value;
        selector.innerHTML = '';
        availableModels.forEach(model => {
            const option = document.createElement('option');
            option.value = model.model_id;
            option.textContent = model.display_name;
            selector.appendChild(option);
        });
        if (previousValue && availableModels.some(model => String(model.model_id) === String(previousValue))) {
            selector.value = previousValue;
        }
    }
}

function getModelConfigById(modelId) {
    return availableModels.find(model => String(model.model_id) === String(modelId)) || null;
}

function getCurrentModelConfig() {
    const selector = document.getElementById('modelSelector');
    if (!selector) return null;
    return getModelConfigById(selector.value);
}

function showProfile() {
    if (!currentUser) {
        showAuthModal('login');
        return;
    }
    loadUsageStats();
    document.getElementById('profileModal').classList.remove('hidden');
}

async function showPlans() {
    const result = await api('/plans');
    if (result && result.success) {
        const container = document.getElementById('plansContainer');
        container.innerHTML = '';
        result.data.forEach(plan => {
            let features = [];
            try { features = plan.features ? JSON.parse(plan.features) : []; } catch (e) { features = []; }
            const isCurrentPlan = currentUser && Number(currentUser.plan_id) === Number(plan.id);
            const isPopular = plan.name === 'Lite';
            container.innerHTML += `
                <div class="plan-card bg-gray-700/50 rounded-2xl p-5 ${isPopular ? 'popular' : ''} ${isCurrentPlan ? 'ring-2 ring-blue-500' : ''}">
                    ${isPopular ? '<div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-orange-500 text-white text-xs px-3 py-1 rounded-full">最受欢迎</div>' : ''}
                    <h3 class="text-lg font-bold mb-1">${escapeHtml(plan.display_name)}</h3>
                    <div class="mb-4">
                        <span class="text-3xl font-bold">$${escapeHtml(plan.price)}</span>
                        <span class="text-gray-400 text-sm">/月</span>
                    </div>
                    <ul class="space-y-2 mb-6 text-sm text-gray-300">
                        ${features.map(f => `<li><i class="fas fa-check text-green-400 mr-2"></i>${escapeHtml(f)}</li>`).join('')}
                    </ul>
                    <button onclick="subscribePlan(${Number(plan.id)})" class="w-full py-2.5 rounded-xl transition-colors ${isCurrentPlan ? 'bg-gray-600 text-gray-400 cursor-default' : Number(plan.price) === 0 ? 'bg-gray-600 hover:bg-gray-500 text-white' : 'bg-orange-500 hover:bg-orange-600 text-white'}" ${isCurrentPlan ? 'disabled' : ''}>
                        ${isCurrentPlan ? '当前套餐' : Number(plan.price) === 0 ? '免费使用' : '立即订阅'}
                    </button>
                </div>
            `;
        });
    }
    document.getElementById('plansModal').classList.remove('hidden');
}

async function subscribePlan(planId) {
    if (!token) {
        showAuthModal('login');
        return;
    }
    const result = await api('/subscriptions', {
        method: 'POST',
        body: { plan_id: planId }
    });
    if (result && result.success) {
        alert('订阅成功！');
        closeModal('plansModal');
        initApp();
    } else {
        alert(result?.message || '订阅失败');
    }
}

async function uploadAvatar(event) {
    const file = event.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('avatar', file);
    const result = await fetch(`${API_BASE}/user/avatar`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: formData
    });
    const data = await result.json();
    if (data.success) {
        currentUser.avatar = data.data.avatar;
        updateUserInfo();
    } else {
        alert(data.message || '头像上传失败');
    }
}

async function changePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    if (!currentPassword || !newPassword) { alert('请填写所有字段'); return; }
    if (newPassword.length < 8) { alert('新密码至少8位'); return; }

    const result = await api('/user/password', {
        method: 'PUT',
        body: { current_password: currentPassword, new_password: newPassword }
    });
    if (result && result.success) {
        alert('密码修改成功');
        closeModal('passwordModal');
    } else {
        alert(result?.message || '修改失败');
    }
}

function showChangePassword() {
    closeModal('profileModal');
    document.getElementById('passwordModal').classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id)?.classList.add('hidden');
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('hidden');
}

function toggleWebSearch() {
    webSearchEnabled = !webSearchEnabled;
    const btn = document.getElementById('btnWebSearch');
    btn.classList.toggle('active', webSearchEnabled);
}

function toggleDeepThink() {
    deepThinkEnabled = !deepThinkEnabled;
    const btn = document.getElementById('btnDeepThink');
    btn.classList.toggle('active-deep', deepThinkEnabled);
}

function triggerUpload() {
    document.getElementById('fileInput').click();
}

function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    uploadedFile = file;
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('filePreview').classList.remove('hidden');
}

function clearFile() {
    uploadedFile = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('filePreview').classList.add('hidden');
}

function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 160) + 'px';
    if (typeof updateContextMeter === 'function') {
        updateContextMeter();
    }
}

function handleKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    hideAuthModal();
    initApp();
    const modelSelector = document.getElementById('modelSelector');
    if (modelSelector) {
        modelSelector.addEventListener('change', () => {
            if (typeof updateContextMeter === 'function') {
                updateContextMeter();
            }
        });
    }
});
