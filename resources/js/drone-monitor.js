import { io } from 'socket.io-client';

function defaultSocketBase() {
    const protocol = window.location.protocol === 'https:' ? 'https:' : 'http:';
    const host = window.location.hostname || '127.0.0.1';
    return `${protocol}//${host}:5001`;
}

const CONFIG = window.DRONE_CONFIG || {};
const API_BASE = CONFIG.apiBase || '/api/drone';
const SOCKET_BASE = CONFIG.socketBase || defaultSocketBase();
const CSRF_TOKEN = CONFIG.csrfToken || '';
const FEATURE_LABELS = {
    detection: 'Casualty detection',
    posture: 'Posture assessment',
};

const statusText = document.getElementById('statusText');
const debugText = document.getElementById('debugText');
const streamEl = document.getElementById('stream');
const btnStart = document.getElementById('btnStart');
const btnStop = document.getElementById('btnStop');
const btnPose = document.getElementById('btnPose');
const btnReload = document.getElementById('btnReload');
const btnTakeoff = document.getElementById('btnTakeoff');
const btnNudgeUp = document.getElementById('btnNudgeUp');
const btnNudgeDown = document.getElementById('btnNudgeDown');
const btnNudgeForward = document.getElementById('btnNudgeForward');
const btnNudgeBack = document.getElementById('btnNudgeBack');
const btnNudgeLeft = document.getElementById('btnNudgeLeft');
const btnNudgeRight = document.getElementById('btnNudgeRight');
const btnNudgeYawLeft = document.getElementById('btnNudgeYawLeft');
const btnNudgeYawRight = document.getElementById('btnNudgeYawRight');
const btnLand = document.getElementById('btnLand');
const btnEmergency = document.getElementById('btnEmergency');
const btnSdkReset = document.getElementById('btnSdkReset');
const msgBox = document.getElementById('msgBox');
const btnRadioScan = document.getElementById('btnRadioScan');
const btnRadioPreflight = document.getElementById('btnRadioPreflight');
const btnRadioAutoHop = document.getElementById('btnRadioAutoHop');
const radioSummary = document.getElementById('radioSummary');
const radioCurrentChannel = document.getElementById('radioCurrentChannel');
const radioRecommendedChannel = document.getElementById('radioRecommendedChannel');
const radioNetworkCount = document.getElementById('radioNetworkCount');
const radioDetails = document.getElementById('radioDetails');
const radioHopHint = document.getElementById('radioHopHint');
const radioChannelSelect = document.getElementById('radioChannelSelect');
const btnRadioChannelApply = document.getElementById('btnRadioChannelApply');
const streamLiveBadge = document.getElementById('streamLiveBadge');
const streamHostValue = document.getElementById('streamHostValue');
const streamStateValue = document.getElementById('streamStateValue');
const streamBatteryValue = document.getElementById('streamBatteryValue');
const streamFlightValue = document.getElementById('streamFlightValue');
const connectedValue = document.getElementById('connectedValue');
const streamingValue = document.getElementById('streamingValue');
const flyingValue = document.getElementById('flyingValue');
const segValue = document.getElementById('segValue');
const poseValue = document.getElementById('poseValue');
const batteryValue = document.getElementById('batteryValue');

let socket = null;
let lastObjectUrl = null;
let renderingFrame = false;
let hasAnyEvent = false;
let flightActionInProgress = false;
let lastDroneFlying = false;

function setStatus(message) {
    if (statusText) statusText.textContent = message;
}

function setValue(el, value, color = '') {
    if (!el) return;
    el.textContent = value;
    el.style.color = color || '#fff';
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            ...(options.headers || {}),
        },
    });

    const data = await response.json().catch(async () => ({ raw: await response.text() }));
    if (!response.ok) {
        throw new Error(data?.error || data?.message || data?.raw || `HTTP ${response.status}`);
    }

    return data;
}

function formatAge(seconds) {
    if (seconds === null || seconds === undefined) return 'never';
    if (seconds < 60) return `${Math.round(seconds)}s ago`;
    return `${Math.round(seconds / 60)}m ago`;
}

function normalizeRadioStatus(status) {
    return status?.status && typeof status.status === 'object'
        ? { ...status.status, ...status }
        : status;
}

function setStreamOverlay(st, connected, streaming, flying, battery) {
    if (streamLiveBadge) {
        streamLiveBadge.textContent = streaming ? 'LIVE' : 'OFFLINE';
        streamLiveBadge.classList.toggle('is-live', streaming);
    }

    if (streamHostValue) {
        streamHostValue.textContent = `Tello host ${st.tello_host ?? 'N/A'}`;
    }

    if (streamStateValue) {
        streamStateValue.textContent = connected
            ? (streaming ? 'Live video streaming' : 'Connected, awaiting stream')
            : 'Disconnected';
    }

    if (streamBatteryValue) {
        streamBatteryValue.textContent = `Battery ${battery}%`;
    }

    if (streamFlightValue) {
        streamFlightValue.textContent = flying ? 'In flight' : 'Grounded';
    }
}

function setRadioBusy(isBusy) {
    [btnRadioScan, btnRadioPreflight, btnRadioAutoHop].forEach((button) => {
        if (button) button.disabled = isBusy;
    });
    if (radioChannelSelect) radioChannelSelect.disabled = isBusy;
    if (btnRadioChannelApply) btnRadioChannelApply.disabled = isBusy;
}

function populateRadioChannelSelect(status) {
    if (!radioChannelSelect || !status || typeof status !== 'object') return;

    const selected = radioChannelSelect.value;
    const supported = status.supported_channels || [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];
    const preferred = new Set(status.preferred_channels || [1, 6, 11]);
    const congestion = status.congestion || {};
    const recommended = status.recommended_channel;
    const current = status.operating_channel;
    const target = status.target_channel;
    const networks = Array.isArray(status.networks) ? status.networks : [];
    const observed = new Map();

    networks.forEach((item) => {
        if (item?.band !== '2.4GHz' || !item.channel) return;
        const channel = Number(item.channel);
        const info = observed.get(channel) || { count: 0, maxSignal: 0 };
        info.count += 1;
        info.maxSignal = Math.max(info.maxSignal, Number(item.signal || 0));
        observed.set(channel, info);
    });

    radioChannelSelect.innerHTML = '';

    supported.forEach((channel) => {
        const ch = Number(channel);
        const option = document.createElement('option');
        const scanInfo = congestion[String(ch)] || {};
        const seen = observed.get(ch);
        const labels = [`CH ${ch}`];

        if (ch === recommended) labels.push('Recommended');
        if (ch === current) labels.push('Current');
        if (ch === target) labels.push('Target');
        if (preferred.has(ch)) labels.push('Preferred');

        const networkCount = scanInfo.network_count ?? seen?.count ?? 0;
        const maxSignal = scanInfo.max_signal ?? seen?.maxSignal ?? 0;
        const score = scanInfo.score ?? 0;
        labels.push(`${networkCount} networks`);
        labels.push(`${maxSignal}% max`);
        labels.push(`score ${score}`);

        option.value = String(ch);
        option.textContent = labels.join(' | ');
        radioChannelSelect.appendChild(option);
    });

    const nextValue = selected || String(recommended || current || '');
    if (nextValue && [...radioChannelSelect.options].some((option) => option.value === nextValue)) {
        radioChannelSelect.value = nextValue;
    }
}

function applyRadioStatus(status) {
    status = normalizeRadioStatus(status);
    if (!status || typeof status !== 'object') return;

    const recommended = status.recommended_channel ?? status.status?.recommended_channel ?? '--';
    const current = status.operating_channel ?? status.status?.operating_channel ?? '--';
    const target = status.target_channel ?? status.status?.target_channel ?? null;
    const count = status.network_count ?? status.status?.network_count ?? '--';
    const age = formatAge(status.last_scan_age_sec ?? status.status?.last_scan_age_sec);
    const mode = status.hop_mode ?? status.status?.hop_mode ?? 'monitor';
    const lastError = status.last_error ?? status.status?.last_error ?? null;

    setValue(radioCurrentChannel, current);
    setValue(radioRecommendedChannel, recommended, recommended === current ? '#047857' : '#8f4b57');
    setValue(radioNetworkCount, count);
    populateRadioChannelSelect(status);

    if (radioSummary) {
        radioSummary.textContent = lastError
            ? `Radio warning: ${lastError}`
            : `Last scan: ${age} | mode: ${mode}${target ? ` | target: ${target}` : ''}`;
    }

    if (radioHopHint) {
        if (mode === 'openwrt-applied') {
            radioHopHint.textContent = `Router channel applied: CH ${current}`;
        } else if (mode === 'pending-router-apply' && target) {
            radioHopHint.textContent = `Target selected: CH ${target}. Apply this channel in the router 2.4 GHz settings to complete the hop.`;
        } else {
            radioHopHint.textContent = `Choose a 2.4 GHz control channel. Recommended: CH ${recommended}.`;
        }
    }

    if (radioDetails) {
        const congestion = status.congestion ?? status.status?.congestion ?? {};
        const openwrt = status.openwrt ?? status.status?.openwrt ?? {};
        const issues = status.issues ?? [];
        const warning = status.warning ? [`warning: ${status.warning}`] : [];
        const openwrtLines = Object.keys(openwrt).length
            ? [`openwrt: enabled=${openwrt.enabled} configured=${openwrt.configured} host=${openwrt.host ?? 'N/A'} radio=${openwrt.radio ?? 'N/A'}`]
            : [];
        const issueLines = issues.map((item) => `${item.severity}: ${item.code} channel=${item.channel ?? 'N/A'} score=${item.score ?? 'N/A'}`);
        const congestionLines = Object.values(congestion).map((item) => (
            `ch${item.channel}: networks=${item.network_count} max_signal=${item.max_signal}% score=${item.score}`
        ));

        radioDetails.textContent = [...warning, ...openwrtLines, ...issueLines, ...congestionLines].join('\n') || 'No radio data';
    }
}

async function loadRadioStatus() {
    if (!radioSummary) return;

    try {
        const status = await fetchJson(`${API_BASE}/radio/status`, { method: 'GET' });
        applyRadioStatus(status);
    } catch (error) {
        radioSummary.textContent = `Radio status failed: ${String(error?.message ?? error)}`;
    }
}

async function runRadioAction(path, label, payload = null) {
    setRadioBusy(true);
    if (radioSummary) radioSummary.textContent = `${label}...`;

    try {
        const options = { method: 'POST' };
        if (payload) {
            options.headers = { 'Content-Type': 'application/json' };
            options.body = JSON.stringify(payload);
        }

        const data = await fetchJson(`${API_BASE}/${path}`, options);
        applyRadioStatus(data.status || data);
        if (radioSummary) radioSummary.textContent = data.message || `${label} complete`;
        await loadStatus();
    } catch (error) {
        if (radioSummary) radioSummary.textContent = `${label} failed: ${String(error?.message ?? error)}`;
        if (radioDetails) radioDetails.textContent = String(error?.message ?? error);
    } finally {
        setRadioBusy(false);
    }
}

async function runSelectedRadioChannel() {
    const channel = Number(radioChannelSelect?.value);
    if (!channel) {
        if (radioSummary) radioSummary.textContent = 'Choose a channel first';
        return;
    }

    await runRadioAction('radio/channel', `Switching to CH ${channel}`, { channel });
}

function explainDroneError(error) {
    if (!error || error === 'none') return 'none';
    const value = String(error);

    if (value.startsWith('PREFLIGHT_TELLO_TOO_HOT')) {
        return 'Tello internal temperature is too high. Power down and allow the aircraft to cool before takeoff.';
    }

    if (value.startsWith('PREFLIGHT_BATTERY_LOW')) {
        return 'Tello battery is below the safe takeoff threshold. Charge the battery above 30% before flight.';
    }

    if (value.startsWith('TAKEOFF_UNVERIFIED')) {
        return 'Tello acknowledged takeoff, but airborne state was not verified. Check propellers, downward sensors, and takeoff behavior in the official app.';
    }

    return value;
}

function setDebug(st) {
    if (!debugText) return;

    if (!st || typeof st !== 'object') {
        debugText.textContent = 'No status data';
        return;
    }

    const lines = [
        `error: ${st.error ?? 'none'}`,
        `battery: ${st.battery ?? 'N/A'}%`,
        `tello_host: ${st.tello_host ?? 'N/A'}`,
        `socket_base: ${SOCKET_BASE}`,
        `connected: ${st.connected}`,
        `streaming: ${st.streaming}`,
        `flying: ${st.flying}`,
        `height_cm: ${st.tello_state?.h ?? 'N/A'}`,
        `tof_cm: ${st.tello_state?.tof ?? 'N/A'}`,
        `pitch: ${st.tello_state?.pitch ?? 'N/A'}`,
        `roll: ${st.tello_state?.roll ?? 'N/A'}`,
        `yaw: ${st.tello_state?.yaw ?? 'N/A'}`,
        `temp: ${st.tello_state?.templ ?? 'N/A'}-${st.tello_state?.temph ?? 'N/A'}C`,
        `motors_time: ${st.tello_state?.time ?? 'N/A'}`,
        `${FEATURE_LABELS.detection}: ${st.seg_enabled ?? st.tracking}`,
        `${FEATURE_LABELS.posture}: ${st.pose_enabled}`,
        `out_size: ${st.out_size ?? 'N/A'}`,
        `stream_fps: ${st.stream_fps ?? 'N/A'}`,
        `proc_fps: ${st.proc_fps ?? 'N/A'}`,
        `last_infer_ms: ${st.last_infer_ms ?? 'N/A'}`,
        `last_det_count: ${st.last_det_count ?? 'N/A'}`,
        `no_det_streak: ${st.no_det_streak ?? 'N/A'}`,
        `jpeg_quality: ${st.jpeg_quality ?? 'N/A'}`,
        `worker_threads: ${st.worker_threads ?? 'N/A'}`,
        `worker_generation: ${st.worker_generation ?? 'N/A'}`,
    ];

    debugText.textContent = lines.join('\n');
}

function appendMessage(evt) {
    if (!msgBox) return;

    const time = new Date((evt.ts || Date.now() / 1000) * 1000).toLocaleTimeString();

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
        <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#b91c1c;color:#fff;font-size:12px;margin-right:8px;">Rescue Incident</span>
        ${evt.message || ''}
        <span style="color:#94a3b8;font-size:12px;margin-left:8px;">${time}</span>
    `;

    msgBox.prepend(div);
}

function applyStatus(st) {
    const battery = st.battery ?? 'N/A';
    const connected = !!st.connected;
    const streaming = !!st.streaming;
    const flying = !!st.flying;
    lastDroneFlying = flying;
    const segEnabled = !!(st.seg_enabled ?? st.tracking);
    const poseEnabled = !!st.pose_enabled;
    const error = explainDroneError(st.error ?? 'none');

    setStatus(`Battery: ${battery}% | connected: ${connected} | streaming: ${streaming} | flying: ${flying} | ${FEATURE_LABELS.detection}: ${segEnabled} | ${FEATURE_LABELS.posture}: ${poseEnabled} | error: ${error}`);

    setValue(connectedValue, connected ? 'Operational' : 'Disconnected', connected ? '#047857' : '#dc2626');
    setValue(streamingValue, streaming ? 'Operational' : 'Inactive', streaming ? '#047857' : '#dc2626');
    setValue(flyingValue, flying ? 'In Flight' : 'Grounded', flying ? '#8f4b57' : '#63757d');
    setValue(segValue, segEnabled ? 'Running' : 'Inactive', segEnabled ? '#8f4b57' : '#63757d');
    setValue(poseValue, poseEnabled ? 'Enabled' : 'Disabled', poseEnabled ? '#8f4b57' : '#63757d');

    let batteryColor = '#63757d';
    if (battery !== 'N/A') {
        if (battery >= 50) batteryColor = '#047857';
        else if (battery >= 20) batteryColor = '#8f4b57';
        else batteryColor = '#dc2626';
    }

    setValue(batteryValue, `${battery}%`, batteryColor);
    setStreamOverlay(st, connected, streaming, flying, battery);
    applyRadioStatus(st.command_center?.radio);

    if (btnStop) btnStop.disabled = !segEnabled;
    if (btnStart) btnStart.disabled = segEnabled;
    if (btnTakeoff) btnTakeoff.disabled = !connected || flying;
    [btnNudgeUp, btnNudgeDown, btnNudgeForward, btnNudgeBack, btnNudgeLeft, btnNudgeRight, btnNudgeYawLeft, btnNudgeYawRight].forEach((button) => {
        if (button) button.disabled = !flying;
    });
    if (btnLand) btnLand.disabled = !flying;
    if (btnEmergency) btnEmergency.disabled = false;
    if (btnSdkReset) btnSdkReset.disabled = flying;
    if (btnPose) {
        btnPose.textContent = poseEnabled ? `Disable ${FEATURE_LABELS.posture}` : `Enable ${FEATURE_LABELS.posture}`;
        btnPose.style.background = poseEnabled ? '#cbd6d6' : '#d9857f';
        btnPose.style.color = poseEnabled ? '#21313a' : '#271816';
    }

    setDebug(st);
}

async function loadStatus() {
    try {
        const status = await fetchJson(`${API_BASE}/status`, { method: 'GET' });
        applyStatus(status);
    } catch (error) {
        setStatus('Status retrieval failed. Confirm that the Python drone server is running.');
        if (debugText) debugText.textContent = String(error?.message ?? error);
    }
}

function connectSocket() {
    if (socket) {
        try { socket.disconnect(); } catch (error) {}
        socket = null;
    }

    socket = io(SOCKET_BASE, {
        reconnection: true,
        reconnectionAttempts: 999,
        reconnectionDelay: 500,
    });

    socket.on('connect', () => {
        setStatus('WebSocket connected. The aircraft can take off after Tello link confirmation.');
        socket.emit('get_status');
    });

    socket.on('disconnect', () => {
        setStatus('WebSocket disconnected.');
    });

    socket.on('frame', (bin) => {
        if (!streamEl || renderingFrame) return;
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
        } catch (error) {
            console.error(error);
            renderingFrame = false;
        }
    });

    socket.on('status', (status) => applyStatus(status));
    socket.on('event', (event) => appendMessage(event));
}

async function startDrone() {
    if (btnStart) btnStart.disabled = true;
    setStatus(`${FEATURE_LABELS.detection} starting...`);

    try {
        const data = await fetchJson(`${API_BASE}/start`, { method: 'POST' });
        if (!data.ok) throw new Error(data.error || 'START_FAILED');
        setStatus(data.message || `${FEATURE_LABELS.detection} started.`);
        await loadStatus();
        socket?.emit('get_status');
    } catch (error) {
        if (btnStart) btnStart.disabled = false;
        setStatus('Startup failed. Check Tello Wi-Fi, firewall rules, and the Python service.');
        if (debugText) debugText.textContent = String(error?.message ?? error);
    }
}

async function stopDrone() {
    if (btnStop) btnStop.disabled = true;
    setStatus(`Stopping ${FEATURE_LABELS.detection}...`);

    try {
        const data = await fetchJson(`${API_BASE}/stop`, { method: 'POST' });
        setStatus(data.message || `${FEATURE_LABELS.detection} stopped.`);
        await loadStatus();
        socket?.emit('get_status');
    } catch (error) {
        if (btnStop) btnStop.disabled = false;
        setStatus('Stop command failed.');
        if (debugText) debugText.textContent = String(error?.message ?? error);
    }
}

async function runFlightAction(path, busyMessage, fallbackMessage) {
    if (flightActionInProgress) {
        throw new Error('A flight-control command is already in progress. Please wait.');
    }

    flightActionInProgress = true;
    setStatus(busyMessage);

    try {
        const data = await fetchJson(`${API_BASE}/${path}`, { method: 'POST' });
        if (!data.ok) throw new Error(data.error || data.message || fallbackMessage);
        applyStatus(data.status || data);
        socket?.emit('get_status');
        return data;
    } finally {
        flightActionInProgress = false;
    }
}

async function takeoffDrone() {
    if (btnTakeoff) btnTakeoff.disabled = true;

    try {
        const data = await runFlightAction('takeoff', 'Taking off. Keep the surrounding area clear...', 'TAKEOFF_FAILED');
        setStatus(data.message || 'Takeoff complete. Aircraft is holding position.');
        await loadStatus();
    } catch (error) {
        setStatus(`Takeoff failed: ${String(error?.message ?? error)}`);
        if (debugText) debugText.textContent = String(error?.message ?? error);
        await loadStatus();
    }
}

async function landDrone() {
    if (btnLand) btnLand.disabled = true;

    try {
        const data = await runFlightAction('land', 'Landing...', 'LAND_FAILED');
        setStatus(data.message || 'Landing complete.');
        await loadStatus();
    } catch (error) {
        setStatus(`Landing failed: ${String(error?.message ?? error)}`);
        if (debugText) debugText.textContent = String(error?.message ?? error);
        await loadStatus();
    }
}

async function nudgeVertical(path, label) {
    const nudgeButtons = [btnNudgeUp, btnNudgeDown, btnNudgeForward, btnNudgeBack, btnNudgeLeft, btnNudgeRight, btnNudgeYawLeft, btnNudgeYawRight];
    nudgeButtons.forEach((button) => {
        if (button) button.disabled = true;
    });

    try {
        const data = await runFlightAction(path, `${label}...`, 'NUDGE_FAILED');
        setStatus(data.message || `${label} complete`);
        await loadStatus();
    } catch (error) {
        setStatus(`${label} failed: ${String(error?.message ?? error)}`);
        if (debugText) debugText.textContent = String(error?.message ?? error);
        await loadStatus();
    } finally {
        nudgeButtons.forEach((button) => {
            if (button) button.disabled = !lastDroneFlying;
        });
    }
}

async function emergencyStopDrone() {
    if (!window.confirm('Send the emergency stop command? This immediately stops the Tello motors.')) {
        return;
    }

    try {
        const data = await runFlightAction('emergency', 'Sending emergency stop command...', 'EMERGENCY_FAILED');
        setStatus(data.message || 'Emergency stop command sent.');
        await loadStatus();
    } catch (error) {
        setStatus(`Emergency stop failed: ${String(error?.message ?? error)}`);
        if (debugText) debugText.textContent = String(error?.message ?? error);
        await loadStatus();
    }
}

async function resetSdk() {
    if (btnSdkReset) btnSdkReset.disabled = true;

    try {
        const data = await runFlightAction('sdk/reset', 'Resetting Tello SDK link...', 'SDK_RESET_FAILED');
        setStatus(data.message || 'SDK link reset.');
        await loadStatus();
    } catch (error) {
        setStatus(`SDK reset failed: ${String(error?.message ?? error)}`);
        if (debugText) debugText.textContent = String(error?.message ?? error);
        await loadStatus();
    }
}

async function togglePose() {
    if (btnPose) btnPose.disabled = true;

    try {
        const current = await fetchJson(`${API_BASE}/status`, { method: 'GET' });
        const nextPath = current.pose_enabled ? 'pose/stop' : 'pose/start';
        const data = await fetchJson(`${API_BASE}/${nextPath}`, { method: 'POST' });
        if (!data.ok) throw new Error(data.error || 'POSE_TOGGLE_FAILED');
        await loadStatus();
        socket?.emit('get_status');
    } catch (error) {
        setStatus(`${FEATURE_LABELS.posture} toggle failed: ${String(error?.message ?? error)}`);
        if (debugText) debugText.textContent = String(error?.message ?? error);
    } finally {
        if (btnPose) btnPose.disabled = false;
    }
}

function reloadSocket() {
    setStatus('Reconnecting WebSocket...');
    connectSocket();
}

document.addEventListener('DOMContentLoaded', async () => {
    if (!document.getElementById('stream')) return;

    btnStart?.addEventListener('click', startDrone);
    btnStop?.addEventListener('click', stopDrone);
    btnPose?.addEventListener('click', togglePose);
    btnReload?.addEventListener('click', reloadSocket);
    btnTakeoff?.addEventListener('click', takeoffDrone);
    btnNudgeUp?.addEventListener('click', () => nudgeVertical('nudge/up', 'Nudge up'));
    btnNudgeDown?.addEventListener('click', () => nudgeVertical('nudge/down', 'Nudge down'));
    btnNudgeForward?.addEventListener('click', () => nudgeVertical('nudge/forward', 'Nudge forward'));
    btnNudgeBack?.addEventListener('click', () => nudgeVertical('nudge/back', 'Nudge backward'));
    btnNudgeLeft?.addEventListener('click', () => nudgeVertical('nudge/left', 'Nudge left'));
    btnNudgeRight?.addEventListener('click', () => nudgeVertical('nudge/right', 'Nudge right'));
    btnNudgeYawLeft?.addEventListener('click', () => nudgeVertical('nudge/yaw-left', 'Yaw left'));
    btnNudgeYawRight?.addEventListener('click', () => nudgeVertical('nudge/yaw-right', 'Yaw right'));
    btnLand?.addEventListener('click', landDrone);
    btnEmergency?.addEventListener('click', emergencyStopDrone);
    btnSdkReset?.addEventListener('click', resetSdk);
    btnRadioScan?.addEventListener('click', () => runRadioAction('radio/scan', 'Radio scan'));
    btnRadioPreflight?.addEventListener('click', () => runRadioAction('radio/preflight', 'Radio preflight'));
    btnRadioAutoHop?.addEventListener('click', () => runRadioAction('radio/auto-hop', 'Auto hop'));
    btnRadioChannelApply?.addEventListener('click', runSelectedRadioChannel);

    connectSocket();
    await loadStatus();
    await loadRadioStatus();

    setInterval(loadStatus, 5000);
    setInterval(loadRadioStatus, 15000);
});

window.addEventListener('beforeunload', () => {
    if (lastObjectUrl) URL.revokeObjectURL(lastObjectUrl);
    if (socket) {
        try { socket.disconnect(); } catch (error) {}
    }
});
