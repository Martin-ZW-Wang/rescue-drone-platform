@extends('layouts.app')

@section('content')
    <style>
        .mcp-shell {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 18px;
            min-height: calc(100vh - 44px);
        }

        .mcp-sidebar,
        .chat-card {
            background:
                radial-gradient(circle at 20% 0%, rgba(59, 130, 246, .14), transparent 34%),
                rgba(15, 23, 42, .92);
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 22px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .28);
            overflow: hidden;
        }

        .mcp-sidebar {
            align-self: start;
            position: sticky;
            top: 18px;
            max-height: calc(100vh - 44px);
            overflow: auto;
        }

        .side-section {
            padding: 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
        }

        .side-title {
            margin: 0 0 10px;
            font-size: 14px;
            color: #cbd5e1;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .status-grid {
            display: grid;
            gap: 10px;
        }

        .status-pill {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border: 1px solid rgba(148, 163, 184, .2);
            border-radius: 14px;
            background: rgba(2, 6, 23, .35);
            color: #e5e7eb;
        }

        .status-pill span:first-child {
            color: #94a3b8;
        }

        .tool-list,
        .event-list {
            display: grid;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .tool-list li,
        .event-item {
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 13px;
            background: rgba(2, 6, 23, .32);
            padding: 10px 11px;
            color: #dbeafe;
            font-size: 13px;
            line-height: 1.45;
        }

        .event-meta {
            color: #93c5fd;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .chat-card {
            display: grid;
            grid-template-rows: auto 1fr auto;
            min-height: calc(100vh - 44px);
        }

        .chat-header {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
        }

        .chat-title {
            margin: 0;
            font-size: 24px;
            letter-spacing: -.02em;
        }

        .chat-subtitle {
            margin: 6px 0 0;
            color: #94a3b8;
            font-size: 14px;
        }

        .mode-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .chat-btn,
        .mode-btn,
        .prompt-chip {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 999px;
            color: #e5e7eb;
            background: rgba(15, 23, 42, .72);
            cursor: pointer;
            font-weight: 700;
            transition: transform .15s ease, border-color .15s ease, background .15s ease;
        }

        .chat-btn:hover,
        .mode-btn:hover,
        .prompt-chip:hover {
            transform: translateY(-1px);
            border-color: rgba(96, 165, 250, .75);
        }

        .chat-btn {
            padding: 9px 12px;
        }

        .mode-btn {
            padding: 9px 12px;
        }

        .mode-btn.active {
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            border-color: rgba(191, 219, 254, .8);
            color: #fff;
        }

        .chat-messages {
            overflow: auto;
            padding: 22px;
            scroll-behavior: smooth;
            background:
                linear-gradient(rgba(15, 23, 42, .35) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, .35) 1px, transparent 1px);
            background-size: 34px 34px;
        }

        .message {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr);
            gap: 12px;
            max-width: 900px;
            margin: 0 0 18px;
            animation: chat-pop .18s ease-out;
        }

        .message.user {
            grid-template-columns: minmax(0, 1fr) 38px;
            margin-left: auto;
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            font-weight: 900;
            color: #fff;
            background: linear-gradient(135deg, #334155, #0f172a);
            border: 1px solid rgba(148, 163, 184, .28);
        }

        .message.user .avatar {
            grid-column: 2;
            background: linear-gradient(135deg, #2563eb, #06b6d4);
        }

        .bubble {
            border: 1px solid rgba(148, 163, 184, .18);
            background: rgba(2, 6, 23, .58);
            border-radius: 18px;
            padding: 14px 16px;
            line-height: 1.75;
            color: #e5e7eb;
            overflow-wrap: anywhere;
        }

        .message.user .bubble {
            grid-column: 1;
            grid-row: 1;
            background: linear-gradient(135deg, rgba(37, 99, 235, .82), rgba(14, 165, 233, .72));
            color: #fff;
        }

        .bubble p {
            margin: 0 0 10px;
        }

        .bubble p:last-child {
            margin-bottom: 0;
        }

        .bubble ul,
        .bubble ol {
            margin: 8px 0 8px 22px;
            padding: 0;
        }

        .bubble code {
            background: rgba(15, 23, 42, .82);
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 7px;
            padding: 2px 5px;
            color: #bfdbfe;
        }

        .bubble pre {
            margin: 10px 0;
            padding: 12px;
            border-radius: 14px;
            background: #020617;
            border: 1px solid rgba(148, 163, 184, .18);
            overflow: auto;
        }

        .message-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .source-grid {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }

        .source-card {
            border: 1px solid rgba(96, 165, 250, .28);
            border-radius: 14px;
            background: rgba(15, 23, 42, .7);
            padding: 11px 12px;
        }

        .source-card a {
            color: #bfdbfe;
            text-decoration: none;
            font-weight: 800;
        }

        .source-card a:hover {
            text-decoration: underline;
        }

        .source-snippet {
            color: #94a3b8;
            font-size: 13px;
            line-height: 1.55;
            margin-top: 5px;
        }

        .mini-action {
            border: 0;
            background: transparent;
            color: #93c5fd;
            cursor: pointer;
            font-size: 12px;
            padding: 0;
        }

        .composer {
            border-top: 1px solid rgba(148, 163, 184, .18);
            padding: 14px 18px 18px;
            background: rgba(2, 6, 23, .38);
        }

        .prompt-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .prompt-chip {
            padding: 8px 11px;
            font-size: 13px;
        }

        .composer-box {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
            border: 1px solid rgba(148, 163, 184, .26);
            border-radius: 20px;
            background: rgba(2, 6, 23, .82);
            padding: 10px;
        }

        .composer textarea {
            width: 100%;
            min-height: 56px;
            max-height: 190px;
            resize: none;
            border: 0;
            outline: 0;
            background: transparent;
            color: #fff;
            font: inherit;
            line-height: 1.55;
            padding: 6px 8px;
        }

        .send-btn {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            border: 0;
            cursor: pointer;
            color: #fff;
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            font-size: 19px;
            font-weight: 900;
        }

        .send-btn:disabled {
            opacity: .45;
            cursor: wait;
        }

        .composer-help {
            color: #64748b;
            font-size: 12px;
            margin-top: 8px;
        }

        .typing {
            display: inline-flex;
            gap: 5px;
            align-items: center;
        }

        .typing span {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #93c5fd;
            animation: typing 1s infinite ease-in-out;
        }

        .typing span:nth-child(2) { animation-delay: .12s; }
        .typing span:nth-child(3) { animation-delay: .24s; }

        @keyframes typing {
            0%, 80%, 100% { transform: translateY(0); opacity: .35; }
            40% { transform: translateY(-5px); opacity: 1; }
        }

        @keyframes chat-pop {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 1120px) {
            .mcp-shell {
                grid-template-columns: 1fr;
            }

            .mcp-sidebar {
                position: static;
                max-height: none;
            }
        }

        @media (max-width: 720px) {
            .chat-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .mode-tabs {
                justify-content: flex-start;
            }

            .message,
            .message.user {
                grid-template-columns: 1fr;
            }

            .avatar {
                display: none;
            }

            .message.user .bubble {
                grid-column: auto;
                grid-row: auto;
            }
        }
    </style>

    <div class="mcp-shell">
        <aside class="mcp-sidebar" aria-label="MCP AI 狀態">
            <div class="side-section">
                <h2 class="side-title">系統狀態</h2>
                <div class="status-grid">
                    <div class="status-pill">
                        <span>本地模型</span>
                        <strong id="mcpModel">--</strong>
                    </div>
                    <div class="status-pill">
                        <span>無人機</span>
                        <strong id="mcpDroneState">--</strong>
                    </div>
                    <div class="status-pill">
                        <span>MCP 工具</span>
                        <strong id="mcpToolCount">--</strong>
                    </div>
                </div>
            </div>

            <div class="side-section">
                <h2 class="side-title">可用工具</h2>
                <ul id="mcpTools" class="tool-list">
                    <li>載入中...</li>
                </ul>
            </div>

            <div class="side-section">
                <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                    <h2 class="side-title" style="margin:0;">近期救援事件</h2>
                    <button id="mcpReloadEventsBtn" class="mini-action" type="button">重新整理</button>
                    <button id="mcpClearEventsBtn" class="mini-action" type="button" style="color:#fca5a5; margin-left:10px;">清除測試事件</button>
                </div>
                <div style="color:#94a3b8; font-size:12px; margin-top:8px;">本區只顯示本次啟動後的新事件；歷史事件已隱藏。</div>
                <div id="mcpDetectionNotice" class="event-item" style="display:none; margin-top:10px; border-color:rgba(245,158,11,.45); color:#fde68a;"></div>
                <ul id="mcpEventsBody" class="event-list" style="margin-top:10px;">
                    <li class="event-item">載入中...</li>
                </ul>
            </div>

            <div class="side-section">
                <button id="mcpRefreshBtn" class="chat-btn" type="button">更新 MCP 狀態</button>
            </div>
        </aside>

        <section class="chat-card" aria-label="本地 AI 對話">
            <header class="chat-header">
                <div>
                    <h1 class="chat-title">本地 AI 助手</h1>
                </div>
                <div class="mode-tabs" role="tablist" aria-label="對話模式">
                    <button id="mcpRescueModeBtn" class="mode-btn active" type="button">救援事件分析</button>
                    <button id="mcpGeneralModeBtn" class="mode-btn" type="button">本地模型問答</button>
                    <button id="mcpWebModeBtn" class="mode-btn" type="button">網路查詢</button>
                    <button id="mcpClearBtn" class="chat-btn" type="button">清除對話</button>
                </div>
            </header>

            <main id="chatMessages" class="chat-messages" aria-live="polite"></main>

            <footer class="composer">
                <div class="prompt-row">
                    <button class="prompt-chip" type="button" data-prompt="請分析最近 10 筆救援事件，列出高風險事件與建議處理順序。">分析救援風險</button>
                    <button class="prompt-chip" type="button" data-prompt="請說明目前 SEG 偵測與 Pose 輔助的差異，並建議適合的使用情境。">說明 SEG / Pose</button>
                    <button class="prompt-chip" type="button" data-prompt="請幫我整理目前系統狀態，包含無人機、事件資料與本地 AI 工具。">整理系統狀態</button>
                    <button class="prompt-chip" type="button" data-prompt="請上網查詢今天台灣無人機救援或災害應變相關資訊，整理重點並附來源。">網路查詢範例</button>
                </div>
                <div class="composer-box">
                    <textarea id="mcpQuestion" placeholder="輸入訊息，Enter 送出，Shift + Enter 換行"></textarea>
                    <button id="mcpSummarizeBtn" class="send-btn" type="button" aria-label="送出">↑</button>
                </div>
                <div id="mcpModeHint" class="composer-help">目前模式：救援事件分析，會把近期救援事件一併提供給本地模型。</div>
            </footer>
        </section>
    </div>

    <script>
        const mcpEls = {
            refreshBtn: document.getElementById('mcpRefreshBtn'),
            summarizeBtn: document.getElementById('mcpSummarizeBtn'),
            reloadEventsBtn: document.getElementById('mcpReloadEventsBtn'),
            clearEventsBtn: document.getElementById('mcpClearEventsBtn'),
            rescueModeBtn: document.getElementById('mcpRescueModeBtn'),
            generalModeBtn: document.getElementById('mcpGeneralModeBtn'),
            webModeBtn: document.getElementById('mcpWebModeBtn'),
            clearBtn: document.getElementById('mcpClearBtn'),
            model: document.getElementById('mcpModel'),
            droneState: document.getElementById('mcpDroneState'),
            toolCount: document.getElementById('mcpToolCount'),
            tools: document.getElementById('mcpTools'),
            question: document.getElementById('mcpQuestion'),
            modeHint: document.getElementById('mcpModeHint'),
            messages: document.getElementById('chatMessages'),
            detectionNotice: document.getElementById('mcpDetectionNotice'),
            eventsBody: document.getElementById('mcpEventsBody'),
        };

        let mcpMode = 'rescue';
        let isSending = false;
        let mcpSessionStartedAt = new Date().toISOString();
        let mcpLastDroneStatus = null;

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function renderMarkdown(text) {
            const escaped = escapeHtml(text || '');
            const parts = escaped.split(/```/);

            return parts.map((part, index) => {
                if (index % 2 === 1) {
                    const code = part.replace(/^\w+\n/, '');
                    return `<pre><code>${code}</code></pre>`;
                }

                return part
                    .split(/\n{2,}/)
                    .map((block) => {
                        const trimmed = block.trim();
                        if (!trimmed) return '';

                        if (/^(\*|-)\s+/m.test(trimmed)) {
                            const items = trimmed.split('\n')
                                .filter(Boolean)
                                .map((line) => `<li>${line.replace(/^(\*|-)\s+/, '')}</li>`)
                                .join('');
                            return `<ul>${items}</ul>`;
                        }

                        if (/^\d+\.\s+/m.test(trimmed)) {
                            const items = trimmed.split('\n')
                                .filter(Boolean)
                                .map((line) => `<li>${line.replace(/^\d+\.\s+/, '')}</li>`)
                                .join('');
                            return `<ol>${items}</ol>`;
                        }

                        return `<p>${trimmed.replace(/\n/g, '<br>')}</p>`;
                    })
                    .join('');
            }).join('');
        }

        async function mcpFetchJson(url, options = {}) {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(options.headers || {}),
                },
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || data.ok === false) {
                throw new Error(data.message || data.error || `HTTP ${response.status}`);
            }

            return data;
        }

        function scrollChatToBottom() {
            mcpEls.messages.scrollTop = mcpEls.messages.scrollHeight;
        }

        function addMessage(role, text, options = {}) {
            const message = document.createElement('article');
            message.className = `message ${role}`;
            const avatarText = role === 'user' ? '你' : 'AI';
            const bubbleHtml = options.typing
                ? '<div class="typing"><span></span><span></span><span></span></div>'
                : renderMarkdown(text);

            message.innerHTML = role === 'user'
                ? `
                    <div class="bubble">${bubbleHtml}</div>
                    <div class="avatar">${avatarText}</div>
                `
                : `
                    <div class="avatar">${avatarText}</div>
                    <div>
                        <div class="bubble">${bubbleHtml}</div>
                        ${options.copyable ? '<div class="message-actions"><button class="mini-action" type="button">複製回覆</button></div>' : ''}
                    </div>
                `;

            if (options.copyable) {
                message.querySelector('.mini-action')?.addEventListener('click', async () => {
                    await navigator.clipboard.writeText(text || '');
                    message.querySelector('.mini-action').textContent = '已複製';
                    setTimeout(() => {
                        const btn = message.querySelector('.mini-action');
                        if (btn) btn.textContent = '複製回覆';
                    }, 1400);
                });
            }

            mcpEls.messages.appendChild(message);
            scrollChatToBottom();
            return message;
        }

        function replaceMessage(message, role, text, options = {}) {
            message.remove();
            return addMessage(role, text, options);
        }

        function addSourcesMessage(sources) {
            if (!Array.isArray(sources) || sources.length === 0) return;

            const sourceHtml = sources.map((source) => `
                <div class="source-card">
                    <a href="${escapeHtml(source.url)}" target="_blank" rel="noopener noreferrer">
                        [${escapeHtml(source.id)}] ${escapeHtml(source.title || source.url)}
                    </a>
                    <div class="source-snippet">${escapeHtml(source.snippet || source.content || '無摘要')}</div>
                </div>
            `).join('');

            const message = document.createElement('article');
            message.className = 'message';
            message.innerHTML = `
                <div class="avatar">Ref</div>
                <div>
                    <div class="bubble">
                        <strong>來源整理</strong>
                        <div class="source-grid">${sourceHtml}</div>
                    </div>
                </div>
            `;

            mcpEls.messages.appendChild(message);
            scrollChatToBottom();
        }

        function updateDetectionNotice() {
            if (!mcpEls.detectionNotice) return;

            const segEnabled = mcpLastDroneStatus?.seg_enabled === true || mcpLastDroneStatus?.tracking === true;

            if (segEnabled) {
                mcpEls.detectionNotice.style.display = 'none';
                mcpEls.detectionNotice.textContent = '';
                return;
            }

            mcpEls.detectionNotice.style.display = 'block';
            mcpEls.detectionNotice.textContent = '目前未開啟即時偵測，事件列表只會顯示本次啟動後已產生的新事件。';
        }

        function renderEvents(events) {
            updateDetectionNotice();
            if (!events || events.length === 0) {
                mcpEls.eventsBody.innerHTML = '<li class="event-item">本次啟動後尚未收到新救援事件。</li>';
                return;
            }

            mcpEls.eventsBody.innerHTML = events.map((event) => `
                <li class="event-item">
                    <div class="event-meta">#${escapeHtml(event.id)} · ${escapeHtml(event.event_time || '--')}</div>
                    <strong>${escapeHtml(event.status || '--')}</strong>
                    <div>信心度：${escapeHtml(event.conf ?? '--')}</div>
                    <div>${escapeHtml(event.message || '')}</div>
                </li>
            `).join('');
        }

        async function loadMcpStatus() {
            mcpEls.tools.innerHTML = '<li>載入中...</li>';

            try {
                const data = await mcpFetchJson('/api/mcp/status');
                const drone = data.drone || {};
                mcpLastDroneStatus = drone;
                updateDetectionNotice();
                const connected = drone.connected === true;
                const streaming = drone.streaming === true;

                mcpEls.model.textContent = data.model || '--';
                mcpEls.droneState.textContent = connected ? (streaming ? '串流中' : '已連線') : '未連線';
                mcpEls.droneState.style.color = connected ? '#10b981' : '#f59e0b';
                mcpEls.toolCount.textContent = Array.isArray(data.tools) ? data.tools.length : 0;
                mcpEls.tools.innerHTML = (data.tools || [])
                    .map((tool) => `<li>${escapeHtml(tool)}</li>`)
                    .join('') || '<li>尚無工具</li>';

                if (data.drone_error) {
                    mcpEls.tools.innerHTML += `<li>Drone API warning: ${escapeHtml(data.drone_error)}</li>`;
                }
            } catch (error) {
                mcpLastDroneStatus = null;
                updateDetectionNotice();
                mcpEls.tools.innerHTML = `<li>MCP 狀態讀取失敗：${escapeHtml(error.message)}</li>`;
                mcpEls.droneState.textContent = '讀取失敗';
                mcpEls.droneState.style.color = '#ef4444';
            }
        }

        async function loadMcpEvents() {
            mcpEls.eventsBody.innerHTML = '<li class="event-item">載入中...</li>';

            try {
                const params = new URLSearchParams({
                    limit: '10',
                    since: mcpSessionStartedAt,
                });
                const data = await mcpFetchJson(`/api/mcp/events/recent?${params.toString()}`);
                renderEvents(data.events);
            } catch (error) {
                mcpEls.eventsBody.innerHTML = `<li class="event-item">事件讀取失敗：${escapeHtml(error.message)}</li>`;
            }
        }

        async function clearMcpEvents() {
            if (!window.confirm('確定要清除目前資料庫中的測試救援事件嗎？此動作無法復原。')) {
                return;
            }

            if (mcpEls.clearEventsBtn) {
                mcpEls.clearEventsBtn.disabled = true;
            }

            try {
                const data = await mcpFetchJson('/api/mcp/events/clear', {
                    method: 'DELETE',
                });

                mcpSessionStartedAt = new Date().toISOString();
                renderEvents([]);
                addMessage('assistant', `已清除 ${data.deleted ?? 0} 筆測試事件。之後只會顯示本次啟動後的新事件。`, { copyable: false });
            } catch (error) {
                addMessage('assistant', `清除測試事件失敗：${error.message}`, { copyable: true });
            } finally {
                if (mcpEls.clearEventsBtn) {
                    mcpEls.clearEventsBtn.disabled = false;
                }
                loadMcpEvents();
            }
        }

        async function sendMessage() {
            const question = mcpEls.question.value.trim();
            if (!question || isSending) return;

            isSending = true;
            mcpEls.summarizeBtn.disabled = true;
            addMessage('user', question);
            mcpEls.question.value = '';
            autoResizeQuestion();

            const pending = addMessage('assistant', '', { typing: true });

            try {
                const data = await mcpFetchJson('/api/mcp/summarize', {
                    method: 'POST',
                    body: JSON.stringify({
                        limit: 10,
                        mode: mcpMode,
                        question,
                        since: mcpSessionStartedAt,
                    }),
                });

                replaceMessage(pending, 'assistant', data.summary || '模型沒有回傳內容。', { copyable: true });
                addSourcesMessage(data.sources);
                renderEvents(data.events);
            } catch (error) {
                replaceMessage(pending, 'assistant', `發生錯誤：${error.message}\n\n請確認 Ollama、本地模型與 MCP API 都已啟動。`, { copyable: true });
            } finally {
                isSending = false;
                mcpEls.summarizeBtn.disabled = false;
                mcpEls.question.focus();
            }
        }

        function setMcpMode(nextMode) {
            mcpMode = nextMode;
            const rescue = nextMode === 'rescue';
            const general = nextMode === 'general';
            const web = nextMode === 'web';

            mcpEls.rescueModeBtn.classList.toggle('active', rescue);
            mcpEls.generalModeBtn.classList.toggle('active', general);
            mcpEls.webModeBtn.classList.toggle('active', web);

            if (rescue) {
                mcpEls.modeHint.textContent = '目前模式：救援事件分析，會把近期救援事件一併提供給本地模型。';
            } else if (web) {
                mcpEls.modeHint.textContent = '目前模式：網路查詢，後端會先搜尋網頁、整理來源，再交給本地模型回答。';
            } else {
                mcpEls.modeHint.textContent = '目前模式：一般本地模型問答，不會主動查詢外部即時資料。';
            }
        }

        function autoResizeQuestion() {
            mcpEls.question.style.height = 'auto';
            mcpEls.question.style.height = `${Math.min(mcpEls.question.scrollHeight, 190)}px`;
        }

        function resetChat() {
            mcpEls.messages.innerHTML = '';
            addMessage('assistant', '你好，我是本地 AI 助手。你可以問我救援事件、SEG/Pose 偵測流程，或請我整理目前系統狀態。', { copyable: false });
        }

        document.addEventListener('DOMContentLoaded', () => {
            mcpEls.refreshBtn.addEventListener('click', loadMcpStatus);
            mcpEls.reloadEventsBtn.addEventListener('click', loadMcpEvents);
            mcpEls.clearEventsBtn?.addEventListener('click', clearMcpEvents);
            mcpEls.summarizeBtn.addEventListener('click', sendMessage);
            mcpEls.rescueModeBtn.addEventListener('click', () => setMcpMode('rescue'));
            mcpEls.generalModeBtn.addEventListener('click', () => setMcpMode('general'));
            mcpEls.webModeBtn.addEventListener('click', () => setMcpMode('web'));
            mcpEls.clearBtn.addEventListener('click', resetChat);
            mcpEls.question.addEventListener('input', autoResizeQuestion);
            mcpEls.question.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendMessage();
                }
            });

            document.querySelectorAll('.prompt-chip').forEach((button) => {
                button.addEventListener('click', () => {
                    mcpEls.question.value = button.dataset.prompt || '';
                    autoResizeQuestion();
                    mcpEls.question.focus();
                });
            });

            resetChat();
            loadMcpStatus();
            loadMcpEvents();
        });
    </script>
@endsection
