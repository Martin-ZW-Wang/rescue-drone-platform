const CONFIG = window.DRONE_CONFIG || {};
const API_BASE = CONFIG.apiBase || '/api/drone';
const SOCKET_BASE = CONFIG.socketBase || 'http://127.0.0.1:5001';
const CSRF_TOKEN = CONFIG.csrfToken || '';

const statusText = document.getElementById('statusText');
const debugText = document.getElementById('debugText');
const streamEl = document.getElementById('stream');
const btnStart = document.getElementById('btnStart');
const btnStop = document.getElementById('btnStop');
const btnPose = document.getElementById('btnPose');
const btnReload = document.getElementById('btnReload');
const msgBox = document.getElementById('msgBox');

const connectedValue = document.getElementById('connectedValue');
const streamingValue = document.getElementById('streamingValue');
const segValue = document.getElementById('segValue');
const poseValue = document.getElementById('poseValue');
const batteryValue = document.getElementById('batteryValue');

let socket = null;
let lastObjectUrl = null;
let renderingFrame = false;
let hasAnyEvent = false;

function setStatus(msg) {
    if (statusText) statusText.textContent = msg;
}

function setValue(el, value, color = '') {
    if (!el) return;
    el.textContent = value;
    el.style.color = color || '#fff';
}

function setDebug(st) {
    if (!debugText) return;

    if (!st || typeof st !== 'object') {
        debugText.textContent = '沒有狀態資料';
        return;
    }

    const lines = [
        `error: ${st.error ?? 'none'}`,
        `battery: ${st.battery ?? 'N/A'}%`,
        `connected: ${st.connected}`,
        `streaming: ${st.streaming}`,
        `seg_enabled: ${st.seg_enabled ?? st.tracking}`,
        `pose_enabled: ${st.pose_enabled}`,
        `out_size: ${st.out_size ?? 'N/A'}`,
        `stream_fps: ${st.stream_fps ?? 'N/A'}`,
        `proc_fps: ${st.proc_fps ?? 'N/A'}`,
        `last_infer_ms: ${st.last_infer_ms ?? 'N/A'}`,
        `last_det_count: ${st.last_det_count ?? 'N/A'}`,
        `no_det_streak: ${st.no_det_streak ?? 'N/A'}`,
        `jpeg_quality: ${st.jpeg_quality ?? 'N/A'}`,
    ];

    debugText.textContent = lines.join('\n');
}

async function fetchJson(url, options = {}) {
    const defaultHeaders = {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': CSRF_TOKEN,
    };

    const res = await fetch(url, {
        ...options,
        headers: {
            ...defaultHeaders,
            ...(options.headers || {}),
        },
    });

    const data = await res.json().catch(async () => ({ raw: await res.text() }));

    if (!res.ok) {
        const err = data?.error || data?.message || data?.raw || `HTTP ${res.status}`;
        throw new Error(err);
    }

    return data;
}

function appendMessage(evt) {
    if (!msgBox) return;

    const dt = new Date((evt.ts || Date.now() / 1000) * 1000);
    const tstr = dt.toLocaleTimeString();

    if (!hasAnyEvent) {
        msgBox.innerHTML = '';
        hasAnyEvent = true;
    }

    const div = document.createElement('div');
    div.style.border = '1px solid #7f1d1d';
    div.style.background = 'rgba(127,29,29,.18)';
    div.style.borderRadius = '12px';
    div.style.padding = '10px 12px';
    div.style.marginBottom = '10px';
    div.innerHTML = `
        <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#b91c1c;color:#fff;font-size:12px;margin-right:8px;">救援事件</span>
        ${evt.message || ''}
        <span style="color:#94a3b8;font-size:12px;margin-left:8px;">${tstr}</span>
    `;

    msgBox.prepend(div);
}

function applyStatus(st) {
    const battery = st.battery ?? 'N/A';
    const connected = !!st.connected;
    const streaming = !!st.streaming;
    const segEnabled = !!(st.seg_enabled ?? st.tracking);
    const poseEnabled = !!st.pose_enabled;
    const error = st.error ?? 'none';

    setStatus(`電池: ${battery}% | connected: ${connected} | streaming: ${streaming} | SEG: ${segEnabled} | Pose: ${poseEnabled} | error: ${error}`);

    setValue(connectedValue, connected ? '正常' : '未連線', connected ? '#10b981' : '#ef4444');
    setValue(streamingValue, streaming ? '正常' : '未啟動', streaming ? '#10b981' : '#ef4444');
    setValue(segValue, segEnabled ? '偵測中' : '未開啟', segEnabled ? '#f59e0b' : '#93c5fd');
    setValue(poseValue, poseEnabled ? '已開啟' : '未開啟', poseEnabled ? '#f59e0b' : '#93c5fd');

    let batteryColor = '#93c5fd';
    if (battery !== 'N/A') {
        if (battery >= 50) batteryColor = '#10b981';
        else if (battery >= 20) batteryColor = '#f59e0b';
        else batteryColor = '#ef4444';
    }

    setValue(batteryValue, `${battery}%`, batteryColor);

    if (btnStop) btnStop.disabled = !segEnabled;
    if (btnStart) btnStart.disabled = segEnabled;
    if (btnPose) {
        btnPose.textContent = poseEnabled ? '關閉 Pose 輔助' : '開啟 Pose 輔助';
        btnPose.style.background = poseEnabled ? '#64748b' : '#f59e0b';
        btnPose.style.color = poseEnabled ? '#fff' : '#111827';
    }

    setDebug(st);
}

async function loadStatus() {
    try {
        const st = await fetchJson(`${API_BASE}/status`, { method: 'GET' });
        applyStatus(st);
    } catch (e) {
        setStatus('狀態讀取失敗，請確認 Python drone_server 是否啟動');
        if (debugText) debugText.textContent = String(e?.message ?? e);
    }
}

function connectSocket() {
    if (!window.io) return;

    if (socket) {
        try { socket.disconnect(); } catch (e) {}
        socket = null;
    }

    socket = io(SOCKET_BASE, {
        transports: ['websocket'],
        reconnection: true,
        reconnectionAttempts: 999,
        reconnectionDelay: 500,
    });

    socket.on('connect', () => {
        setStatus('WebSocket 已連線，等待影像串流');
        socket.emit('get_status');
    });

    socket.on('disconnect', () => {
        setStatus('WebSocket 已中斷');
    });

    socket.on('frame', (bin) => {
        if (!streamEl) return;
        if (renderingFrame) return;

        renderingFrame = true;

        try {
            const blob = new Blob([bin], { type: 'image/jpeg' });
            const url = URL.createObjectURL(blob);
            streamEl.src = url;

            streamEl.onload = () => {
                if (lastObjectUrl) URL.revokeObjectURL(lastObjectUrl);
                lastObjectUrl = url;
                renderingFrame = false;
            };

            streamEl.onerror = () => {
                if (lastObjectUrl) URL.revokeObjectURL(lastObjectUrl);
                lastObjectUrl = url;
                renderingFrame = false;
            };
        } catch (e) {
            console.error(e);
            renderingFrame = false;
        }
    });

    socket.on('status', (st) => {
        applyStatus(st);
    });

    socket.on('event', (evt) => {
        appendMessage(evt);
    });
}

async function startDrone() {
    if (btnStart) btnStart.disabled = true;
    setStatus('SEG 偵測啟動中...');

    try {
        const data = await fetchJson(`${API_BASE}/start`, { method: 'POST' });

        if (!data.ok) {
            throw new Error(data.error || 'START_FAILED');
        }

        setStatus(data.message || 'SEG 偵測已啟動');
        await loadStatus();
        socket?.emit('get_status');
    } catch (e) {
        if (btnStart) btnStart.disabled = false;
        setStatus('啟動失敗，請確認 Tello Wi-Fi、防火牆與 Python 服務');
        if (debugText) debugText.textContent = String(e?.message ?? e);
    }
}

async function stopDrone() {
    if (btnStop) btnStop.disabled = true;
    setStatus('停止 SEG 偵測中...');

    try {
        const data = await fetchJson(`${API_BASE}/stop`, { method: 'POST' });
        setStatus(data.message || 'SEG 偵測已停止');
        await loadStatus();
        socket?.emit('get_status');
    } catch (e) {
        if (btnStop) btnStop.disabled = false;
        setStatus('停止失敗');
        if (debugText) debugText.textContent = String(e?.message ?? e);
    }
}

async function togglePose() {
    if (btnPose) btnPose.disabled = true;

    try {
        const current = await fetchJson(`${API_BASE}/status`, { method: 'GET' });
        const nextPath = current.pose_enabled ? 'pose/stop' : 'pose/start';
        const data = await fetchJson(`${API_BASE}/${nextPath}`, { method: 'POST' });

        if (!data.ok) {
            throw new Error(data.error || 'POSE_TOGGLE_FAILED');
        }

        await loadStatus();
        socket?.emit('get_status');
    } catch (e) {
        setStatus(`Pose 輔助切換失敗：${String(e?.message ?? e)}`);
        if (debugText) debugText.textContent = String(e?.message ?? e);
    } finally {
        if (btnPose) btnPose.disabled = false;
    }
}

function reloadSocket() {
    setStatus('重新連線 WebSocket...');
    connectSocket();
}

document.addEventListener('DOMContentLoaded', async () => {
    if (!document.getElementById('stream')) return;

    btnStart?.addEventListener('click', startDrone);
    btnStop?.addEventListener('click', stopDrone);
    btnPose?.addEventListener('click', togglePose);
    btnReload?.addEventListener('click', reloadSocket);

    connectSocket();
    await loadStatus();

    setInterval(loadStatus, 5000);
});

window.addEventListener('beforeunload', () => {
    if (lastObjectUrl) URL.revokeObjectURL(lastObjectUrl);
    if (socket) {
        try { socket.disconnect(); } catch (e) {}
    }
});
