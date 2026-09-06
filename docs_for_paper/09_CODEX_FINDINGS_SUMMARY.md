# Codex 掃描與整理摘要

## 1. 已確認的專案架構

本專案是 Laravel + Python + MCP/Ollama 的混合式原型：

- Laravel 12 / PHP 8.2：Dashboard、API proxy、event storage、MCP AI page。
- Python Flask-SocketIO：Tello/camera runtime、YOLO segmentation、pose-assisted decision、SocketIO stream。
- MCP server / AI orchestrator：讀取 Laravel 事件並呼叫 Ollama 產生摘要。
- Database：`rescue_events` migration 存放 detection event。

## 2. 核心檔案

### Laravel

- `routes/api.php`
- `routes/web.php`
- `app/Services/DroneService.php`
- `app/Http/Controllers/Api/DroneApiController.php`
- `app/Http/Controllers/Api/EventIngestController.php`
- `app/Http/Controllers/Api/EventQueryController.php`
- `app/Http/Controllers/Api/McpAiController.php`
- `app/Models/RescueEvent.php`
- `database/migrations/2026_03_28_134157_create_rescue_events_table.php`

### Frontend

- `resources/views/layouts/app.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/events/index.blade.php`
- `resources/views/events/show.blade.php`
- `resources/views/mcp/index.blade.php`
- `resources/js/drone-monitor.js`
- `resources/js/app.js`
- `resources/css/app.css`

### Python

- `python_service/drone.py`
- `python_service/drone_server.py`
- `python_service/drone_runtime.py`

### MCP / AI

- `mcp_server/server.py`
- `mcp_server/requirements.txt`
- `ai_orchestrator/chat.py`
- `ai_orchestrator/requirements.txt`

## 3. 找到的模型與不建議上 Git 的大型檔

- `python_service/SEG6-2.pt`
- `python_service/SEG.pt`
- `python_service/15012.pt`
- `python_service/yolov8n-pose.pt`
- `yolov8n-pose.pt`

建議：用 Git LFS、GitHub Releases 或外部下載說明管理。

## 4. 未找到的正式實驗結果檔

本次掃描未找到：

- `results.csv`
- `args.yaml`
- PR/F1/P/R curves
- confusion matrix images
- validation image-level CSV
- embedding projection CSV
- ROC note file

因此本批文件將使用者提供的 mAP/F1/confusion matrix 數字標示為「使用者提供，待原始結果檔驗證」。

## 5. 重要風險

1. `vite.config.js` 未明列 `resources/js/drone-monitor.js`，但 layout 直接使用 `@vite(...drone-monitor.js)`。
2. Python event ingest URL 寫死為 `http://127.0.0.1:8000/api/events/ingest`。
3. Dashboard socket URL 寫死為 `http://127.0.0.1:5001`。
4. `python_service/requirements.txt` 未找到；目前只有 `mcp_server/requirements.txt` 與 `ai_orchestrator/requirements.txt`。
5. 正式訓練結果檔與 dataset metadata 不在 repo。
6. 模型權重很大，不建議直接進 Git。
7. 若要公開部署，API auth / network restriction 需補強。

## 6. 給 ChatGPT 接手整理的重點摘要

- 專案定位：智慧搜救無人機 local near-edge inference prototype，不是完全 onboard edge AI。
- 論文題名建議：YOLO-Based Instance Segmentation and Pose-Assisted Injured Target Detection for a Local Near-Edge Rescue Drone Prototype。
- 核心流程：Tello/camera frame -> Python Flask-SocketIO -> YOLO segmentation -> pose-assisted decision -> Rescue Needed event -> Laravel `/api/events/ingest` -> `rescue_events` DB -> Dashboard/events/MCP summary。
- 核心檔：`python_service/drone.py`, `python_service/drone_server.py`, `routes/api.php`, `routes/web.php`, `DroneService.php`, `EventIngestController.php`, `RescueEvent.php`, `resources/js/drone-monitor.js`, `mcp_server/server.py`, `ai_orchestrator/chat.py`。
- 實驗數字目前只能先當使用者提供：Box mAP@0.5 約 0.895、Mask mAP@0.5 約 0.912、Box F1 約 0.87 at 0.596、Mask F1 約 0.88 at 0.600、TP 299、FP 38、FN 47、recall 約 0.86。
- repo 內目前未找到 `results.csv`, `args.yaml`, curves, confusion matrix 圖，需補齊才能讓論文結果可追溯。
- ROC/custom PR 未生成的合理說法：validation set 缺少 negative/background-only images。
- 論文必須揭露限制：dataset scale、scene leakage risk、negative samples 缺乏、pose instability、mask confusion、非 onboard edge AI、latency/FPS 未完整評估、真實災害現場未驗證、隱私/同意。
- GitHub 前建議補：`python_service/requirements.txt`、模型權重取得說明、實驗結果資料夾、port/service 說明、Vite entry 修正、README 安裝與啟動步驟。

