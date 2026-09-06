# 智慧搜救無人機第二階段論文專案總覽

## 1. 論文定位

建議論文題名：

**YOLO-Based Instance Segmentation and Pose-Assisted Injured Target Detection for a Local Near-Edge Rescue Drone Prototype**

本專案是一個以搜救情境為目標的原型系統。核心重點不是「完全機載 edge AI」，而是：

- 無人機或攝影來源提供即時影像。
- 本機端 Python 服務執行 YOLO instance segmentation 與 pose-assisted 判斷。
- Laravel 提供 Dashboard、API proxy、事件儲存、事件列表與 MCP AI 頁面。
- MCP server / Ollama 用於讀取事件資料並產生摘要。

因此，論文應定位為 **local near-edge inference prototype**：推論在無人機附近的本機電腦或近端運算節點執行，而非部署在 Tello 或機載板上。

## 2. 專案核心功能

根據目前檔案確認，系統包含以下功能：

- 即時影像串流與狀態推播：`python_service/drone_server.py`
- YOLO segmentation 偵測：`python_service/drone.py`
- Pose 輔助判斷：`python_service/drone.py`
- 疑似傷者事件判定與時間一致性確認：`python_service/drone.py`
- WebSocket 推送影像、狀態與事件：`python_service/drone_server.py`
- Python 偵測事件後 POST 至 Laravel：`python_service/drone_server.py`
- Laravel 儲存 `rescue_events`：`database/migrations/2026_03_28_134157_create_rescue_events_table.php`
- Laravel Dashboard / event list / MCP AI 頁面：`routes/web.php`, `resources/views/`
- Laravel API proxy：`routes/api.php`, `app/Services/DroneService.php`
- MCP / Ollama event summary：`mcp_server/server.py`, `ai_orchestrator/chat.py`, `app/Http/Controllers/Api/McpAiController.php`

## 3. 主要技術棧

- Backend / Web：Laravel 12, PHP 8.2
- Frontend build：Vite, Laravel Vite Plugin, Tailwind CSS dependency
- Realtime / CV service：Python, Flask, Flask-SocketIO, OpenCV, Ultralytics YOLO
- Drone：DJI Tello Python control path, as inferred from `python_service/drone_runtime.py` and service naming
- AI assistant：MCP server, Ollama local model
- Database：Laravel migration currently defaults to SQLite in `.env.example`

## 4. 論文應強調的貢獻

1. 以 YOLO instance segmentation 偵測 rescue-oriented injured target candidate。
2. 使用 ROI pose estimation 輔助降低單純 bounding box 判斷的不穩定性。
3. 結合 bounding box horizontal ratio、torso angle、keypoint confidence 與 temporal consistency 產生 Rescue Needed 事件。
4. 建立 Python near-edge inference service 與 Laravel command-center interface 的整合原型。
5. 將事件以資料表 `rescue_events` 儲存，並透過 MCP / Ollama 產生事件摘要。

## 5. 不應誇大的範圍

論文與展示請避免以下說法：

- 不要說系統已完成真實災害現場驗證。
- 不要說模型已通過臨床或真實傷患驗證。
- 不要說 AI 推論已部署於 Tello 機體或完全 onboard edge device。
- 不要暗示 ROC / AUC 已完成，除非後續補齊 negative/background-only validation set。
- 不要把 `debug_outputs/` 當作正式實驗結果。

建議用語：

- simulated and augmented rescue-oriented scenes
- preliminary feasibility validation
- local near-edge inference prototype
- event-oriented rescue output
- Flask-SocketIO and Laravel command-center interface

## 6. 目前證據來源

本文件依據以下檔案與使用者提供背景整理：

- `README.md`
- `routes/api.php`
- `routes/web.php`
- `app/Services/DroneService.php`
- `app/Http/Controllers/Api/DroneApiController.php`
- `app/Http/Controllers/Api/EventIngestController.php`
- `app/Http/Controllers/Api/EventQueryController.php`
- `app/Http/Controllers/Api/McpAiController.php`
- `app/Models/RescueEvent.php`
- `database/migrations/2026_03_28_134157_create_rescue_events_table.php`
- `python_service/drone.py`
- `python_service/drone_server.py`
- `mcp_server/server.py`
- `ai_orchestrator/chat.py`

