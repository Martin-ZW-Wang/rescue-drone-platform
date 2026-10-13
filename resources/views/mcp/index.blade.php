@extends('layouts.app')

@section('content')
    <style>
        .mcp-shell {
            display: grid;
            grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
            gap: 18px;
            height: calc(100dvh - 44px);
            min-height: 620px;
        }

        .mcp-sidebar,
        .chat-card {
            min-width: 0;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 18px;
            background: rgba(15, 23, 42, .92);
        }

        .mcp-sidebar {
            align-self: start;
            position: sticky;
            top: 18px;
            max-height: 100%;
            overflow: auto;
        }

        .side-section {
            padding: 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
        }

        .side-title {
            margin: 0 0 10px;
            color: #cbd5e1;
            font-size: 14px;
            font-weight: 900;
        }

        .status-grid,
        .tool-list,
        .event-list {
            display: grid;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .status-pill,
        .event-item,
        .tool-list li {
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 12px;
            background: rgba(2, 6, 23, .38);
            color: #dbeafe;
            padding: 10px 11px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .status-pill {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .status-pill span,
        .event-meta,
        .event-subtle {
            color: #94a3b8;
            font-size: 12px;
        }

        .tool-action {
            display: grid;
            gap: 4px;
            width: 100%;
            border: 0;
            background: transparent;
            color: inherit;
            cursor: pointer;
            padding: 0;
            text-align: left;
        }

        .tool-action strong {
            color: #dbeafe;
            font-size: 13px;
        }

        .tool-action span {
            color: var(--accent);
            font-size: 12px;
        }

        .tool-list li:hover {
            border-color: var(--accent-border);
            background: var(--accent-soft);
        }

        .event-link {
            display: inline-flex;
            margin-top: 6px;
            color: var(--accent);
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
        }

        .event-row {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        .event-chip {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 999px;
            color: #cbd5e1;
            font-size: 12px;
            padding: 3px 8px;
        }

        .event-chip.pending { color: #f7ddca; border-color: var(--accent-border); }
        .event-chip.reviewed { color: #bbf7d0; border-color: rgba(16, 185, 129, .45); }
        .event-chip.dismissed { color: #cbd5e1; border-color: rgba(148, 163, 184, .45); }

        .event-evidence-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-top: 8px;
        }

        .event-evidence-link {
            display: grid;
            gap: 5px;
            border: 1px solid var(--accent-border);
            border-radius: 10px;
            background: rgba(2, 6, 23, .42);
            color: #f7ddca;
            font-size: 12px;
            font-weight: 800;
            overflow: hidden;
            padding: 7px;
            text-decoration: none;
        }

        .event-evidence-link img {
            width: 100%;
            aspect-ratio: 4 / 3;
            border-radius: 7px;
            background: #000;
            object-fit: cover;
        }

        .chat-evidence-grid {
            display: grid;
            gap: 12px;
            margin-top: 10px;
        }

        .chat-evidence-card {
            border: 1px solid var(--accent-border);
            border-radius: 12px;
            background: rgba(33, 25, 35, .72);
            padding: 10px;
        }

        .mini-action,
        .chat-btn,
        .mode-btn,
        .prompt-chip {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 999px;
            background: rgba(33, 25, 35, .72);
            color: #e5e7eb;
            cursor: pointer;
            font-weight: 800;
        }

        .mini-action {
            border: 0;
            background: transparent;
            color: var(--accent);
            font-size: 12px;
            padding: 0;
        }

        .chat-btn,
        .mode-btn {
            min-height: 38px;
            padding: 9px 12px;
        }

        .prompt-chip {
            min-height: 36px;
            padding: 8px 11px;
            font-size: 13px;
        }

        .mode-btn.active {
            border-color: var(--accent);
            background: var(--accent-gradient);
            color: var(--accent-ink);
        }

        .chat-card {
            display: grid;
            grid-template-rows: auto 1fr auto;
            height: 100%;
        }

        .chat-header {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
        }

        .chat-title {
            margin: 0;
            font-size: 24px;
        }

        .sunny-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sun-icon {
            position: relative;
            display: inline-block;
            width: 28px;
            height: 28px;
            flex: 0 0 auto;
            border-radius: 999px;
            background: radial-gradient(circle at 36% 34%, #fff7ed 0 18%, var(--accent) 19% 58%, var(--accent-strong) 59% 100%);
            box-shadow: 0 0 0 4px rgba(252, 200, 155, .14), 0 0 18px rgba(252, 200, 155, .42);
        }

        .sun-icon::before {
            content: "";
            position: absolute;
            inset: -7px;
            z-index: -1;
            border-radius: inherit;
            background: repeating-conic-gradient(from 0deg, rgba(252, 200, 155, .95) 0 8deg, transparent 8deg 30deg);
            mask: radial-gradient(circle, transparent 0 56%, #000 57% 66%, transparent 67%);
        }

        .sun-icon.is-avatar {
            width: 20px;
            height: 20px;
            box-shadow: none;
        }

        .sun-icon.is-avatar::before {
            inset: -5px;
        }

        .chat-subtitle {
            margin: 6px 0 0;
            color: #94a3b8;
            font-size: 14px;
        }

        .mode-tabs,
        .prompt-row,
        .message-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .chat-messages {
            min-height: 0;
            overflow: auto;
            padding: 22px;
            background:
                linear-gradient(rgba(15, 23, 42, .35) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, .35) 1px, transparent 1px);
            background-size: 34px 34px;
        }

        .message {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr);
            gap: 12px;
            width: min(840px, 100%);
            margin: 0 0 18px;
        }

        .message.user {
            grid-template-columns: minmax(0, 1fr) 38px;
            margin-left: auto;
        }

        .avatar {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border: 1px solid rgba(148, 163, 184, .28);
            border-radius: 12px;
            background: linear-gradient(135deg, #4a3946, #17131a);
            color: #fff;
            font-weight: 900;
        }

        .message.user .avatar {
            grid-column: 2;
            background: var(--accent-gradient);
            color: var(--accent-ink);
        }

        .avatar.sunny-avatar {
            overflow: visible;
            background: rgba(252, 200, 155, .12);
            border-color: var(--accent-border);
        }

        .bubble {
            min-width: 0;
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 16px;
            background: rgba(18, 14, 20, .68);
            color: #e5e7eb;
            line-height: 1.75;
            padding: 14px 16px;
            overflow-wrap: anywhere;
        }

        .message.user .bubble {
            grid-column: 1;
            grid-row: 1;
            background: linear-gradient(135deg, rgba(252, 200, 155, .92), rgba(242, 164, 110, .78));
            color: var(--accent-ink);
        }

        .bubble p { margin: 0 0 10px; }
        .bubble p:last-child { margin-bottom: 0; }
        .bubble ul, .bubble ol { margin: 8px 0 8px 22px; padding: 0; }
        .bubble code {
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 7px;
            background: rgba(15, 23, 42, .82);
            color: #f7ddca;
            padding: 2px 5px;
        }

        .source-grid {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }

        .source-card {
            border: 1px solid var(--accent-border);
            border-radius: 12px;
            background: rgba(33, 25, 35, .72);
            padding: 11px 12px;
        }

        .source-card a {
            color: var(--accent);
            font-weight: 800;
            text-decoration: none;
        }

        .source-snippet {
            color: #94a3b8;
            font-size: 13px;
            line-height: 1.55;
            margin-top: 5px;
        }

        .composer {
            border-top: 1px solid rgba(148, 163, 184, .18);
            background: rgba(2, 6, 23, .38);
            padding: 14px 18px 18px;
        }

        .prompt-row { margin-bottom: 12px; }

        .composer-box {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
            border: 1px solid rgba(148, 163, 184, .26);
            border-radius: 18px;
            background: rgba(11, 7, 10, .86);
            padding: 10px;
        }

        .composer textarea {
            width: 100%;
            min-height: 56px;
            max-height: 190px;
            border: 0;
            outline: 0;
            resize: none;
            background: transparent;
            color: #fff;
            line-height: 1.55;
            padding: 6px 8px;
        }

        .send-btn {
            width: 46px;
            height: 46px;
            border: 0;
            border-radius: 14px;
            background: var(--accent-gradient);
            color: var(--accent-ink);
            cursor: pointer;
            font-size: 18px;
            font-weight: 900;
        }

        .send-btn:disabled {
            opacity: .45;
            cursor: wait;
        }

        .composer-help {
            color: #94a3b8;
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
            background: var(--accent);
            animation: typing 1s infinite ease-in-out;
        }

        .typing span:nth-child(2) { animation-delay: .12s; }
        .typing span:nth-child(3) { animation-delay: .24s; }

        @keyframes typing {
            0%, 80%, 100% { transform: translateY(0); opacity: .35; }
            40% { transform: translateY(-5px); opacity: 1; }
        }

        @media (max-width: 1120px) {
            .mcp-shell {
                grid-template-columns: 1fr;
                height: auto;
            }
            .mcp-sidebar { position: static; }
            .chat-card {
                min-height: 620px;
                height: calc(100dvh - 32px);
            }
        }

        @media (max-width: 720px) {
            .chat-header {
                align-items: flex-start;
                flex-direction: column;
            }
            .message,
            .message.user { grid-template-columns: 1fr; }
            .avatar { display: none; }
            .message.user .bubble {
                grid-column: auto;
                grid-row: auto;
            }
        }
    </style>

    <div class="mcp-shell">
        <aside class="mcp-sidebar" aria-label="Sunny assistant status">
            <div class="side-section">
                <h2 class="side-title">System Status</h2>
                <div class="status-grid">
                    <div class="status-pill">
                        <span>Local Model</span>
                        <strong id="mcpModel">--</strong>
                    </div>
                    <div class="status-pill">
                        <span>Drone</span>
                        <strong id="mcpDroneState">--</strong>
                    </div>
                    <div class="status-pill">
                        <span>MCP Tools</span>
                        <strong id="mcpToolCount">--</strong>
                    </div>
                    <div class="status-pill">
                        <span>Stored Incidents</span>
                        <strong id="mcpDbEvents">--</strong>
                    </div>
                </div>
            </div>

            <div class="side-section">
                <h2 class="side-title">Available Tools</h2>
                <ul id="mcpTools" class="tool-list">
                    <li>Loading...</li>
                </ul>
            </div>

            <div class="side-section">
                <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                    <h2 class="side-title" style="margin:0;">Recent Rescue Incidents</h2>
                    <div style="display:flex; gap:10px;">
                        <button id="mcpReloadEventsBtn" class="mini-action" type="button">Refresh</button>
                        <button id="mcpClearEventsBtn" class="mini-action" type="button" style="color:#fca5a5;">Clear Test Incidents</button>
                    </div>
                </div>
                <div style="color:#94a3b8; font-size:12px; margin-top:8px;">This panel shows new incidents generated during the current session. Open details to inspect original and AI-annotated evidence.</div>
                <div id="mcpDetectionNotice" class="event-item" style="display:none; margin-top:10px; border-color:var(--accent-border); color:#f7ddca;"></div>
                <ul id="mcpEventsBody" class="event-list" style="margin-top:10px;">
                    <li class="event-item">Loading...</li>
                </ul>
            </div>

            <div class="side-section">
                <button id="mcpRefreshBtn" class="chat-btn" type="button">Refresh MCP Status</button>
            </div>
        </aside>

        <section class="chat-card" aria-label="Sunny AI assistant">
            <header class="chat-header">
                <div>
                    <h1 class="chat-title sunny-title"><span class="sun-icon" aria-hidden="true"></span><span>Sunny</span></h1>
                    <p class="chat-subtitle">Local AI assistant for rescue incidents, system state, and cited external sources.</p>
                </div>
                <div class="mode-tabs" role="tablist" aria-label="Chat mode">
                    <button id="mcpRescueModeBtn" class="mode-btn active" type="button">Incident Analysis</button>
                    <button id="mcpGeneralModeBtn" class="mode-btn" type="button">System Context</button>
                    <button id="mcpWebModeBtn" class="mode-btn" type="button">Web Research</button>
                    <button id="mcpClearBtn" class="chat-btn" type="button">Clear Chat</button>
                </div>
            </header>

            <main id="chatMessages" class="chat-messages" aria-live="polite"></main>

            <footer class="composer">
                <div class="prompt-row">
                    <button class="prompt-chip" type="button" data-prompt="Analyze the latest 10 rescue incidents. Identify high-risk incidents, target IDs, confidence values, operator disposition, and recommended response order.">Analyze Incident Risk</button>
                    <button class="prompt-chip" type="button" data-prompt="Explain the difference between casualty detection and posture-assisted assessment, and recommend appropriate operating scenarios.">Explain Detection/Posture</button>
                    <button class="prompt-chip" type="button" data-prompt="Summarize the current system state, including drone link, incident database, local AI model, and MCP tools.">Summarize System State</button>
                    <button class="prompt-chip" type="button" data-prompt="Search current public information related to drone-assisted rescue or disaster response, summarize the key points, and cite sources.">Web Research Example</button>
                </div>
                <div class="composer-box">
                    <textarea id="mcpQuestion" placeholder="Enter a question. Press Enter to send, Shift + Enter for a new line."></textarea>
                    <button id="mcpSummarizeBtn" class="send-btn" type="button" aria-label="Send">Send</button>
                </div>
                <div id="mcpModeHint" class="composer-help">Current mode: Incident Analysis. Recent rescue incidents will be provided to the local model.</div>
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
            dbEvents: document.getElementById('mcpDbEvents'),
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

        const mcpToolPrompts = {
            get_drone_status: {
                mode: 'rescue',
                label: 'Read drone state',
                prompt: 'Summarize the current drone state, including link, stream, flight state, battery, error message, casualty detection, and posture assessment.',
            },
            get_recent_events: {
                mode: 'rescue',
                label: 'List recent incidents',
                prompt: 'List the latest 10 rescue incidents. Include incident ID, target ID, detection status, confidence, operator disposition, and operational risk.',
            },
            get_event_detail: {
                mode: 'rescue',
                label: 'Inspect incident detail',
                prompt: 'Select the most operationally significant recent incident and summarize its detail, risk, and next response step.',
            },
            summarize_recent_events: {
                mode: 'rescue',
                label: 'Summarize incidents',
                prompt: 'Summarize recent rescue incidents by risk level and provide a recommended response priority.',
            },
            local_model_chat: {
                mode: 'general',
                label: 'Local model Q&A',
                prompt: 'Explain how this system architecture operates, including Laravel, the Python drone server, MCP AI, the database, and drone-control workflow.',
            },
            local_web_search: {
                mode: 'web',
                label: 'Web research',
                prompt: 'Search for current information about drone-assisted rescue or disaster response and summarize key points with sources.',
            },
            fetch_web_page: {
                mode: 'web',
                label: 'Fetch web evidence',
                prompt: 'Find and summarize credible public sources related to UAV rescue, emergency response, or disaster management.',
            },
            answer_with_sources: {
                mode: 'web',
                label: 'Cited answer',
                prompt: 'Answer with traceable sources: what are common applications, limitations, and operational considerations for drones in rescue or disaster response?',
            },
        };

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
            const blocks = escaped.split(/\n{2,}/);
            return blocks.map((block) => {
                const trimmed = block.trim();
                if (!trimmed) return '';
                const lines = trimmed.split('\n');
                if (lines.every((line) => /^(\*|-|\d+\.)\s+/.test(line.trim()))) {
                    const items = lines.map((line) => line.trim().replace(/^(\*|-|\d+\.)\s+/, ''));
                    const tag = /^\d+\./.test(lines[0].trim()) ? 'ol' : 'ul';
                    return `<${tag}>${items.map((item) => `<li>${item}</li>`).join('')}</${tag}>`;
                }
                return `<p>${lines.join('<br>')}</p>`;
            }).join('');
        }

        async function mcpFetchJson(url, options = {}) {
            const response = await fetch(url, {
                ...options,
                headers: {
                    Accept: 'application/json',
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

        function toolConfig(tool) {
            return mcpToolPrompts[tool] || {
                mode: 'rescue',
                label: tool,
                prompt: `Use the ${tool} capability and summarize the available information in professional English.`,
            };
        }

        function toolButtonHtml(tool) {
            const config = toolConfig(tool);
            return `
                <li>
                    <button class="tool-action" type="button" data-tool="${escapeHtml(tool)}">
                        <strong>${escapeHtml(tool)}</strong>
                        <span>${escapeHtml(config.label)}</span>
                    </button>
                </li>
            `;
        }

        function addMessage(role, text, options = {}) {
            const message = document.createElement('article');
            message.className = `message ${role}`;
            const avatarHtml = role === 'user'
                ? '<div class="avatar">You</div>'
                : '<div class="avatar sunny-avatar" aria-label="Sunny"><span class="sun-icon is-avatar" aria-hidden="true"></span></div>';
            const bubbleHtml = options.typing
                ? '<div class="typing"><span></span><span></span><span></span></div>'
                : renderMarkdown(text);

            message.innerHTML = role === 'user'
                ? `<div class="bubble">${bubbleHtml}</div>${avatarHtml}`
                : `${avatarHtml}<div><div class="bubble">${bubbleHtml}</div>${options.copyable ? '<div class="message-actions"><button class="mini-action" type="button">Copy Response</button></div>' : ''}</div>`;

            if (options.copyable) {
                message.querySelector('.mini-action')?.addEventListener('click', async () => {
                    await navigator.clipboard.writeText(text || '');
                    const btn = message.querySelector('.mini-action');
                    if (!btn) return;
                    btn.textContent = 'Copied';
                    setTimeout(() => { btn.textContent = 'Copy Response'; }, 1400);
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
                    <div class="source-snippet">${escapeHtml(source.snippet || source.content || 'No excerpt available.')}</div>
                </div>
            `).join('');

            addMessage('assistant', `Reference sources:\n\n${sourceHtml}`, { copyable: false });
            const lastBubble = mcpEls.messages.lastElementChild?.querySelector('.bubble');
            if (lastBubble) lastBubble.innerHTML = `<strong>Reference Sources</strong><div class="source-grid">${sourceHtml}</div>`;
        }

        function evidencePath(path) {
            const value = String(path || '').trim();
            if (!value) return '';
            if (/^https?:\/\//i.test(value)) return value;
            return value.startsWith('/') ? value : `/${value}`;
        }

        function evidenceLinksHtml(event) {
            const original = evidencePath(event.original_image_path);
            const annotated = evidencePath(event.annotated_image_path);
            const items = [];

            if (original) {
                items.push(`
                    <a class="event-evidence-link" href="${escapeHtml(original)}" target="_blank" rel="noopener noreferrer">
                        <img src="${escapeHtml(original)}" alt="Original frame for incident ${escapeHtml(event.display_number || event.id)}">
                        <span>Original Frame</span>
                    </a>
                `);
            }

            if (annotated) {
                items.push(`
                    <a class="event-evidence-link" href="${escapeHtml(annotated)}" target="_blank" rel="noopener noreferrer">
                        <img src="${escapeHtml(annotated)}" alt="AI-annotated frame for incident ${escapeHtml(event.display_number || event.id)}">
                        <span>AI-Annotated Frame</span>
                    </a>
                `);
            }

            return items.length ? `<div class="event-evidence-row">${items.join('')}</div>` : '';
        }

        function addEvidenceMessage(events) {
            const withEvidence = (events || []).filter((event) => event.original_image_path || event.annotated_image_path).slice(0, 4);
            if (withEvidence.length === 0) return;

            const html = withEvidence.map((event) => `
                <div class="chat-evidence-card">
                    <strong>Incident #${escapeHtml(event.display_number || event.id)}</strong>
                    <div class="event-subtle">${escapeHtml(statusLabel(event.status))} / ${escapeHtml(trackLabel(event))}</div>
                    ${evidenceLinksHtml(event)}
                    <a class="event-link" href="/events/${encodeURIComponent(event.id)}">Open full incident detail</a>
                </div>
            `).join('');

            const message = document.createElement('article');
            message.className = 'message';
            message.innerHTML = `
                <div class="avatar sunny-avatar" aria-label="Sunny"><span class="sun-icon is-avatar" aria-hidden="true"></span></div>
                <div>
                    <div class="bubble">
                        <strong>Visual Evidence</strong>
                        <div class="chat-evidence-grid">${html}</div>
                    </div>
                </div>
            `;
            mcpEls.messages.appendChild(message);
            scrollChatToBottom();
        }

        function formatPercent(value) {
            if (value === null || value === undefined || value === '') return '--';
            const number = Number(value);
            if (!Number.isFinite(number)) return String(value);
            return `${Math.round(number <= 1 ? number * 100 : number)}%`;
        }

        function statusLabel(status) {
            return ({
                'Rescue Needed': 'Rescue Required',
                'Suspected Injury': 'Suspected Casualty',
                'Detected Injury': 'Casualty Detected',
                'Posture Unclear': 'Posture Unclear',
                'Pose Unclear': 'Posture Unclear',
                OK: 'No Abnormality Detected',
            })[status] || status || '--';
        }

        function reviewLabel(review) {
            return ({
                pending: 'Pending Review',
                reviewed: 'Confirmed',
                dismissed: 'Dismissed',
            })[review || 'pending'] || review || 'Pending Review';
        }

        function trackLabel(event) {
            const raw = String(event.tid ?? '').trim();
            if (!raw) return 'Unassigned';
            const cleaned = raw.replace(/[()]/g, '').replace(/\s*,\s*/g, '-').replace(/\s+/g, '-');
            return `Target #${cleaned}`;
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
            mcpEls.detectionNotice.textContent = 'Real-time detection is not currently active. The incident list only shows incidents that have already been generated.';
        }

        function renderEvents(events) {
            updateDetectionNotice();
            if (!events || events.length === 0) {
                mcpEls.eventsBody.innerHTML = '<li class="event-item">No new rescue incidents have been received during this session.</li>';
                return;
            }

            mcpEls.eventsBody.innerHTML = events.map((event) => `
                <li class="event-item">
                    <div class="event-meta">#${escapeHtml(event.display_number || event.id)} / ${escapeHtml(event.event_time || '--')}</div>
                    <strong>${escapeHtml(statusLabel(event.status))}</strong>
                    <div class="event-row">
                        <span class="event-chip">${escapeHtml(trackLabel(event))}</span>
                        <span class="event-chip">Confidence ${escapeHtml(formatPercent(event.conf))}</span>
                        <span class="event-chip ${escapeHtml(event.review_status || 'pending')}">${escapeHtml(reviewLabel(event.review_status))}</span>
                    </div>
                    <div class="event-subtle" style="margin-top:6px;">${escapeHtml(event.message || 'No incident description is available.')}</div>
                    ${evidenceLinksHtml(event)}
                    <a class="event-link" href="/events/${encodeURIComponent(event.id)}">Open incident detail</a>
                </li>
            `).join('');
        }

        async function loadMcpStatus() {
            mcpEls.tools.innerHTML = '<li>Loading...</li>';
            try {
                const data = await mcpFetchJson('/api/mcp/status');
                const drone = data.drone || {};
                mcpLastDroneStatus = drone;
                updateDetectionNotice();

                const connected = drone.connected === true;
                const streaming = drone.streaming === true;
                mcpEls.model.textContent = data.model || '--';
                mcpEls.droneState.textContent = connected ? (streaming ? 'Connected / Streaming' : 'Connected') : 'Disconnected';
                mcpEls.droneState.style.color = connected ? '#10b981' : '#8f4b57';
                mcpEls.toolCount.textContent = Array.isArray(data.tools) ? data.tools.length : 0;
                mcpEls.dbEvents.textContent = data.db?.event_count ?? '--';
                mcpEls.dbEvents.title = data.db?.database || '';
                mcpEls.tools.innerHTML = (data.tools || []).map((tool) => toolButtonHtml(tool)).join('') || '<li>No tools available</li>';

                if (data.drone_error) {
                    mcpEls.tools.innerHTML += `<li>Drone API warning: ${escapeHtml(data.drone_error)}</li>`;
                }
            } catch (error) {
                mcpLastDroneStatus = null;
                updateDetectionNotice();
                mcpEls.tools.innerHTML = `<li>MCP status retrieval failed: ${escapeHtml(error.message)}</li>`;
                mcpEls.droneState.textContent = 'Read failed';
                mcpEls.droneState.style.color = '#ef4444';
            }
        }

        async function loadMcpEvents() {
            mcpEls.eventsBody.innerHTML = '<li class="event-item">Loading...</li>';
            try {
                const params = new URLSearchParams({ limit: '10', since: mcpSessionStartedAt });
                const data = await mcpFetchJson(`/api/mcp/events/recent?${params.toString()}`);
                renderEvents(data.events);
            } catch (error) {
                mcpEls.eventsBody.innerHTML = `<li class="event-item">Incident retrieval failed: ${escapeHtml(error.message)}</li>`;
            }
        }

        async function clearMcpEvents() {
            if (!window.confirm('Clear all test rescue incidents from the current database? This action cannot be undone.')) return;
            mcpEls.clearEventsBtn.disabled = true;
            try {
                const data = await mcpFetchJson('/api/mcp/events/clear', { method: 'DELETE' });
                mcpSessionStartedAt = new Date().toISOString();
                renderEvents([]);
                addMessage('assistant', `Cleared ${data.deleted ?? 0} test incidents. Only new incidents from this session will be displayed.`);
            } catch (error) {
                addMessage('assistant', `Failed to clear test incidents: ${error.message}`, { copyable: true });
            } finally {
                mcpEls.clearEventsBtn.disabled = false;
                loadMcpEvents();
                loadMcpStatus();
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

                replaceMessage(pending, 'assistant', data.summary || 'The model returned no content.', { copyable: true });
                addSourcesMessage(data.sources);
                renderEvents(data.events);
                addEvidenceMessage(data.events);
            } catch (error) {
                replaceMessage(pending, 'assistant', `Query failed: ${error.message}\n\nConfirm that Ollama, the local model, and the MCP API are running.`, { copyable: true });
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
            mcpEls.modeHint.textContent = rescue
                ? 'Current mode: Incident Analysis. Recent rescue incidents will be provided to the local model.'
                : web
                    ? 'Current mode: Web Research. Public sources will be searched and cited when available.'
                    : 'Current mode: System Context. Use this for architecture, workflow, and operational questions.';
        }

        function autoResizeQuestion() {
            mcpEls.question.style.height = 'auto';
            mcpEls.question.style.height = `${Math.min(mcpEls.question.scrollHeight, 190)}px`;
        }

        function resetChat() {
            mcpEls.messages.innerHTML = '';
            addMessage('assistant', 'Hello. I am Sunny, the local AI assistant for this rescue-drone command center. You can ask about incidents, detection/posture assessment, system status, or cited web research.');
        }

        async function runToolPrompt(tool) {
            if (isSending) return;
            const config = toolConfig(tool);
            setMcpMode(config.mode);
            mcpEls.question.value = config.prompt;
            autoResizeQuestion();
            await sendMessage();
        }

        document.addEventListener('DOMContentLoaded', () => {
            mcpEls.refreshBtn.addEventListener('click', loadMcpStatus);
            mcpEls.reloadEventsBtn.addEventListener('click', loadMcpEvents);
            mcpEls.clearEventsBtn.addEventListener('click', clearMcpEvents);
            mcpEls.summarizeBtn.addEventListener('click', sendMessage);
            mcpEls.rescueModeBtn.addEventListener('click', () => setMcpMode('rescue'));
            mcpEls.generalModeBtn.addEventListener('click', () => setMcpMode('general'));
            mcpEls.webModeBtn.addEventListener('click', () => setMcpMode('web'));
            mcpEls.clearBtn.addEventListener('click', resetChat);
            mcpEls.tools.addEventListener('click', (event) => {
                const button = event.target.closest('.tool-action');
                if (button) runToolPrompt(button.dataset.tool || '');
            });
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
