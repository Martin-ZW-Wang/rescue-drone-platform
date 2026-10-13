<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Rescue Drone Command Center' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/drone-monitor.js'])
    <style>
        :root {
            color-scheme: light;
            --bg: #f4efe7;
            --panel: #eef3f2;
            --panel-soft: #e4eeee;
            --line: #b9c9cc;
            --text: #21313a;
            --muted: #63757d;
            --accent: #d9857f;
            --accent-strong: #b98faa;
            --accent-deep: #8f4b57;
            --accent-ink: #271816;
            --accent-soft: rgba(217, 133, 127, .18);
            --accent-border: rgba(143, 75, 87, .28);
            --accent-gradient: linear-gradient(135deg, #d9857f, #b98faa);
            --mint: #8db9ad;
            --mint-ink: #11352f;
            --surface: #eaf0f0;
            --surface-strong: #f4f7f6;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(141, 185, 173, .18), transparent 36rem),
                radial-gradient(circle at bottom right, rgba(217, 133, 127, .1), transparent 34rem),
                linear-gradient(180deg, #f4efe7 0%, #efe9df 54%, #e9e1d7 100%);
            color: var(--text);
            overflow-x: hidden;
        }

        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            width: 100%;
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            overflow: auto;
            background: #e6e7e7;
            border-right: 1px solid var(--line);
            padding: 22px 18px;
        }

        .brand {
            margin-bottom: 24px;
            font-size: 20px;
            font-weight: 900;
            line-height: 1.35;
        }

        .brand small {
            display: block;
            margin-top: 4px;
            color: var(--accent);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .nav a {
            display: block;
            color: #2d4650;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 10px;
            background: #f1f2f2;
            border: 1px solid transparent;
            font-weight: 800;
        }

        .nav a:hover {
            background: #f7f7f7;
            border-color: var(--accent-border);
        }

        .nav a.active {
            background: #f4f4f4;
            border-color: var(--accent);
            color: var(--text);
        }

        .content {
            min-width: 0;
            max-width: 100%;
            padding: 22px;
            overflow-x: hidden;
            container-type: inline-size;
        }

        .page-title {
            margin: 0 0 8px;
            font-size: 28px;
            letter-spacing: 0;
        }

        .page-subtitle {
            margin: 0 0 18px;
            color: var(--muted);
        }

        .panel {
            min-width: 0;
            background: rgba(238, 243, 242, .94);
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 14px 34px rgba(55, 79, 86, .12);
        }

        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
        }

        .panel-head h2,
        .panel-head h3 {
            margin: 0;
            font-size: 18px;
        }

        .panel-body { padding: 16px; }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border-bottom: 1px solid #372a34;
            padding: 12px 10px;
            text-align: left;
        }

        th {
            color: #ead8cb;
            font-size: 14px;
        }

        td { font-size: 14px; }

        a.link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 800;
        }

        a.link:hover { text-decoration: underline; }

        .dashboard-hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(340px, .75fr);
            gap: 16px;
            align-items: start;
        }

        .dashboard-hero-grid > *,
        .dashboard-main-column {
            min-width: 0;
        }

        .dashboard-grid {
            display: grid;
            gap: 16px;
        }

        .monitor-panel .panel-body,
        .command-panel .panel-body {
            display: grid;
            gap: 14px;
        }

        .stream-frame {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            height: clamp(280px, 46vh, 520px);
            background: #000;
            border: 1px solid var(--accent-border);
            border-radius: 12px;
            overflow: hidden;
        }

        .stream-frame img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .stream-topbar,
        .stream-bottombar {
            position: absolute;
            left: 12px;
            right: 12px;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            pointer-events: none;
        }

        .stream-topbar { top: 12px; }

        .stream-bottombar {
            bottom: 12px;
            padding: 8px 10px;
            border: 1px solid rgba(148, 163, 184, .2);
            border-radius: 10px;
            background: rgba(2, 6, 23, .72);
            color: #ead8cb;
            font-size: 12px;
        }

        .live-badge,
        .stream-host {
            border-radius: 999px;
            padding: 6px 10px;
            background: rgba(18, 14, 20, .76);
            border: 1px solid rgba(148, 163, 184, .2);
            color: #f7ddca;
            font-size: 12px;
            font-weight: 900;
        }

        .live-badge {
            background: rgba(239, 68, 68, .18);
            border-color: rgba(248, 113, 113, .5);
            color: #fecaca;
            letter-spacing: .06em;
        }

        .live-badge.is-live {
            background: rgba(16, 185, 129, .16);
            border-color: rgba(52, 211, 153, .55);
            color: #bbf7d0;
        }

        .status-grid,
        .compact-status-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .status-tile,
        .summary-tile,
        .event-card,
        .empty-state {
            border: 1px solid #4a3946;
            background: rgba(33, 25, 35, .72);
            border-radius: 12px;
            padding: 14px;
        }

        .summary-tile span,
        .event-card-kicker,
        .event-card-meta,
        .detail-list dt {
            color: #94a3b8;
            font-size: 13px;
        }

        .summary-tile strong {
            display: block;
            margin-top: 6px;
            font-size: 24px;
        }

        .status-message {
            max-height: 118px;
            overflow: auto;
            border: 1px solid #4a3946;
            border-radius: 12px;
            padding: 10px 12px;
            color: #f6e7dd;
            line-height: 1.5;
        }

        .control-section,
        .flight-control-block {
            border-top: 1px solid #372a34;
            padding-top: 12px;
        }

        .control-section-title {
            color: #ead8cb;
            font-size: 14px;
            font-weight: 900;
            margin-bottom: 10px;
        }

        .control-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .control-grid.two-col {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .safety-grid {
            grid-template-columns: 1fr 1fr 1.2fr;
        }

        .control-grid button,
        .flight-pad button,
        .altitude-control button {
            width: 100%;
            min-height: 46px;
            font-weight: 900;
            line-height: 1.22;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .manual-flight-grid {
            display: grid;
            grid-template-columns: minmax(186px, 1fr) minmax(118px, .55fr);
            gap: 12px;
            align-items: start;
        }

        .flight-pad {
            display: grid;
            grid-template-columns: repeat(3, minmax(50px, 1fr));
            grid-template-rows: repeat(3, 46px);
            gap: 8px;
        }

        .altitude-control {
            display: grid;
            gap: 8px;
        }

        .command-panel .control-grid button,
        .command-panel .flight-pad button,
        .command-panel .altitude-control button {
            padding: 10px 12px !important;
            font-size: clamp(13px, 0.9cqi, 15px);
        }

        .command-panel .control-grid {
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        }

        .command-panel .control-grid.two-col {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .command-panel .manual-flight-grid {
            grid-template-columns: minmax(0, 1fr) minmax(132px, .5fr);
        }

        .command-panel .flight-pad {
            grid-template-columns: repeat(3, minmax(64px, 1fr));
        }

        .command-center-radio {
            min-width: 0;
        }

        .command-center-radio .radio-status-row,
        .command-center-radio .radio-channel-row {
            min-width: 0;
        }

        .command-center-radio .radio-actions,
        .command-center-radio .radio-channel-controls {
            min-width: 0;
        }

        .command-center-radio .radio-channel-controls {
            flex: 1 1 380px;
        }

        .command-center-radio select {
            width: 100%;
            min-width: 0 !important;
            max-width: 100%;
        }

        .command-center-radio #btnRadioChannelApply {
            flex: 0 0 auto;
            min-height: 42px;
        }

        .debug-details > summary {
            cursor: pointer;
            list-style: none;
        }

        .debug-details > summary::-webkit-details-marker { display: none; }

        .details-indicator {
            color: var(--accent);
            font-size: 13px;
            font-weight: 800;
        }

        .debug-details[open] .details-indicator { font-size: 0; }
        .debug-details[open] .details-indicator::after {
            content: "Collapse";
            font-size: 13px;
        }

        .debug-output,
        .payload-pre {
            max-height: 360px;
            overflow: auto;
            white-space: pre-wrap;
            background: #0b070a;
            border: 1px solid #4a3946;
            border-radius: 10px;
            padding: 12px;
            color: #f7ddca;
            line-height: 1.45;
        }

        .event-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .event-card-list {
            display: none;
            gap: 12px;
        }

        .event-card {
            display: grid;
            gap: 12px;
        }

        .event-card h3 {
            margin: 4px 0 6px;
            font-size: 18px;
        }

        .event-card p {
            margin: 0;
            color: #ead8cb;
        }

        .event-card-meta {
            display: grid;
            gap: 4px;
        }

        .event-card-link {
            justify-self: start;
            color: var(--accent);
            text-decoration: none;
            font-weight: 900;
        }

        .detail-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .event-detail-grid {
            display: grid;
            grid-template-columns: minmax(260px, .75fr) minmax(0, 1.25fr);
            gap: 16px;
        }

        .detail-list {
            display: grid;
            gap: 12px;
            margin: 0;
        }

        .detail-list div {
            border-bottom: 1px solid #372a34;
            padding-bottom: 10px;
        }

        .detail-list dd {
            margin: 4px 0 0;
            font-weight: 900;
        }

        .detail-message { margin-top: 18px; }
        .detail-message h3,
        .payload-title {
            margin: 0 0 8px;
            color: #ead8cb;
            font-size: 15px;
        }

        .detail-message p {
            margin: 0;
            color: #e5e7eb;
            line-height: 1.65;
        }

        @media (max-width: 960px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar {
                position: static;
                height: auto;
                border-right: 0;
            border-bottom: 1px solid #3a2d38;
                padding: 14px 16px;
            }
            .brand { margin-bottom: 12px; }
            .nav {
                display: flex;
                gap: 8px;
                overflow-x: auto;
            }
            .nav a {
                flex: 0 0 auto;
                margin-bottom: 0;
            }
            .dashboard-grid,
            .dashboard-hero-grid,
            .secondary-grid,
            .manual-flight-grid,
            .event-detail-grid {
                grid-template-columns: 1fr !important;
            }
            .content { padding: 16px; }
        }

        a.link,
        .event-card-link {
            color: var(--accent) !important;
        }

        input,
        select,
        textarea {
            accent-color: var(--accent);
        }

        body,
        .page-title,
        .panel-head h2,
        .panel-head h3,
        .control-section-title,
        .side-title,
        .chat-title {
            color: var(--text) !important;
        }

        .page-subtitle,
        .summary-tile span,
        .event-card-kicker,
        .event-card-meta,
        .detail-list dt,
        .track-help,
        .chat-subtitle,
        .event-meta,
        .event-subtle,
        .source-snippet,
        .composer-help {
            color: var(--muted) !important;
        }

        .panel,
        .mcp-sidebar,
        .chat-card,
        .status-tile,
        .summary-tile,
        .event-card,
        .empty-state,
        .event-help,
        .status-pill,
        .event-item,
        .tool-list li,
        .source-card,
        .chat-evidence-card,
        .evidence-frame,
        .radio-metrics > div,
        .radio-channel-row {
            background: rgba(238, 243, 242, .94) !important;
            border-color: var(--line) !important;
            color: var(--text) !important;
            box-shadow: 0 10px 24px rgba(55, 79, 86, .1);
        }

        .panel-head,
        .chat-header,
        .composer,
        .side-section,
        .control-section,
        .flight-control-block {
            background: rgba(228, 238, 238, .74) !important;
            border-color: var(--line) !important;
        }

        .chat-messages {
            background:
                linear-gradient(rgba(74, 104, 112, .08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(74, 104, 112, .08) 1px, transparent 1px),
                var(--surface) !important;
        }

        .bubble,
        .status-message,
        .debug-output,
        .payload-pre,
        .composer-box,
        .review-action-row button,
        .review-action-btn,
        .filter-btn,
        .event-action,
        .event-search,
        .review-form textarea,
        .radio-channel-row select,
        #radioChannelSelect {
            background: var(--surface) !important;
            border-color: var(--line) !important;
            color: var(--text) !important;
        }

        .message.user .bubble,
        .filter-btn.active,
        .mode-btn.active,
        .send-btn {
            background: var(--accent-gradient) !important;
            border-color: var(--accent-deep) !important;
            color: var(--accent-ink) !important;
        }

        .avatar {
            background: #d5e0e1 !important;
            border-color: var(--line) !important;
            color: #2d4650 !important;
        }

        .avatar.sunny-avatar,
        .message.user .avatar {
            background: var(--accent-soft) !important;
            border-color: var(--accent-border) !important;
        }

        table th,
        table td,
        .detail-list div {
            border-color: var(--line) !important;
        }

        table tbody tr:hover,
        .event-table-wrap tbody tr:hover,
        .tool-list li:hover {
            background: var(--accent-soft) !important;
        }

        .stream-bottombar,
        .live-badge,
        .stream-host {
            background: rgba(238, 243, 242, .88) !important;
            border-color: var(--line) !important;
            color: var(--text) !important;
        }

        .live-badge {
            background: rgba(239, 68, 68, .12) !important;
            border-color: rgba(239, 68, 68, .28) !important;
            color: #991b1b !important;
        }

        .live-badge.is-live {
            background: rgba(34, 197, 94, .14) !important;
            border-color: rgba(34, 197, 94, .28) !important;
            color: #166534 !important;
        }

        #btnRadioScan,
        #btnRadioAutoHop,
        #btnRadioChannelApply,
        #btnTakeoff,
        #btnReload,
        #btnNudgeYawLeft,
        #btnNudgeYawRight,
        #btnNudgeUp,
        #btnNudgeDown {
            background: var(--accent-gradient) !important;
            color: var(--accent-ink) !important;
        }

        #btnRadioPreflight,
        #btnStart,
        #btnNudgeForward,
        #btnNudgeLeft,
        #btnNudgeRight,
        #btnNudgeBack {
            background: var(--mint) !important;
            color: var(--mint-ink) !important;
        }

        #btnPose {
            background: linear-gradient(135deg, #f8c5a7, #f7b7b2) !important;
            color: var(--accent-ink) !important;
        }

        #btnPose[data-enabled="true"],
        #btnSdkReset {
            background: #cbd6d6 !important;
            color: var(--text) !important;
        }

        .flight-pad button:not([id]) {
            background: #d2dddd !important;
            border-color: #b9c9cc !important;
            color: var(--muted) !important;
        }

        .status-tile,
        .summary-tile,
        .event-card,
        .empty-state,
        .radio-metrics > div,
        .radio-channel-row {
            border-color: var(--line) !important;
            background: rgba(238, 243, 242, .94) !important;
        }

        .radio-channel-row select,
        .event-search {
            border-color: var(--accent-border) !important;
            background: var(--surface) !important;
            color: var(--text) !important;
        }

        .mcp-sidebar,
        .chat-card,
        .bubble,
        .event-evidence-link,
        .chat-evidence-card,
        .source-card,
        .evidence-frame,
        .evidence-empty,
        .event-help,
        .review-action-row button,
        .review-action-btn,
        .filter-btn,
        .event-action,
        .event-search,
        .review-form textarea,
        .status-message,
        .debug-output,
        .payload-pre,
        .composer-box {
            background: var(--surface) !important;
            border-color: var(--line) !important;
            color: var(--text) !important;
        }

        .chat-messages,
        .composer,
        .chat-header,
        .side-section {
            background-color: var(--panel-soft) !important;
        }

        .bubble code,
        .confidence-bar {
            background: #d5e0e1 !important;
            border-color: var(--line) !important;
            color: #2d4650 !important;
        }

        .confidence-bar span {
            background: linear-gradient(90deg, #d9857f, #8db9ad) !important;
        }

        .event-risk.medium,
        .review-pill,
        .event-chip.pending,
        .review-action-btn.pending,
        .review-action-row .pending {
            color: #8f4b57 !important;
        }

        .source-card a,
        .event-link,
        .event-card-link,
        .detail-action-btn,
        .mini-action,
        .tool-action span {
            color: #8f4b57 !important;
        }

        .sun-icon {
            background: radial-gradient(circle at 36% 34%, #fff7b8 0 18%, #facc15 19% 58%, #f59e0b 59% 100%) !important;
            box-shadow: 0 0 0 4px rgba(250, 204, 21, .22), 0 0 18px rgba(245, 158, 11, .38) !important;
        }

        .sun-icon::before {
            background: repeating-conic-gradient(from 0deg, rgba(245, 158, 11, .95) 0 8deg, transparent 8deg 30deg) !important;
        }

        .sunny-avatar {
            background: rgba(250, 204, 21, .18) !important;
            border-color: rgba(245, 158, 11, .48) !important;
        }

        .tool-action strong {
            color: #172a32 !important;
        }

        .tool-action span,
        .event-subtle,
        .source-snippet,
        .composer-help,
        .chat-subtitle {
            color: #4f6570 !important;
        }

        .mode-btn,
        .prompt-chip,
        .chat-btn,
        .mini-action {
            background: #e8eeee !important;
            border-color: #9db2b8 !important;
            color: #172a32 !important;
        }

        .mode-btn.active {
            background: #d9857f !important;
            border-color: #8f4b57 !important;
            color: #111827 !important;
        }

        .chat-btn,
        .prompt-chip {
            font-weight: 900;
        }

        .composer textarea::placeholder,
        input::placeholder,
        textarea::placeholder {
            color: #6f858e !important;
            opacity: 1;
        }

        .send-btn {
            background: #d9857f !important;
            color: #111827 !important;
            border: 1px solid #8f4b57 !important;
        }

        th,
        td,
        .track-code,
        .event-card h3,
        .event-card p,
        .detail-list dd,
        .confidence-meter strong {
            color: var(--text) !important;
        }

        th {
            color: #5a6870 !important;
            font-weight: 900;
        }

        .event-card-kicker,
        td [style*="#94a3b8"],
        .track-help {
            color: #657982 !important;
        }

        .event-risk.high {
            background: rgba(220, 38, 38, .12) !important;
            color: #991b1b !important;
        }

        .event-risk.high::before {
            background: #dc2626 !important;
        }

        .event-risk.medium {
            background: rgba(217, 133, 127, .16) !important;
            color: #7f3b46 !important;
        }

        .event-risk.low {
            background: rgba(20, 83, 45, .1) !important;
            color: #166534 !important;
        }

        .review-pill {
            background: rgba(217, 133, 127, .12) !important;
            color: #7f3b46 !important;
        }

        .review-pill.reviewed {
            background: rgba(22, 101, 52, .12) !important;
            color: #166534 !important;
        }

        .review-pill.dismissed {
            background: rgba(99, 117, 125, .14) !important;
            color: #334155 !important;
        }

        .event-action,
        .review-action-btn,
        .detail-action-btn,
        .filter-btn {
            color: #172a32 !important;
            border-color: #a9bdc2 !important;
            font-weight: 900;
        }

        .event-action,
        .detail-action-btn,
        .event-card-link,
        a.link {
            color: #b55358 !important;
        }

        .filter-btn.active {
            color: #1f1717 !important;
            border-color: #8f4b57 !important;
        }

        .confidence-bar {
            background: #d7e1e4 !important;
        }

        [style*="color:#94a3b8"],
        [style*="color: #94a3b8"],
        [style*="color:#cbd5e1"],
        [style*="color: #cbd5e1"],
        [style*="color:#dbeafe"],
        [style*="color: #dbeafe"],
        [style*="color:#e2e8f0"],
        [style*="color: #e2e8f0"] {
            color: #405964 !important;
        }

        .status-tile div:first-child,
        .summary-tile span,
        .radio-metrics div div:first-child,
        #radioHopHint,
        #radioSummary,
        #radioDetails,
        #debugText,
        #statusText,
        #msgBox,
        .details-indicator {
            color: #405964 !important;
        }

        .status-tile div[id],
        .radio-metrics div[id],
        #connectedValue,
        #streamingValue,
        #flyingValue,
        #segValue,
        #poseValue,
        #batteryValue,
        #radioCurrentChannel,
        #radioRecommendedChannel,
        #radioNetworkCount {
            font-weight: 900;
            text-shadow: none;
        }

        #radioDetails,
        #debugText,
        .payload-pre {
            background: #dfe9e9 !important;
            border-color: #9eb5bb !important;
            color: #21313a !important;
            font-size: 13px !important;
            line-height: 1.55 !important;
        }

        .panel,
        .status-tile,
        .summary-tile,
        .radio-metrics > div,
        .radio-channel-row,
        .status-message,
        .event-search,
        .composer-box,
        .bubble {
            border-color: #a9bdc2 !important;
        }

        .panel-head {
            border-bottom-color: #a9bdc2 !important;
        }

        .control-section,
        .flight-control-block {
            border-top-color: #a9bdc2 !important;
        }

        @container (max-width: 1280px) {
            .dashboard-hero-grid,
            .secondary-grid {
                grid-template-columns: 1fr !important;
            }

            .stream-frame {
                height: clamp(260px, 42cqi, 480px);
            }

            .command-panel .manual-flight-grid {
                grid-template-columns: minmax(0, 1fr) minmax(160px, .35fr);
            }
        }

        @container (max-width: 760px) {
            .command-center-radio .radio-status-row,
            .command-center-radio .radio-channel-row,
            .command-center-radio .radio-channel-controls,
            .command-center-radio .radio-actions {
                align-items: stretch !important;
                flex-direction: column;
                width: 100%;
            }

            .command-center-radio .radio-actions button,
            .command-center-radio #btnRadioChannelApply {
                width: 100%;
            }

            .command-panel .manual-flight-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .event-summary-grid,
            .compact-status-grid,
            .control-grid,
            .control-grid.two-col,
            .safety-grid,
            .radio-metrics {
                grid-template-columns: 1fr !important;
            }
            .event-card-list { display: grid; }
            .event-table-wrap { display: none; }
            .panel-body { padding: 12px; }
            .stream-frame { height: min(58vh, 360px); }
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            Rescue Drone
            <small>Command Center</small>
        </div>
        <nav class="nav">
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="{{ request()->routeIs('events.*') ? 'active' : '' }}" href="{{ route('events.index') }}">Incident Review</a>
            <a class="{{ request()->routeIs('mcp.*') ? 'active' : '' }}" href="{{ route('mcp.index') }}">Sunny</a>
        </nav>
    </aside>

    <main class="content">
        @yield('content')
    </main>
</div>
</body>
</html>
