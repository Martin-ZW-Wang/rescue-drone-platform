# 系統架構整理

## 1. 總體架構

本專案是混合式多服務架構：

```text
User
  -> Laravel Dashboard (Blade + Vite JS)
  -> Laravel API proxy
  -> Python Flask-SocketIO near-edge service
  -> Tello / camera frame source
  -> YOLO segmentation + pose-assisted event decision
  -> Python POST /api/events/ingest
  -> Laravel RescueEvent database
  -> Events page / MCP AI summary
```

## 2. Laravel 的角色

Laravel 不是主要推論端，而是 command-center 與 persistence layer。

主要責任：

- 提供 Dashboard：`resources/views/dashboard.blade.php`
- 提供 event list/detail：`resources/views/events/`
- 提供 MCP AI 頁面：`resources/views/mcp/index.blade.php`
- 提供 API proxy：`app/Services/DroneService.php`
- 接收 Python service 事件：`EventIngestController.php`
- 儲存事件：`RescueEvent` model 與 migration
- 提供 Ollama summary API：`McpAiController.php`

## 3. Python service 的角色

Python service 是 local near-edge inference service。

主要責任：

- 連接 Tello / camera runtime。
- 擷取影像 frame。
- 執行 YOLO instance segmentation。
- 對候選區域執行 pose-assisted decision。
- 產生 `Rescue Needed` event。
- 用 SocketIO 推送 frame/status/event。
- 將確認事件 POST 到 Laravel。

## 4. MCP / Ollama 的角色

MCP server 與 AI orchestrator 不參與影像推論，而是事件查詢與摘要層。

資料流：

```text
MCP AI page / CLI
  -> Laravel MCP API or MCP server tools
  -> Laravel events API
  -> RescueEvent database
  -> Ollama local model
  -> textual summary / risk overview
```

## 5. Realtime flow

```text
Python capture thread
  -> frame queue
  -> inference thread
  -> YOLO segmentation
  -> optional pose analysis
  -> status/event update
  -> SocketIO emit frame/status/event
  -> browser dashboard
```

## 6. Event persistence flow

```text
YOLO/Pose decision in python_service/drone.py
  -> event dict
  -> python_service/drone_server.py event queue
  -> POST http://127.0.0.1:8000/api/events/ingest
  -> EventIngestController validation
  -> RescueEvent::create(...)
  -> rescue_events table
  -> /events and /api/events/recent
```

## 7. 論文架構圖建議

建議畫成四層：

1. Sensing layer：Tello / camera frame source
2. Near-edge inference layer：Flask-SocketIO + YOLO segmentation + pose-assisted decision
3. Web application layer：Laravel API proxy + dashboard + event database
4. AI summary layer：MCP server + Ollama

建議圖中明確標示：

- 影像串流方向：Python -> Browser via SocketIO
- 控制方向：Browser -> Laravel -> Python
- 事件儲存方向：Python -> Laravel -> DB
- AI 摘要方向：MCP/Ollama -> Laravel events API

## 8. Port / service 關係

| Service | Port | 依據 |
|---|---:|---|
| Laravel dev server | 8000 | `composer.json` dev script uses `php artisan serve`; default Laravel local port commonly 8000 |
| Python Flask-SocketIO | 5001 | `python_service/drone_server.py` and `.env.example` |
| Ollama | 11434 | `.env.example`, `mcp_server/server.py`, `ai_orchestrator/chat.py` |

推測：Laravel 若用 `php artisan serve --port=8000` 啟動，會符合 Python service 目前寫死的 `LARAVEL_EVENT_INGEST_URL`。

## 9. 目前架構風險

- Python event ingest URL 寫死為 `http://127.0.0.1:8000/api/events/ingest`。
- Dashboard socket URL 在 Blade 中寫死為 `http://127.0.0.1:5001`。
- `vite.config.js` 未將 `resources/js/drone-monitor.js` 明列為 input。
- Python service 與 Laravel API 目前未看到身份驗證保護，若部署至公開網路需加上 auth。
- 真機 Tello、YOLO 權重與 Ollama model 不會由 Git 自動提供，需要 README 補充取得方式。

