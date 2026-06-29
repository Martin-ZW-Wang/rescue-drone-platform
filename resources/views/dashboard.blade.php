@extends('layouts.app')

@section('content')
    <h1 class="page-title">指揮中心總覽</h1>
    <p class="page-subtitle">整合即時影像監控、系統狀態、SEG 偵測、Pose 輔助與救援事件顯示</p>

    <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px;">
        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2>即時影像監控</h2>
                </div>
            </div>
            <div class="panel-body">
                <div style="background:#000; border-radius:12px; overflow:hidden; min-height:420px; display:flex; align-items:center; justify-content:center;">
                    <img id="stream" alt="Drone Stream" style="width:100%; display:block;">
                </div>

                <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:16px;">
                    <button id="btnStart" style="background:#10b981; color:#fff; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;">啟動 SEG 偵測</button>
                    <button id="btnStop" style="background:#ef4444; color:#fff; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;" disabled>停止偵測</button>
                    <button id="btnPose" style="background:#f59e0b; color:#111827; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;">開啟 Pose 輔助</button>
                    <button id="btnReload" style="background:#3b82f6; color:#fff; border:none; border-radius:10px; padding:12px 18px; cursor:pointer;">重連 WebSocket</button>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2>系統狀態</h2>
                </div>
            </div>
            <div class="panel-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div style="border:1px solid #334155; border-radius:12px; padding:12px;">
                        <div style="color:#94a3b8; font-size:13px;">連線狀態</div>
                        <div id="connectedValue" style="font-size:24px; font-weight:bold;">--</div>
                    </div>
                    <div style="border:1px solid #334155; border-radius:12px; padding:12px;">
                        <div style="color:#94a3b8; font-size:13px;">串流狀態</div>
                        <div id="streamingValue" style="font-size:24px; font-weight:bold;">--</div>
                    </div>
                    <div style="border:1px solid #334155; border-radius:12px; padding:12px;">
                        <div style="color:#94a3b8; font-size:13px;">SEG 偵測</div>
                        <div id="segValue" style="font-size:24px; font-weight:bold;">--</div>
                    </div>
                    <div style="border:1px solid #334155; border-radius:12px; padding:12px;">
                        <div style="color:#94a3b8; font-size:13px;">Pose 輔助</div>
                        <div id="poseValue" style="font-size:24px; font-weight:bold;">--</div>
                    </div>
                    <div style="border:1px solid #334155; border-radius:12px; padding:12px;">
                        <div style="color:#94a3b8; font-size:13px;">電池</div>
                        <div id="batteryValue" style="font-size:24px; font-weight:bold;">--</div>
                    </div>
                </div>

                <div id="statusText" style="margin-top:14px; border:1px solid #334155; border-radius:12px; padding:12px;">
                    尚未連線
                </div>
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1.3fr 1fr; gap:16px; margin-top:16px;">
        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2>救援事件訊息</h2>
                </div>
            </div>
            <div class="panel-body">
                <div id="msgBox" style="min-height:220px; max-height:320px; overflow:auto;">
                    <div style="color:#94a3b8;">目前尚未收到救援事件</div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2>偵測除錯資訊</h2>
                </div>
            </div>
            <div class="panel-body">
                <pre id="debugText" style="white-space:pre-wrap; margin:0;">等待狀態資料...</pre>
            </div>
        </div>
    </div>

    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
    <script>
        window.DRONE_CONFIG = {
            apiBase: '/api/drone',
            socketBase: 'http://127.0.0.1:5001',
            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        };
    </script>
@endsection
