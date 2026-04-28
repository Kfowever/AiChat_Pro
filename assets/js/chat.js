let currentChatMessages = [];
let activeStreaming = null;
let selectedAssistantVariant = {};
let tempMessageId = -1;

function nextTempMessageId() {
    return tempMessageId--;
}

function normalizeMessage(raw) {
    return {
        id: raw?.id != null ? Number(raw.id) : nextTempMessageId(),
        role: String(raw?.role || ''),
        content: String(raw?.content || ''),
        reasoning_content: String(raw?.reasoning_content || ''),
        parent_user_message_id: raw?.parent_user_message_id != null ? Number(raw.parent_user_message_id) : null,
        variant_no: raw?.variant_no != null ? Number(raw.variant_no) : 1,
        tokens: Number(raw?.tokens || 0),
        model: raw?.model || null,
        created_at: raw?.created_at || null,
    };
}

async function loadChatList() {
    if (!token) return;
    const result = await api('/chats');
    if (result && result.success) {
        const chatList = document.getElementById('chatList');
        chatList.innerHTML = '';
        result.data.forEach(chat => {
            const isActive = Number(chat.id) === Number(currentChatId);
            chatList.innerHTML += `
                <div class="chat-item flex items-center gap-2 px-3 py-2.5 rounded-lg cursor-pointer group ${isActive ? 'active' : ''}" onclick="loadChat(${Number(chat.id)})">
                    <i class="fas fa-comment text-gray-500 shrink-0"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">${escapeHtml(chat.title)}</p>
                        <p class="text-xs text-gray-500 truncate">${escapeHtml(chat.last_message || '')}</p>
                    </div>
                    <button onclick="event.stopPropagation(); deleteChat(${Number(chat.id)})" class="text-gray-600 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity shrink-0" title="删除">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>
            `;
        });
    }
    updateChatActions();
}

async function createNewChat() {
    if (!requireChatLogin()) return;
    const model = document.getElementById('modelSelector').value;
    const result = await api('/chats', {
        method: 'POST',
        body: { model }
    });
    if (result && result.success) {
        currentChatId = result.data.id;
        selectedAssistantVariant = {};
        await loadChatList();
        showChatArea();
        clearMessages();
    } else if (result && result.message) {
        alert(result.message);
    }
}

async function loadChat(chatId, options = {}) {
    if (!requireChatLogin()) return;
    const {
        preserveSelections = false,
        focusUserMessageId = null,
        animate = true,
        preserveScroll = false
    } = options;
    currentChatId = chatId;
    const result = await api(`/chats/${chatId}`);
    if (result && result.success) {
        if (!preserveSelections) {
            selectedAssistantVariant = {};
        }
        currentChatMessages = Array.isArray(result.data.messages)
            ? result.data.messages.map(normalizeMessage)
            : [];
        showChatArea();
        renderMessages(currentChatMessages, { focusUserMessageId, animate, preserveScroll });
        document.getElementById('modelSelector').value = result.data.model;
        updateContextMeter();
        await loadChatList();
        if (window.innerWidth < 1024) toggleSidebar();
    }
    updateChatActions();
}

async function deleteChat(chatId) {
    if (!confirm('确定删除此对话？')) return;
    const result = await api(`/chats/${chatId}`, { method: 'DELETE' });
    if (result && result.success) {
        if (Number(currentChatId) === Number(chatId)) {
            currentChatId = null;
            showWelcome();
        }
        await loadChatList();
    }
}

async function renameCurrentChat() {
    if (!currentChatId) return;
    const activeTitle = document.querySelector('#chatList .chat-item.active .font-medium')?.textContent || '';
    const title = prompt('新的聊天标题', activeTitle);
    if (title === null) return;
    const trimmed = title.trim();
    if (!trimmed) return;

    const result = await api(`/chats/${currentChatId}`, {
        method: 'PUT',
        body: { title: trimmed }
    });
    if (result && result.success) {
        await loadChatList();
    } else {
        alert(result?.message || '重命名失败');
    }
}

async function exportCurrentChat(format = 'markdown') {
    if (!currentChatId) return;
    const result = await api(`/chats/${currentChatId}/export?format=${encodeURIComponent(format)}`);
    if (result && result.success) {
        const content = result.data.content || '';
        const type = result.data.format === 'json' ? 'application/json' : 'text/markdown';
        downloadText(result.data.filename || 'chat.md', content, type);
    } else {
        alert(result?.message || '导出失败');
    }
}

async function regenerateResponse() {
    const turns = buildConversationTurns(currentChatMessages).filter(turn => turn.user && Number(turn.user.id) > 0);
    if (!turns.length) return;
    const lastUserMessageId = Number(turns[turns.length - 1].user.id);
    await regenerateTurnResponse(lastUserMessageId);
}

function showWelcome() {
    document.getElementById('welcomeScreen').classList.remove('hidden');
    document.getElementById('messagesContainer').classList.add('hidden');
    updateContextMeter();
    updateChatActions();
}

function showChatArea() {
    document.getElementById('welcomeScreen').classList.add('hidden');
    document.getElementById('messagesContainer').classList.remove('hidden');
    updateContextMeter();
    updateChatActions();
}

function clearMessages() {
    document.getElementById('messagesContainer').innerHTML = '';
    currentChatMessages = [];
    selectedAssistantVariant = {};
    updateContextMeter();
}

function buildConversationTurns(messages) {
    const turns = [];
    const turnByUserId = new Map();
    let lastTurn = null;
    const normalized = (messages || []).map(normalizeMessage).sort((a, b) => Number(a.id) - Number(b.id));

    normalized.forEach((msg) => {
        if (msg.role === 'user') {
            const turn = {
                key: `u-${msg.id}`,
                user: msg,
                assistants: [],
                activeAssistantIndex: -1,
            };
            turns.push(turn);
            turnByUserId.set(Number(msg.id), turn);
            lastTurn = turn;
            return;
        }

        if (msg.role !== 'assistant') {
            return;
        }

        let parentId = Number(msg.parent_user_message_id || 0);
        if (parentId <= 0 && lastTurn?.user?.id) {
            parentId = Number(lastTurn.user.id);
        }

        let turn = parentId > 0 ? turnByUserId.get(parentId) : null;
        if (!turn) {
            turn = {
                key: `orphan-${msg.id}`,
                user: null,
                assistants: [],
                activeAssistantIndex: -1,
            };
            turns.push(turn);
        }

        const variantNo = Number(msg.variant_no || (turn.assistants.length + 1));
        turn.assistants.push({
            ...msg,
            parent_user_message_id: parentId > 0 ? parentId : null,
            variant_no: variantNo > 0 ? variantNo : 1,
        });
    });

    turns.forEach((turn, turnIndex) => {
        turn.assistants.sort((a, b) => {
            const va = Number(a.variant_no || 1);
            const vb = Number(b.variant_no || 1);
            if (va !== vb) return va - vb;
            return Number(a.id || 0) - Number(b.id || 0);
        });

        if (!turn.assistants.length) {
            turn.activeAssistantIndex = -1;
            return;
        }

        const key = turn.user ? `u-${turn.user.id}` : `orphan-${turnIndex}`;
        const selected = Number(selectedAssistantVariant[key]);
        if (Number.isFinite(selected)) {
            turn.activeAssistantIndex = Math.max(0, Math.min(turn.assistants.length - 1, selected));
        } else {
            turn.activeAssistantIndex = turn.assistants.length - 1;
            selectedAssistantVariant[key] = turn.activeAssistantIndex;
        }
    });

    return turns;
}

function renderMessages(messages, options = {}) {
    const { focusUserMessageId = null, animate = true, preserveScroll = false } = options;
    const container = document.getElementById('messagesContainer');
    const chatArea = document.getElementById('chatArea');
    const previousScrollTop = chatArea ? chatArea.scrollTop : 0;
    container.innerHTML = '';
    const turns = buildConversationTurns(messages);

    turns.forEach((turn, index) => {
        if (turn.user) {
            container.appendChild(renderUserBubble(turn.user, { animate }));
        }
        if (turn.assistants.length) {
            container.appendChild(renderAssistantBubble(turn, index, { animate }));
        }
    });

    if (!turns.length) {
        updateContextMeter();
        return;
    }

    if (preserveScroll && chatArea) {
        chatArea.scrollTop = previousScrollTop;
    } else if (focusUserMessageId) {
        const target = container.querySelector(`.assistant-turn[data-user-id="${Number(focusUserMessageId)}"]`);
        if (target) {
            target.scrollIntoView({ block: 'center' });
        } else {
            scrollToBottom();
        }
    } else {
        scrollToBottom();
    }
    updateContextMeter();
}

function renderUserBubble(userMessage, options = {}) {
    const { animate = true } = options;
    const canRollback = Number(userMessage.id) > 0;
    const row = document.createElement('div');
    row.className = `${animate ? 'fade-in ' : ''}flex justify-end`;
    row.innerHTML = `
        <div class="message-bubble-wrap message-bubble-user group max-w-[74%] md:max-w-[68%]">
            <div class="message-user px-4 py-3">
                <p class="text-sm whitespace-pre-wrap">${escapeHtml(userMessage.content)}</p>
            </div>
            <div class="message-hover-actions">
                <button type="button" class="msg-action-btn" title="复制问题" onclick="copyMessageContent(${Number(userMessage.id)}, 'user')">
                    <i class="fas fa-copy"></i>
                </button>
                ${canRollback ? `
                    <button type="button" class="msg-action-btn" title="回退并编辑" onclick="rollbackAndEdit(${Number(userMessage.id)})">
                        <i class="fas fa-pen-to-square"></i>
                    </button>
                ` : ''}
            </div>
        </div>
    `;
    return row;
}

function renderAssistantBubble(turn, turnIndex, options = {}) {
    const { animate = true } = options;
    const active = turn.assistants[turn.activeAssistantIndex] || turn.assistants[turn.assistants.length - 1];
    const currentIndex = Math.max(0, turn.activeAssistantIndex);
    const total = turn.assistants.length;
    const reasoning = String(active.reasoning_content || '').trim();
    const userMessageId = turn.user ? Number(turn.user.id) : Number(active.parent_user_message_id || 0);

    const row = document.createElement('div');
    row.className = `${animate ? 'fade-in ' : ''}flex justify-start assistant-turn`;
    row.dataset.userId = userMessageId > 0 ? String(userMessageId) : '';
    row.dataset.turnIndex = String(turnIndex);

    row.innerHTML = `
        <div class="flex gap-3 max-w-[88%]">
            <div class="w-8 h-8 rounded-full bg-purple-600 flex items-center justify-center shrink-0 mt-1">
                <i class="fas fa-robot text-xs"></i>
            </div>
            <div class="assistant-stack flex-1 min-w-0">
                <div class="message-assistant px-4 py-3">
                    ${reasoning ? `
                        <details class="reasoning-panel">
                            <summary class="reasoning-summary">深度思考</summary>
                            <div class="reasoning-panel-body">${renderMarkdown(reasoning)}</div>
                        </details>
                    ` : ''}
                    <div class="text-sm assistant-answer">${renderMarkdown(active.content)}</div>
                </div>
                <div class="message-hover-actions">
                    <button type="button" class="msg-action-btn" title="复制回复" onclick="copyMessageContent(${Number(active.id)}, 'assistant')">
                        <i class="fas fa-copy"></i>
                    </button>
                    ${userMessageId > 0 ? `
                        <button type="button" class="msg-action-btn" title="重新输出" onclick="regenerateTurnResponse(${userMessageId})">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    ` : ''}
                    ${total > 1 ? `
                        <div class="assistant-variant-pager">
                            <button type="button" class="variant-nav" ${currentIndex <= 0 ? 'disabled' : ''} onclick="switchAssistantVariant(${userMessageId}, -1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span class="variant-indicator">${currentIndex + 1}/${total}</span>
                            <button type="button" class="variant-nav" ${currentIndex >= total - 1 ? 'disabled' : ''} onclick="switchAssistantVariant(${userMessageId}, 1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>
    `;

    addCopyButtons(row);
    return row;
}

function switchAssistantVariant(userMessageId, delta) {
    const key = `u-${Number(userMessageId)}`;
    const turns = buildConversationTurns(currentChatMessages);
    const turn = turns.find(item => Number(item.user?.id) === Number(userMessageId));
    if (!turn || !turn.assistants.length) return;

    const current = Number(selectedAssistantVariant[key] ?? (turn.assistants.length - 1));
    const next = Math.max(0, Math.min(turn.assistants.length - 1, current + Number(delta || 0)));
    selectedAssistantVariant[key] = next;
    turn.activeAssistantIndex = next;

    const chatArea = document.getElementById('chatArea');
    const previousScrollTop = chatArea ? chatArea.scrollTop : 0;
    const existingRow = document.querySelector(`.assistant-turn[data-user-id="${Number(userMessageId)}"]`);
    if (existingRow) {
        existingRow.replaceWith(renderAssistantBubble(turn, turns.indexOf(turn), { animate: false }));
        if (chatArea) {
            chatArea.scrollTop = previousScrollTop;
        }
        updateContextMeter();
        return;
    }

    renderMessages(currentChatMessages, { animate: false, preserveScroll: true });
}

function appendLocalMessage(role, content, extra = {}) {
    currentChatMessages.push(normalizeMessage({
        id: nextTempMessageId(),
        role,
        content,
        ...extra,
    }));
    renderMessages(currentChatMessages);
}

function appendStreamingMessage(options = {}) {
    const { mode = 'send', targetUserMessageId = null, showReasoningPanel = false } = options;
    const container = document.getElementById('messagesContainer');
    const streamingBubbleHtml = `
        <details class="reasoning-panel ${showReasoningPanel ? '' : 'hidden'}">
            <summary class="reasoning-summary">正在深度思考 (0秒)</summary>
            <div class="reasoning-panel-body">思考中...</div>
        </details>
        <div class="text-sm assistant-answer hidden"></div>
        <div class="streaming-typing">
            <div class="typing-indicator">
                <span></span><span></span><span></span>
            </div>
        </div>
    `;

    const reusableRow = mode === 'regenerate' && targetUserMessageId
        ? container.querySelector(`.assistant-turn[data-user-id="${Number(targetUserMessageId)}"]`)
        : null;
    let div = reusableRow;
    let actionsEl = null;

    if (reusableRow) {
        const bubble = reusableRow.querySelector('.message-assistant');
        actionsEl = reusableRow.querySelector('.message-hover-actions');
        if (bubble) {
            bubble.innerHTML = streamingBubbleHtml;
            reusableRow.classList.add('streaming-row');
            actionsEl?.classList.add('hidden');
        }
    }

    if (!div) {
        div = document.createElement('div');
        div.className = 'fade-in flex justify-start streaming-row';
        div.innerHTML = `
            <div class="flex gap-3 max-w-[88%]">
                <div class="w-8 h-8 rounded-full bg-purple-600 flex items-center justify-center shrink-0 mt-1">
                    <i class="fas fa-robot text-xs"></i>
                </div>
                <div class="assistant-stack flex-1 min-w-0">
                    <div class="message-assistant px-4 py-3">
                        ${streamingBubbleHtml}
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);
    }

    const reasoningPanelEl = div.querySelector('.reasoning-panel');
    activeStreaming = {
        wrapper: div,
        reasoningPanelEl,
        reasoningSummaryEl: div.querySelector('.reasoning-summary'),
        reasoningBodyEl: div.querySelector('.reasoning-panel-body'),
        answerEl: div.querySelector('.assistant-answer'),
        typingEl: div.querySelector('.streaming-typing'),
        startedAt: Date.now(),
        reasoningFinishedAt: null,
        reasoningExpanded: false,
        reasoning: '',
        answer: '',
        hasError: false,
        timer: null,
        mode,
        showReasoningPanel,
        actionsEl,
        reusedBubble: !!reusableRow,
        targetUserMessageId: targetUserMessageId ? Number(targetUserMessageId) : null,
    };

    reasoningPanelEl.addEventListener('toggle', () => {
        if (!activeStreaming || activeStreaming.reasoningPanelEl !== reasoningPanelEl) return;
        activeStreaming.reasoningExpanded = reasoningPanelEl.open;
    });

    if (showReasoningPanel) {
        startStreamingTimer();
    }
    keepStreamingInView();
}

function updateStreamingContent(content, options = {}) {
    if (!activeStreaming) return;
    const { event = 'message' } = options;
    const safeChunk = String(content || '');

    if (event === 'reasoning') {
        if (activeStreaming.reasoningPanelEl.classList.contains('hidden')) {
            activeStreaming.reasoningPanelEl.classList.remove('hidden');
            activeStreaming.showReasoningPanel = true;
            startStreamingTimer();
        }
        activeStreaming.reasoning += safeChunk;
        activeStreaming.reasoningBodyEl.innerHTML = renderMarkdown(activeStreaming.reasoning || '思考中...', { streaming: true });
        updateReasoningSummary();
    } else if (event === 'error') {
        activeStreaming.hasError = true;
        if (!activeStreaming.reasoningFinishedAt) {
            activeStreaming.reasoningFinishedAt = Date.now();
            stopStreamingTimer();
        }
        activeStreaming.answer = safeChunk;
        activeStreaming.answerEl.classList.remove('hidden');
        activeStreaming.answerEl.innerHTML = `<div class="text-sm text-red-300 whitespace-pre-wrap">${escapeHtml(safeChunk)}</div>`;
        activeStreaming.typingEl.classList.add('hidden');
        updateReasoningSummary();
    } else {
        if (!activeStreaming.reasoningFinishedAt && activeStreaming.reasoning.trim() !== '') {
            activeStreaming.reasoningFinishedAt = Date.now();
            stopStreamingTimer();
            updateReasoningSummary();
        }
        activeStreaming.answer += safeChunk;
        activeStreaming.answerEl.classList.remove('hidden');
        activeStreaming.answerEl.innerHTML = renderMarkdown(activeStreaming.answer, { streaming: true });
        activeStreaming.typingEl.classList.add('hidden');
    }

    updateContextMeter();
    keepStreamingInView();
}

function finalizeStreaming() {
    if (!activeStreaming) return;

    stopStreamingTimer();
    const hasReasoning = activeStreaming.reasoning.trim() !== '';
    const hasAnswer = activeStreaming.answer.trim() !== '';

    if (!hasReasoning && !hasAnswer && !activeStreaming.hasError) {
        activeStreaming.typingEl.classList.add('hidden');
        activeStreaming.answerEl.classList.remove('hidden');
        activeStreaming.answerEl.innerHTML = `<div class="text-sm text-gray-400">未获取到有效回复，请重试。</div>`;
    }

    if (hasReasoning && !activeStreaming.reasoningFinishedAt) {
        activeStreaming.reasoningFinishedAt = Date.now();
        updateReasoningSummary();
    }

    if (hasAnswer && !activeStreaming.hasError) {
        if (activeStreaming.mode === 'regenerate' && activeStreaming.targetUserMessageId) {
            const nextVariant = getLocalNextVariantNo(activeStreaming.targetUserMessageId);
            currentChatMessages.push(normalizeMessage({
                id: nextTempMessageId(),
                role: 'assistant',
                content: activeStreaming.answer,
                reasoning_content: activeStreaming.reasoning,
                parent_user_message_id: activeStreaming.targetUserMessageId,
                variant_no: nextVariant,
            }));
            const key = `u-${activeStreaming.targetUserMessageId}`;
            selectedAssistantVariant[key] = Number.MAX_SAFE_INTEGER;
        } else {
            currentChatMessages.push(normalizeMessage({
                id: nextTempMessageId(),
                role: 'assistant',
                content: activeStreaming.answer,
                reasoning_content: activeStreaming.reasoning,
            }));
        }
    }

    const wasRegenerating = activeStreaming.mode === 'regenerate';
    const focusUserMessageId = wasRegenerating ? activeStreaming.targetUserMessageId : null;
    activeStreaming = null;
    renderMessages(currentChatMessages, {
        focusUserMessageId,
        animate: !wasRegenerating,
        preserveScroll: wasRegenerating
    });
}

function getLocalNextVariantNo(userMessageId) {
    const turns = buildConversationTurns(currentChatMessages);
    const turn = turns.find(item => Number(item.user?.id) === Number(userMessageId));
    if (!turn || !turn.assistants.length) {
        return 1;
    }
    const maxVariant = Math.max(...turn.assistants.map(item => Number(item.variant_no || 1)));
    return maxVariant + 1;
}

async function sendMessage() {
    const input = document.getElementById('messageInput');
    const rawContent = input.value.trim();
    if (!rawContent || isStreaming) return;
    if (!requireChatLogin()) return;

    if (!currentChatId) {
        await createNewChat();
        if (!currentChatId) return;
    }

    input.value = '';
    input.style.height = 'auto';

    showChatArea();
    appendLocalMessage('user', rawContent);
    appendStreamingMessage({ mode: 'send', showReasoningPanel: !!deepThinkEnabled });

    isStreaming = true;
    document.getElementById('sendBtn').disabled = true;
    updateChatActions();

    let content = rawContent;
    const attachment = await uploadPendingFile();
    if (attachment === false) {
        finalizeStreaming();
        isStreaming = false;
        document.getElementById('sendBtn').disabled = false;
        updateChatActions();
        return;
    }
    if (attachment) {
        content = buildAttachmentMessage(rawContent, attachment);
    }

    const ok = await streamRequest(`/chats/${currentChatId}/messages`, {
        content,
        web_search: webSearchEnabled,
        deep_think: deepThinkEnabled
    });

    finalizeStreaming();
    isStreaming = false;
    document.getElementById('sendBtn').disabled = false;
    updateChatActions();

    if (ok) {
        await loadChat(currentChatId);
        await loadUsageStats();
    } else {
        renderMessages(currentChatMessages);
    }
    await loadChatList();
}

async function regenerateTurnResponse(userMessageId) {
    if (!currentChatId || isStreaming || !Number(userMessageId)) return;

    appendStreamingMessage({
        mode: 'regenerate',
        targetUserMessageId: Number(userMessageId),
        showReasoningPanel: !!deepThinkEnabled,
    });

    isStreaming = true;
    document.getElementById('sendBtn').disabled = true;
    updateChatActions();

    const ok = await streamRequest(`/chats/${currentChatId}/messages/${Number(userMessageId)}/regenerate`, {
        web_search: webSearchEnabled,
        deep_think: deepThinkEnabled
    });

    finalizeStreaming();
    isStreaming = false;
    document.getElementById('sendBtn').disabled = false;
    updateChatActions();

    if (ok) {
        await loadChat(currentChatId, {
            preserveSelections: true,
            focusUserMessageId: Number(userMessageId),
            animate: false,
            preserveScroll: true
        });
        const key = `u-${Number(userMessageId)}`;
        const turn = buildConversationTurns(currentChatMessages).find(item => Number(item.user?.id) === Number(userMessageId));
        if (turn && turn.assistants.length) {
            selectedAssistantVariant[key] = turn.assistants.length - 1;
            renderMessages(currentChatMessages, {
                focusUserMessageId: Number(userMessageId),
                animate: false,
                preserveScroll: true
            });
        }
        await loadUsageStats();
    }
    await loadChatList();
}

async function rollbackAndEdit(userMessageId) {
    if (!currentChatId || isStreaming || !Number(userMessageId)) return;
    const ok = confirm('是否回退到发起此次提问之前？确认后将删除这次提问及其之后的所有对话。');
    if (!ok) return;

    const result = await api(`/chats/${currentChatId}/messages/${Number(userMessageId)}/rollback`, {
        method: 'POST',
    });

    if (!result || !result.success) {
        alert(result?.message || '回退失败');
        return;
    }

    await loadChat(currentChatId);
    await loadChatList();

    const input = document.getElementById('messageInput');
    const draft = String(result.data?.draft || '');
    input.value = draft;
    autoResize(input);
    input.focus();
}

function copyMessageContent(messageId, role) {
    const id = Number(messageId);
    if (!Number.isFinite(id)) return;

    if (role === 'assistant') {
        const turns = buildConversationTurns(currentChatMessages);
        for (const turn of turns) {
            if (!turn.assistants.length) continue;
            const active = turn.assistants[turn.activeAssistantIndex] || turn.assistants[turn.assistants.length - 1];
            if (Number(active.id) === id) {
                copyText(active.content || '');
                return;
            }
            const matched = turn.assistants.find(item => Number(item.id) === id);
            if (matched) {
                copyText(matched.content || '');
                return;
            }
        }
        return;
    }

    const message = currentChatMessages.find(item => Number(item.id) === id && item.role === 'user');
    if (message) {
        copyText(message.content || '');
    }
}

function copyText(text) {
    const content = String(text || '');
    if (!content) return;
    navigator.clipboard.writeText(content).catch(() => {});
}

async function streamRequest(endpoint, body) {
    let fullContent = '';
    let hasStreamError = false;

    try {
        const candidates = [getApiBase(), '/api', '/index.php/api'].filter((v, i, arr) => arr.indexOf(v) === i);
        let response = null;

        for (let i = 0; i < candidates.length; i++) {
            const base = candidates[i];
            response = await fetch(`${base}${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify(body)
            });

            if (response.status === 404 && i < candidates.length - 1) {
                continue;
            }

            setApiBase(base);
            break;
        }

        if (!response || !response.ok) {
            const data = response ? await response.json().catch(() => null) : null;
            hasStreamError = true;
            updateStreamingContent(data?.message || `请求失败 (${response ? response.status : 0})`, { event: 'error' });
            return false;
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let currentEvent = 'message';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            for (const line of lines) {
                if (line.startsWith('event: ')) {
                    currentEvent = line.substring(7).trim() || 'message';
                    continue;
                }

                if (!line.startsWith('data: ')) {
                    continue;
                }

                const jsonStr = line.substring(6);
                let data;
                try {
                    data = JSON.parse(jsonStr);
                } catch (e) {
                    currentEvent = 'message';
                    continue;
                }

                if (!data.content || data.content === '[DONE]') {
                    currentEvent = 'message';
                    continue;
                }

                if (currentEvent === 'reasoning') {
                    updateStreamingContent(data.content, { event: 'reasoning' });
                } else if (currentEvent === 'error') {
                    hasStreamError = true;
                    fullContent = data.content;
                    updateStreamingContent(fullContent, { event: 'error' });
                } else if (currentEvent === 'meta') {
                    // Reserved for metadata events; current UI does not require direct handling.
                } else {
                    fullContent += data.content;
                    updateStreamingContent(data.content, { event: 'message' });
                }

                currentEvent = 'message';
            }
        }
    } catch (error) {
        console.error('Stream error:', error);
        if (!fullContent) {
            fullContent = '抱歉，发生了错误，请重试。';
            updateStreamingContent(fullContent, { event: 'error' });
        }
        return false;
    }

    return !hasStreamError;
}

function getStreamingElapsedSeconds(startedAt, endedAt = null) {
    const end = endedAt || Date.now();
    return Math.max(0, Math.floor((end - startedAt) / 1000));
}

function updateReasoningSummary() {
    if (!activeStreaming || !activeStreaming.reasoningSummaryEl) return;
    const elapsed = getStreamingElapsedSeconds(activeStreaming.startedAt, activeStreaming.reasoningFinishedAt);
    const label = activeStreaming.reasoningFinishedAt ? '深度思考' : '正在深度思考';
    activeStreaming.reasoningSummaryEl.textContent = `${label} (${elapsed}秒)`;
}

function startStreamingTimer() {
    stopStreamingTimer();
    if (!activeStreaming) return;

    activeStreaming.timer = setInterval(() => {
        if (!activeStreaming || !activeStreaming.reasoningSummaryEl) return;
        if (activeStreaming.reasoningFinishedAt) return;
        updateReasoningSummary();
    }, 1000);
}

function stopStreamingTimer() {
    if (!activeStreaming || !activeStreaming.timer) return;
    clearInterval(activeStreaming.timer);
    activeStreaming.timer = null;
}

function estimateTokens(text) {
    return Math.ceil((text || '').length / 4);
}

function formatTokenCount(value) {
    const number = Number(value) || 0;
    return number.toLocaleString('zh-CN');
}

function getContextStats() {
    const modelConfig = typeof getCurrentModelConfig === 'function' ? getCurrentModelConfig() : null;
    const maxContext = Math.max(1, Number(modelConfig?.max_context_tokens || 4096));
    let inputTokens = 0;
    let outputTokens = 0;

    const turns = buildConversationTurns(currentChatMessages);
    turns.forEach(turn => {
        if (turn.user) {
            inputTokens += estimateTokens(turn.user.content || '');
        }
        if (turn.assistants.length) {
            const active = turn.assistants[turn.activeAssistantIndex] || turn.assistants[turn.assistants.length - 1];
            outputTokens += estimateTokens(active.content || '');
            outputTokens += estimateTokens(active.reasoning_content || '');
        }
    });

    const draft = document.getElementById('messageInput')?.value || '';
    inputTokens += estimateTokens(draft);

    if (activeStreaming) {
        if (activeStreaming.mode === 'regenerate' && activeStreaming.targetUserMessageId) {
            const turn = turns.find(item => Number(item.user?.id) === Number(activeStreaming.targetUserMessageId));
            if (turn && turn.assistants.length) {
                const active = turn.assistants[turn.activeAssistantIndex] || turn.assistants[turn.assistants.length - 1];
                outputTokens -= estimateTokens(active.content || '');
                outputTokens -= estimateTokens(active.reasoning_content || '');
                outputTokens = Math.max(0, outputTokens);
            }
        }
        outputTokens += estimateTokens(activeStreaming.answer);
        outputTokens += estimateTokens(activeStreaming.reasoning);
    }

    const totalTokens = inputTokens + outputTokens;
    const ratio = Math.min(1, totalTokens / maxContext);
    return { inputTokens, outputTokens, totalTokens, maxContext, ratio };
}

function updateContextMeter() {
    const meter = document.getElementById('contextMeter');
    const ring = document.getElementById('contextMeterRing');
    const label = document.getElementById('contextMeterLabel');
    const tooltip = document.getElementById('contextMeterTooltip');
    if (!meter || !ring || !label || !tooltip) return;

    if (!token || !currentChatId) {
        meter.classList.add('hidden');
        return;
    }

    meter.classList.remove('hidden');
    const stats = getContextStats();
    const percent = Math.round(stats.ratio * 100);
    const radius = 18;
    const circumference = 2 * Math.PI * radius;
    ring.style.strokeDasharray = `${circumference} ${circumference}`;
    ring.style.strokeDashoffset = `${circumference * (1 - stats.ratio)}`;
    label.textContent = `${percent}%`;

    meter.classList.remove('context-warn', 'context-danger');
    if (stats.ratio >= 0.95) {
        meter.classList.add('context-danger');
    } else if (stats.ratio >= 0.8) {
        meter.classList.add('context-warn');
    }

    tooltip.innerHTML = `
        <div class="context-tooltip-line"><span>输入估算</span><strong>${formatTokenCount(stats.inputTokens)}</strong></div>
        <div class="context-tooltip-line"><span>输出估算</span><strong>${formatTokenCount(stats.outputTokens)}</strong></div>
        <div class="context-tooltip-line"><span>当前上下文</span><strong>${formatTokenCount(stats.totalTokens)} / ${formatTokenCount(stats.maxContext)}</strong></div>
        <p class="context-tooltip-note">超过最大上下文后，较早的对话会被逐步遗忘。</p>
    `;
}

function clampMeterPosition(left, top, meter) {
    const width = meter.offsetWidth || 54;
    const height = meter.offsetHeight || 54;
    const maxLeft = Math.max(0, window.innerWidth - width - 8);
    const maxTop = Math.max(0, window.innerHeight - height - 8);
    return {
        left: Math.min(Math.max(8, left), maxLeft),
        top: Math.min(Math.max(8, top), maxTop),
    };
}

function applyContextMeterPosition(position) {
    const meter = document.getElementById('contextMeter');
    if (!meter || !position) return;
    const normalized = clampMeterPosition(Number(position.left || 0), Number(position.top || 0), meter);
    meter.style.left = `${normalized.left}px`;
    meter.style.top = `${normalized.top}px`;
    meter.style.right = 'auto';
    meter.style.bottom = 'auto';
}

function saveContextMeterPosition(position) {
    localStorage.setItem('aichat_context_meter_pos', JSON.stringify(position));
}

function initContextMeterDrag() {
    const meter = document.getElementById('contextMeter');
    if (!meter) return;

    const cached = localStorage.getItem('aichat_context_meter_pos');
    if (cached) {
        try {
            applyContextMeterPosition(JSON.parse(cached));
        } catch (e) {}
    }

    const drag = {
        active: false,
        offsetX: 0,
        offsetY: 0,
    };

    meter.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) return;
        if (event.target.closest('.context-meter-tooltip')) return;

        const rect = meter.getBoundingClientRect();
        drag.active = true;
        drag.offsetX = event.clientX - rect.left;
        drag.offsetY = event.clientY - rect.top;
        meter.classList.add('dragging');
        meter.setPointerCapture(event.pointerId);
        event.preventDefault();
    });

    meter.addEventListener('pointermove', (event) => {
        if (!drag.active) return;
        const next = clampMeterPosition(event.clientX - drag.offsetX, event.clientY - drag.offsetY, meter);
        applyContextMeterPosition(next);
        event.preventDefault();
    });

    const stopDrag = () => {
        if (!drag.active) return;
        drag.active = false;
        meter.classList.remove('dragging');
        const rect = meter.getBoundingClientRect();
        saveContextMeterPosition({ left: rect.left, top: rect.top });
    };

    meter.addEventListener('pointerup', stopDrag);
    meter.addEventListener('pointercancel', stopDrag);
    window.addEventListener('resize', () => {
        const rect = meter.getBoundingClientRect();
        if (meter.style.left && meter.style.top) {
            applyContextMeterPosition({ left: rect.left, top: rect.top });
        }
    });
}

async function uploadPendingFile() {
    if (!uploadedFile) return null;

    const formData = new FormData();
    formData.append('file', uploadedFile);
    formData.append('chat_id', currentChatId);

    try {
        const candidates = [getApiBase(), '/api', '/index.php/api'].filter((v, i, arr) => arr.indexOf(v) === i);
        let data = null;
        let hit = false;

        for (let i = 0; i < candidates.length; i++) {
            const base = candidates[i];
            const response = await fetch(`${base}/upload`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}` },
                body: formData
            });
            const text = await response.text();
            if (response.status === 404 && i < candidates.length - 1) {
                continue;
            }
            setApiBase(base);
            hit = true;
            try { data = text ? JSON.parse(text) : {}; } catch (e) { data = { success: false, message: text }; }
            break;
        }

        if (!hit || !data) {
            updateStreamingContent('附件上传失败', { event: 'error' });
            return false;
        }

        if (!data.success) {
            updateStreamingContent(data.message || '附件上传失败', { event: 'error' });
            return false;
        }
        clearFile();
        return data.data;
    } catch (error) {
        updateStreamingContent('附件上传失败', { event: 'error' });
        return false;
    }
}

function buildAttachmentMessage(content, attachment) {
    const lines = [
        content,
        '',
        '[Attachment]',
        `Filename: ${attachment.filename}`,
        `Type: ${attachment.filetype}`,
        `Size: ${attachment.filesize} bytes`
    ];

    if (attachment.text_preview) {
        lines.push('', 'Content preview:', attachment.text_preview);
    } else {
        lines.push('', 'The file was uploaded, but this file type has no text preview available.');
    }

    return lines.join('\n');
}

function quickAsk(question) {
    document.getElementById('messageInput').value = question;
    sendMessage();
}

function requireChatLogin() {
    if (token) return true;
    showAuthModal('login');
    return false;
}

function scrollToBottom() {
    const chatArea = document.getElementById('chatArea');
    chatArea.scrollTop = chatArea.scrollHeight;
}

function keepStreamingInView() {
    if (activeStreaming?.reusedBubble && activeStreaming.wrapper) {
        activeStreaming.wrapper.scrollIntoView({ block: 'center' });
        return;
    }
    scrollToBottom();
}

function renderMarkdown(text, options = {}) {
    const safeText = escapeHtml(text);
    if (typeof marked !== 'undefined') {
        marked.setOptions({
            highlight: function(code, lang) {
                if (typeof hljs !== 'undefined' && lang && hljs.getLanguage(lang)) {
                    return hljs.highlight(code, { language: lang }).value;
                }
                return typeof hljs !== 'undefined' ? hljs.highlightAuto(code).value : code;
            },
            breaks: true,
            gfm: true
        });
        return decorateCodeBlocks(sanitizeRenderedHtml(marked.parse(safeText)), {
            copyButton: !options.streaming,
        });
    }
    return safeText.replace(/\n/g, '<br>');
}

function decorateCodeBlocks(html, options = {}) {
    const { copyButton = true } = options;
    const template = document.createElement('template');
    template.innerHTML = html;

    template.content.querySelectorAll('pre > code').forEach((code) => {
        const pre = code.parentElement;
        if (!pre) return;

        const rawCode = code.textContent.replace(/\n$/, '');
        const language = getCodeLanguage(code);
        const highlighted = highlightCode(rawCode, language);
        const lineCount = Math.max(1, rawCode.split('\n').length);
        const lineNumbers = Array.from({ length: lineCount }, (_, index) => `<span>${index + 1}</span>`).join('');
        const displayLanguage = language || highlighted.language || 'text';

        const editor = document.createElement('div');
        editor.className = 'code-editor';
        editor.innerHTML = `
            <div class="code-editor-head">
                <span class="code-editor-lang">${escapeHtml(displayLanguage)}</span>
                ${copyButton ? `
                    <button type="button" class="code-copy-btn" title="复制代码">
                        <i class="fas fa-copy"></i>
                        <span>复制</span>
                    </button>
                ` : ''}
            </div>
            <div class="code-editor-body">
                <div class="code-line-numbers" aria-hidden="true">${lineNumbers}</div>
                <pre class="code-editor-pre"><code class="${language ? `language-${escapeHtml(language)}` : ''}"></code></pre>
            </div>
        `;
        editor.querySelector('code').innerHTML = highlighted.html || escapeHtml(rawCode);
        pre.replaceWith(editor);
    });

    return template.innerHTML;
}

function getCodeLanguage(code) {
    const className = String(code.className || '');
    const matched = className.match(/(?:^|\s)language-([a-zA-Z0-9_+#.-]+)/);
    return matched ? matched[1].toLowerCase() : '';
}

function highlightCode(code, language) {
    if (typeof hljs === 'undefined') {
        return { html: escapeHtml(code), language };
    }

    try {
        if (language && hljs.getLanguage(language)) {
            return {
                html: hljs.highlight(code, { language }).value,
                language,
            };
        }
        const result = hljs.highlightAuto(code);
        return {
            html: result.value,
            language: result.language || language || '',
        };
    } catch (e) {
        return { html: escapeHtml(code), language };
    }
}

function sanitizeRenderedHtml(html) {
    const template = document.createElement('template');
    template.innerHTML = html;
    template.content.querySelectorAll('a[href]').forEach(anchor => {
        const href = anchor.getAttribute('href') || '';
        if (!/^(https?:|mailto:|#|\/)/i.test(href)) {
            anchor.removeAttribute('href');
        } else {
            anchor.setAttribute('rel', 'noopener noreferrer');
            anchor.setAttribute('target', '_blank');
        }
    });
    template.content.querySelectorAll('[src]').forEach(node => {
        const src = node.getAttribute('src') || '';
        if (!/^(https?:|data:image\/|\/)/i.test(src)) {
            node.removeAttribute('src');
        }
    });
    template.content.querySelectorAll('*').forEach(node => {
        [...node.attributes].forEach(attr => {
            if (/^on/i.test(attr.name)) {
                node.removeAttribute(attr.name);
            }
        });
    });
    return template.innerHTML;
}

function addCopyButtons(container) {
    container.querySelectorAll('.code-copy-btn').forEach(btn => {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => {
            const editor = btn.closest('.code-editor');
            const code = editor?.querySelector('code')?.textContent || '';
            navigator.clipboard.writeText(code).then(() => {
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i><span>已复制</span>';
                setTimeout(() => { btn.innerHTML = original; }, 1600);
            });
        });
    });

    container.querySelectorAll('pre').forEach(pre => {
        if (pre.closest('.code-editor')) return;
        if (pre.querySelector('.copy-btn')) return;
        const btn = document.createElement('button');
        btn.className = 'copy-btn';
        btn.innerHTML = '<i class="fas fa-copy"></i> 复制';
        btn.onclick = () => {
            const code = pre.querySelector('code')?.textContent || pre.textContent;
            navigator.clipboard.writeText(code).then(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> 已复制';
                setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> 复制'; }, 1800);
            });
        };
        pre.style.position = 'relative';
        pre.appendChild(btn);
    });
}

function updateChatActions() {
    document.querySelectorAll('.chat-action').forEach(btn => {
        btn.disabled = !currentChatId || isStreaming;
    });
}

function downloadText(filename, content, type) {
    const blob = new Blob([content], { type: `${type};charset=utf-8` });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    initContextMeterDrag();
    updateContextMeter();
});
