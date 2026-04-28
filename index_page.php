<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AiChat Pro - AI 智能对话</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/app.css?v=20260427-7">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
</head>
<body class="bg-gray-900 text-white h-screen flex overflow-hidden">

    <aside id="sidebar" class="w-64 bg-gray-800 flex flex-col h-full shrink-0 transition-all duration-300">
        <div class="p-3">
            <button onclick="createNewChat()" class="w-full flex items-center gap-2 px-4 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                <i class="fas fa-plus"></i>
                <span>新建聊天</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-3 space-y-1" id="chatList">
        </div>

        <div class="p-3 border-t border-gray-700">
            <select id="modelSelector" class="w-full bg-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </select>
        </div>
    </aside>

    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <main class="flex-1 flex flex-col h-full min-w-0">
        <header class="h-14 bg-gray-800/80 backdrop-blur-sm border-b border-gray-700 flex items-center justify-between px-4 shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-gray-400 hover:text-white">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <button id="loginButton" onclick="showAuthModal('login')" class="flex items-center gap-2 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-right-to-bracket"></i>
                    <span>登录</span>
                </button>
                <div id="userInfo" class="hidden flex items-center gap-2 cursor-pointer hover:opacity-80" onclick="showProfile()">
                    <div id="userAvatar" class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-sm">
                        <i class="fas fa-user"></i>
                    </div>
                    <span id="userName" class="text-sm font-medium hidden sm:inline"></span>
                </div>
                <span id="quotaInfo" class="text-xs text-gray-400 hidden sm:inline"></span>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="renameCurrentChat()" class="chat-action text-gray-400 hover:text-white p-2 rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed" title="重命名聊天" disabled>
                    <i class="fas fa-pen"></i>
                </button>
                <button onclick="exportCurrentChat()" class="chat-action text-gray-400 hover:text-white p-2 rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed" title="导出聊天" disabled>
                    <i class="fas fa-download"></i>
                </button>
                <button onclick="showPlans()" class="bg-orange-500 hover:bg-orange-600 text-white text-sm px-4 py-1.5 rounded-full transition-colors font-medium">
                    <i class="fas fa-crown mr-1"></i>升级套餐
                </button>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto" id="chatArea">
            <div id="welcomeScreen" class="flex flex-col items-center justify-center h-full px-4">
                <div class="text-center max-w-2xl">
                    <div class="text-6xl mb-6">🤖</div>
                    <h1 class="text-3xl font-bold mb-4">你好，我能为你做些什么？</h1>
                    <p class="text-gray-400 mb-8">选择下方示例开始对话，或直接输入你的问题</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-lg mx-auto">
                        <button onclick="quickAsk('帮我写一篇关于人工智能的文章')" class="text-left p-4 bg-gray-800 hover:bg-gray-700 rounded-xl transition-colors border border-gray-700">
                            <i class="fas fa-pen-fancy text-blue-400 mb-2"></i>
                            <p class="text-sm text-gray-300">帮我写一篇关于人工智能的文章</p>
                        </button>
                        <button onclick="quickAsk('用Python实现一个快速排序算法')" class="text-left p-4 bg-gray-800 hover:bg-gray-700 rounded-xl transition-colors border border-gray-700">
                            <i class="fas fa-code text-green-400 mb-2"></i>
                            <p class="text-sm text-gray-300">用Python实现一个快速排序算法</p>
                        </button>
                        <button onclick="quickAsk('解释量子计算的基本原理')" class="text-left p-4 bg-gray-800 hover:bg-gray-700 rounded-xl transition-colors border border-gray-700">
                            <i class="fas fa-atom text-purple-400 mb-2"></i>
                            <p class="text-sm text-gray-300">解释量子计算的基本原理</p>
                        </button>
                        <button onclick="quickAsk('推荐5本经典科幻小说')" class="text-left p-4 bg-gray-800 hover:bg-gray-700 rounded-xl transition-colors border border-gray-700">
                            <i class="fas fa-book text-yellow-400 mb-2"></i>
                            <p class="text-sm text-gray-300">推荐5本经典科幻小说</p>
                        </button>
                    </div>
                </div>
            </div>

            <div id="messagesContainer" class="hidden max-w-5xl mx-auto px-6 py-8 space-y-8">
            </div>
        </div>

        <div class="border-t border-gray-700 bg-gray-800/80 backdrop-blur-sm p-4 shrink-0">
            <div class="max-w-5xl mx-auto">
                <div class="flex items-center gap-2 mb-2">
                    <button id="btnWebSearch" onclick="toggleWebSearch()" class="feature-btn text-gray-400 hover:text-blue-400 p-2 rounded-lg hover:bg-gray-700 transition-colors" title="联网搜索">
                        <i class="fas fa-search"></i>
                    </button>
                    <button id="btnDeepThink" onclick="toggleDeepThink()" class="feature-btn text-gray-400 hover:text-purple-400 p-2 rounded-lg hover:bg-gray-700 transition-colors" title="深度思考">
                        <i class="fas fa-brain"></i>
                    </button>
                    <button onclick="triggerUpload()" class="text-gray-400 hover:text-green-400 p-2 rounded-lg hover:bg-gray-700 transition-colors" title="上传文件">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <input type="file" id="fileInput" class="hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.md,.csv,.json,.doc,.docx" onchange="handleFileUpload(event)">
                    <div id="filePreview" class="hidden flex items-center gap-2 bg-gray-700 rounded-lg px-3 py-1.5 text-sm">
                        <i class="fas fa-file text-blue-400"></i>
                        <span id="fileName" class="text-gray-300"></span>
                        <button onclick="clearFile()" class="text-gray-400 hover:text-red-400"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <div class="flex-1 relative">
                        <textarea id="messageInput" rows="1" placeholder="输入消息..." class="w-full bg-gray-700 text-white rounded-xl px-4 py-3 pr-12 resize-none focus:outline-none focus:ring-2 focus:ring-blue-500 max-h-40" onkeydown="handleKeyDown(event)" oninput="autoResize(this)"></textarea>
                    </div>
                    <button onclick="sendMessage()" id="sendBtn" class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-xl transition-colors shrink-0" title="发送">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-2 text-center">AiChat Pro 可能会犯错，请核实重要信息。</p>
            </div>
        </div>

        <div id="contextMeter" class="context-meter hidden" aria-live="polite">
            <div class="context-meter-ring-wrap">
                <svg class="context-meter-ring" viewBox="0 0 44 44">
                    <circle class="context-meter-bg" cx="22" cy="22" r="18"></circle>
                    <circle id="contextMeterRing" class="context-meter-progress" cx="22" cy="22" r="18"></circle>
                </svg>
                <span id="contextMeterLabel" class="context-meter-label">0%</span>
            </div>
            <div id="contextMeterTooltip" class="context-meter-tooltip"></div>
        </div>
    </main>

    <div id="profileModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold">个人中心</h2>
                    <button onclick="closeModal('profileModal')" class="text-gray-400 hover:text-white"><i class="fas fa-times text-lg"></i></button>
                </div>
                <div class="text-center mb-6">
                    <div class="relative inline-block">
                        <div id="profileAvatar" class="w-20 h-20 rounded-full bg-blue-600 flex items-center justify-center text-2xl mx-auto">
                            <i class="fas fa-user"></i>
                        </div>
                        <label class="absolute bottom-0 right-0 bg-blue-600 rounded-full w-7 h-7 flex items-center justify-center cursor-pointer hover:bg-blue-700">
                            <i class="fas fa-camera text-xs"></i>
                            <input type="file" class="hidden" accept="image/*" onchange="uploadAvatar(event)">
                        </label>
                    </div>
                    <h3 id="profileName" class="text-lg font-semibold mt-3"></h3>
                    <p id="profileEmail" class="text-gray-400 text-sm"></p>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                        <p class="text-2xl font-bold text-blue-400" id="statBalance">$0.00</p>
                        <p class="text-xs text-gray-400">剩余额度</p>
                    </div>
                    <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                        <p class="text-2xl font-bold text-orange-400" id="statUsed">$0.00</p>
                        <p class="text-xs text-gray-400">本月使用</p>
                    </div>
                    <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                        <p class="text-2xl font-bold text-green-400" id="statChats">0</p>
                        <p class="text-xs text-gray-400">本月对话</p>
                    </div>
                    <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                        <p class="text-2xl font-bold text-purple-400" id="statPlan">Free</p>
                        <p class="text-xs text-gray-400">当前套餐</p>
                    </div>
                </div>
                <div class="space-y-2">
                    <button onclick="showPlans()" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2.5 rounded-xl transition-colors">
                        <i class="fas fa-crown mr-2"></i>升级套餐
                    </button>
                    <button onclick="showChangePassword()" class="w-full bg-gray-700 hover:bg-gray-600 text-white py-2.5 rounded-xl transition-colors">
                        <i class="fas fa-key mr-2"></i>修改密码
                    </button>
                    <button onclick="logout()" class="w-full bg-red-600/20 hover:bg-red-600/30 text-red-400 py-2.5 rounded-xl transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i>退出登录
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="plansModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold">选择套餐</h2>
                    <button onclick="closeModal('plansModal')" class="text-gray-400 hover:text-white"><i class="fas fa-times text-lg"></i></button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="plansContainer">
                </div>
            </div>
        </div>
    </div>

    <div id="passwordModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-2xl w-full max-w-sm">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold">修改密码</h2>
                    <button onclick="closeModal('passwordModal')" class="text-gray-400 hover:text-white"><i class="fas fa-times text-lg"></i></button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-400 mb-1 block">当前密码</label>
                        <input type="password" id="currentPassword" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="text-sm text-gray-400 mb-1 block">新密码</label>
                        <input type="password" id="newPassword" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button onclick="changePassword()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg transition-colors">确认修改</button>
                </div>
            </div>
        </div>
    </div>

    <div id="authModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-2xl w-full max-w-sm">
            <div class="p-6">
                <div class="flex justify-end -mt-2 -mr-2">
                    <button onclick="hideAuthModal()" class="w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition-colors" title="关闭">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="text-center mb-6">
                    <h1 class="text-2xl font-bold mb-1">AiChat Pro</h1>
                    <p class="text-gray-400 text-sm">AI 智能对话平台</p>
                </div>
                <div id="authForm">
                    <div id="loginForm">
                        <div class="space-y-3">
                            <input type="email" id="loginEmail" placeholder="邮箱" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <input type="password" id="loginPassword" placeholder="密码" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button onclick="login()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg transition-colors">登录</button>
                        </div>
                        <p class="text-center text-sm text-gray-400 mt-4">还没有账户？<a href="#" onclick="showRegister()" class="text-blue-400 hover:underline">注册</a></p>
                    </div>
                    <div id="registerForm" class="hidden">
                        <div class="space-y-3">
                            <input type="text" id="regUsername" placeholder="用户名" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <input type="email" id="regEmail" placeholder="邮箱" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <input type="password" id="regPassword" placeholder="密码（至少8位）" class="w-full bg-gray-700 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button onclick="register()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg transition-colors">注册</button>
                        </div>
                        <p class="text-center text-sm text-gray-400 mt-4">已有账户？<a href="#" onclick="showLogin()" class="text-blue-400 hover:underline">登录</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/js/app.js?v=20260427-6"></script>
    <script src="/assets/js/chat.js?v=20260427-7"></script>
</body>
</html>
