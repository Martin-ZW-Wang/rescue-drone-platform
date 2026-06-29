<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? '智慧搜救無人機指揮中心' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/drone-monitor.js'])
    <style>
        :root {
            color-scheme: dark;
            --bg: #0b1220;
            --panel: #111827;
            --panel-2: #1f2937;
            --line: #374151;
            --text: #f9fafb;
            --muted: #9ca3af;
            --ok: #10b981;
            --warn: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #08101c 0%, #0f172a 100%);
            color: var(--text);
        }

        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 240px 1fr;
        }

        .sidebar {
            background: rgba(10, 15, 28, .95);
            border-right: 1px solid #263247;
            padding: 22px 18px;
        }

        .brand {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 24px;
            line-height: 1.4;
        }

        .nav a {
            display: block;
            color: #dbeafe;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 10px;
            background: rgba(255,255,255,.03);
            border: 1px solid transparent;
        }

        .nav a:hover {
            background: rgba(59, 130, 246, .12);
            border-color: #31507a;
        }

        .nav a.active {
            background: rgba(59, 130, 246, .18);
            border-color: #e5e7eb;
            color: #fff;
        }

        .content {
            padding: 22px;
        }

        .page-title {
            font-size: 28px;
            margin: 0 0 8px;
        }

        .page-subtitle {
            color: var(--muted);
            margin: 0 0 18px;
        }

        .panel {
            background: rgba(17, 24, 39, .94);
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,.22);
        }

        .panel-head {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .panel-head h2,
        .panel-head h3 {
            margin: 0;
            font-size: 18px;
        }

        .panel-body {
            padding: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border-bottom: 1px solid #273244;
            padding: 12px 10px;
            text-align: left;
        }

        th {
            color: #cbd5e1;
            font-size: 14px;
        }

        td {
            font-size: 14px;
        }

        a.link {
            color: #93c5fd;
            text-decoration: none;
        }

        a.link:hover {
            text-decoration: underline;
        }

        @media (max-width: 960px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: none;
                border-bottom: 1px solid #263247;
            }
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">智慧搜救無人機<br>指揮中心</div>
        <nav class="nav">
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="{{ request()->routeIs('events.*') ? 'active' : '' }}" href="{{ route('events.index') }}">事件列表</a>
            <a class="{{ request()->routeIs('mcp.*') ? 'active' : '' }}" href="{{ route('mcp.index') }}">MCP AI</a>
        </nav>
    </aside>

    <main class="content">
        @yield('content')
    </main>
</div>
</body>
</html>
