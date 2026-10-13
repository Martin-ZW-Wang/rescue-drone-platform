@extends('layouts.app')

@section('content')
    <h1 class="page-title">Rescue Drone Command Center</h1>
    <p class="page-subtitle">Integrated live video, flight state, casualty detection, posture assessment, radio-channel control, and incident review.</p>

    <div class="dashboard-hero-grid">
        <div class="dashboard-main-column" style="display:grid; gap:16px;">
            <div class="panel monitor-panel">
                <div class="panel-head">
                    <h2>Live Video Feed</h2>
                </div>
                <div class="panel-body">
                    <div class="stream-frame">
                        <img id="stream" alt="Live drone video feed">
                        <div class="stream-topbar">
                            <span id="streamLiveBadge" class="live-badge">OFFLINE</span>
                            <span id="streamHostValue" class="stream-host">Tello host --</span>
                        </div>
                        <div class="stream-bottombar">
                            <span id="streamStateValue">Stream disconnected</span>
                            <span id="streamBatteryValue">Battery --</span>
                            <span id="streamFlightValue">Grounded</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel command-center-radio">
                <div class="panel-head">
                    <h2>Command Center Radio</h2>
                </div>
                <div class="panel-body">
                    <div class="radio-status-row" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                        <div>
                            <div id="radioSummary" style="color:#cbd5e1; font-size:14px;">Waiting for scan</div>
                            <div style="color:#94a3b8; font-size:13px; margin-top:4px;">A preflight radio scan is required before takeoff.</div>
                        </div>
                        <div class="radio-actions" style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button id="btnRadioScan" style="background:#0ea5e9; color:#fff; border:none; border-radius:10px; padding:10px 12px; cursor:pointer;">Scan</button>
                            <button id="btnRadioPreflight" style="background:#14b8a6; color:#06211e; border:none; border-radius:10px; padding:10px 12px; cursor:pointer;">Preflight</button>
                            <button id="btnRadioAutoHop" style="background:#f59e0b; color:#111827; border:none; border-radius:10px; padding:10px 12px; cursor:pointer;">Auto Hop</button>
                        </div>
                    </div>
                    <div class="radio-metrics" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-top:12px;">
                        <div style="border:1px solid #334155; border-radius:10px; padding:10px;">
                            <div style="color:#94a3b8; font-size:12px;">Current Channel</div>
                            <div id="radioCurrentChannel" style="font-size:20px; font-weight:bold;">--</div>
                        </div>
                        <div style="border:1px solid #334155; border-radius:10px; padding:10px;">
                            <div style="color:#94a3b8; font-size:12px;">Recommended Channel</div>
                            <div id="radioRecommendedChannel" style="font-size:20px; font-weight:bold;">--</div>
                        </div>
                        <div style="border:1px solid #334155; border-radius:10px; padding:10px;">
                            <div style="color:#94a3b8; font-size:12px;">Detected Networks</div>
                            <div id="radioNetworkCount" style="font-size:20px; font-weight:bold;">--</div>
                        </div>
                    </div>
                    <div class="radio-channel-row" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-top:12px; border:1px solid #334155; border-radius:10px; padding:12px;">
                        <div>
                            <div style="font-weight:700; color:#e2e8f0;">Channel Hopping</div>
                            <div id="radioHopHint" style="color:#94a3b8; font-size:13px; margin-top:4px;">Scan first, then select a 2.4 GHz control channel.</div>
                        </div>
                        <div class="radio-channel-controls" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                            <select id="radioChannelSelect" style="min-width:260px; background:#020617; color:#e2e8f0; border:1px solid #334155; border-radius:10px; padding:10px 12px;">
                                <option value="">Channels load after scan</option>
                            </select>
                            <button id="btnRadioChannelApply" style="background:#1d4ed8; color:#fff; border:none; border-radius:10px; padding:10px 14px; cursor:pointer;">Apply Target</button>
                        </div>
                    </div>
                    <pre id="radioDetails" style="white-space:pre-wrap; margin:12px 0 0; color:#cbd5e1; font-size:12px; max-height:150px; overflow:auto;">No radio data</pre>
                </div>
            </div>
        </div>

        <div class="panel command-panel">
            <div class="panel-head">
                <h2>Flight And Detection Control</h2>
            </div>
            <div class="panel-body">
                <div class="status-grid compact-status-grid">
                    <div class="status-tile">
                        <div style="color:#94a3b8; font-size:13px;">Link</div>
                        <div id="connectedValue" style="font-size:24px; font-weight:bold;">--</div>
                    </div>
                    <div class="status-tile">
                        <div style="color:#94a3b8; font-size:13px;">Stream</div>
                        <div id="streamingValue" style="font-size:24px; font-weight:bold;">--</div>
                    </div>
                    <div class="status-tile">
                        <div style="color:#94a3b8; font-size:13px;">Flight</div>
                        <div id="flyingValue" style="font-size:24px; font-weight:bold;">--</div>
                    </div>
                    <div class="status-tile">
                        <div style="color:#94a3b8; font-size:13px;">Casualty Detection</div>
                        <div id="segValue" style="font-size:24px; font-weight:bold;">--</div>
                    </div>
                    <div class="status-tile">
                        <div style="color:#94a3b8; font-size:13px;">Posture Assessment</div>
                        <div id="poseValue" style="font-size:24px; font-weight:bold;">--</div>
                    </div>
                    <div class="status-tile">
                        <div style="color:#94a3b8; font-size:13px;">Battery</div>
                        <div id="batteryValue" style="font-size:24px; font-weight:bold;">--</div>
                    </div>
                </div>

                <div id="statusText" class="status-message">
                    Waiting for system state...
                </div>

                <section class="control-section">
                    <div class="control-section-title">Flight Safety</div>
                    <div class="control-grid safety-grid">
                        <button id="btnTakeoff" style="background:#2563eb; color:#fff; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;" disabled>Take Off</button>
                        <button id="btnLand" style="background:#7c3aed; color:#fff; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;" disabled>Land</button>
                        <button id="btnEmergency" class="danger-action" style="background:#991b1b; color:#fff; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;">Emergency Stop</button>
                    </div>
                </section>

                <section class="control-section">
                    <div class="control-section-title">Detection Pipeline</div>
                    <div class="control-grid">
                        <button id="btnStart" style="background:#10b981; color:#fff; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;">Start Detection</button>
                        <button id="btnStop" style="background:#ef4444; color:#fff; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;" disabled>Stop Detection</button>
                        <button id="btnPose" style="background:#f59e0b; color:#111827; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;">Enable Posture</button>
                    </div>
                </section>

                <section class="control-section">
                    <div class="control-section-title">System Control</div>
                    <div class="control-grid two-col">
                        <button id="btnSdkReset" style="background:#64748b; color:#fff; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;">Reset SDK</button>
                        <button id="btnReload" style="background:#3b82f6; color:#fff; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;">Reconnect WebSocket</button>
                    </div>
                </section>

                <section id="manualFlightPanel" class="flight-control-block">
                    <div class="control-section-title">Manual Trim</div>
                    <div class="manual-flight-grid">
                        <div class="flight-pad">
                            <button id="btnNudgeYawLeft" style="background:#4338ca; color:#fff; border:none; border-radius:10px; padding:12px; cursor:pointer;" disabled>Yaw Left</button>
                            <button id="btnNudgeForward" style="background:#0f766e; color:#fff; border:none; border-radius:10px; padding:12px; cursor:pointer;" disabled>Forward</button>
                            <button id="btnNudgeYawRight" style="background:#4338ca; color:#fff; border:none; border-radius:10px; padding:12px; cursor:pointer;" disabled>Yaw Right</button>
                            <button id="btnNudgeLeft" style="background:#0f766e; color:#fff; border:none; border-radius:10px; padding:12px; cursor:pointer;" disabled>Left</button>
                            <button type="button" style="background:#1f2937; color:#94a3b8; border:1px solid #334155; border-radius:10px; padding:12px;" disabled>Stop</button>
                            <button id="btnNudgeRight" style="background:#0f766e; color:#fff; border:none; border-radius:10px; padding:12px; cursor:pointer;" disabled>Right</button>
                            <div></div>
                            <button id="btnNudgeBack" style="background:#0f766e; color:#fff; border:none; border-radius:10px; padding:12px; cursor:pointer;" disabled>Backward</button>
                            <div></div>
                        </div>

                        <div class="altitude-control">
                            <button id="btnNudgeUp" style="background:#0ea5e9; color:#fff; border:none; border-radius:10px; padding:14px 18px; cursor:pointer;" disabled>Ascend Step</button>
                            <button id="btnNudgeDown" style="background:#0284c7; color:#fff; border:none; border-radius:10px; padding:14px 18px; cursor:pointer;" disabled>Descend Step</button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="dashboard-grid secondary-grid" style="display:grid; grid-template-columns:1.3fr 1fr; gap:16px; margin-top:16px;">
        <div class="panel">
            <div class="panel-head">
                <h2>Recent Rescue Incidents</h2>
            </div>
            <div class="panel-body">
                <div id="msgBox" style="min-height:220px; max-height:320px; overflow:auto;">
                    <div style="color:#94a3b8;">No rescue incidents have been received yet.</div>
                </div>
            </div>
        </div>

        <details class="panel debug-details">
            <summary class="panel-head">
                <span>Detection Debug Data</span>
                <span class="details-indicator">Expand</span>
            </summary>
            <div class="panel-body">
                <pre id="debugText" class="debug-output">Waiting for detection data...</pre>
            </div>
        </details>
    </div>

    <script>
        window.DRONE_CONFIG = {
            apiBase: '/api/drone',
            socketBase: null,
            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        };
    </script>
@endsection
