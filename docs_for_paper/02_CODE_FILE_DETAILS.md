# 核心程式檔案細節

## 1. Python detection core：`python_service/drone.py`

此檔案是論文方法章最重要的程式依據。

目前確認的關鍵設定：

- `DETECT_MODEL_PATH = BASE_DIR / "SEG6-2.pt"`
- `POSE_MODEL_PATH = BASE_DIR / "yolov8n-pose.pt"`
- `DEBUG_OUTPUT_DIR = BASE_DIR / "debug_outputs"`
- `RESCUE_CONFIRM_FRAMES = 6`
- `SUSPECT_FRAMES = 2`
- `BBOX_HORIZONTAL_RATIO = 0.82`
- `TORSO_ANGLE_DEG = 42`
- `KP_CONF = 0.5`
- `TRACK_GRID = 80`
- `TRACK_FORGET_SEC = 4.0`
- `POSE_MIN_CONF = 0.5`
- `POSE_MIN_AREA = 128 * 128`
- `DETECT_IMGSZ = 640`
- `DETECT_CONF = 0.25`
- `SEG_INJURED_CONF = 0.25`

可寫入論文的方法描述：

1. 系統先使用 YOLO segmentation model 對影像進行 injured target candidate 偵測。
2. 對候選 ROI 執行 pose estimation。
3. 結合 bounding box aspect / horizontal ratio、torso angle、keypoint confidence 與 temporal consistency。
4. 當同一 track 累積到指定確認門檻後，產生 `Rescue Needed` 事件。

應謹慎描述：

- 目前程式是 heuristic-assisted detection pipeline，不是單純 end-to-end classification。
- Pose 用於輔助判斷，不應宣稱能準確診斷傷勢。
- 事件判斷是搜救提示訊號，不是醫療判定。

## 2. Python service：`python_service/drone_server.py`

此檔案負責讓 detection core 成為可被 Laravel 與前端使用的服務。

已確認功能：

- Flask app 與 SocketIO service。
- host `0.0.0.0`，port `5001`。
- CORS origins 包含 `http://127.0.0.1:8000` 與 `http://localhost:8000`。
- REST endpoints：
  - `/status`
  - `/debug/seg`
  - `/start`
  - `/stop`
  - `/pose/start`
  - `/pose/stop`
- SocketIO events：
  - `frame`
  - `status`
  - `event`
- Python 事件儲存目標：
  - `LARAVEL_EVENT_INGEST_URL = "http://127.0.0.1:8000/api/events/ingest"`

可寫入論文的描述：

> The near-edge Python service exposes REST control endpoints and SocketIO streams. It continuously pushes encoded video frames and system status to the Laravel dashboard, while confirmed rescue events are posted to the Laravel ingestion endpoint for persistent storage.

風險與限制：

- `LARAVEL_EVENT_INGEST_URL` 目前寫死在程式中，建議未來改為環境變數。
- `_events = deque(maxlen=200)` 與事件推送邏輯需注意滿佇列後是否仍能穩定推送新事件。
- `requests.post(..., timeout=5)` 可能在 Laravel 不可用時造成 event save thread 等待。

## 3. Laravel API routes：`routes/api.php`

已確認 API 類型：

- Drone control/status：
  - `GET /api/drone/status`
  - `POST /api/drone/start`
  - `POST /api/drone/stop`
  - `POST /api/drone/pose/start`
  - `POST /api/drone/pose/stop`
  - `GET /api/drone/debug/seg`
- Event ingest/query：
  - `POST /api/events/ingest`
  - `GET /api/events/recent`
  - `GET /api/events/{event}`
- MCP AI：
  - `GET /api/mcp/status`
  - `GET /api/mcp/events/recent`
  - `POST /api/mcp/summarize`

論文用途：

- 說明 Laravel 作為 API proxy 與 event persistence layer。
- 說明 Python 與 Laravel 的解耦方式。

## 4. Laravel event ingestion：`EventIngestController.php`

此 controller 驗證並儲存偵測事件。可支援論文描述中的 event-oriented output。

已確認欄位：

- `event_type`
- `tid`
- `status`
- `conf`
- `message`
- `bbox_json`
- `dbg_json`
- `event_time`
- `review_status`
- `review_note`

對應 migration：

- `database/migrations/2026_03_28_134157_create_rescue_events_table.php`

## 5. Laravel event query：`EventQueryController.php`

提供近期事件與單筆事件查詢。

論文用途：

- 可作為 Dashboard event review 與 MCP summary 的資料來源。

## 6. Laravel drone proxy：`DroneService.php` 與 `DroneApiController.php`

`DroneService.php` 透過 `config('services.drone.base_url')` 呼叫 Python service，預設為 `http://127.0.0.1:5001`。

相關 `.env.example`：

- `DRONE_API_BASE=http://127.0.0.1:5001`

論文用途：

- Laravel 不直接執行 CV 推論，而是以 proxy 方式控制 Python near-edge inference service。

## 7. Frontend monitor：`resources/js/drone-monitor.js`

已確認功能：

- 讀取 `window.DRONE_CONFIG`。
- 連線 `SOCKET_BASE = http://127.0.0.1:5001`。
- 接收 SocketIO `frame` 並以 `<img id="stream">` 顯示。
- 接收 `status` 更新 Dashboard。
- 接收 `event` 顯示救援事件。
- 呼叫 `/api/drone/start`, `/api/drone/stop`, `/api/drone/pose/start`, `/api/drone/pose/stop`。

重要風險：

- `resources/views/layouts/app.blade.php` 有 `@vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/drone-monitor.js'])`。
- 但 `vite.config.js` input 目前只有 `resources/css/app.css` 與 `resources/js/app.js`。
- 這可能導致 production build manifest 找不到 `resources/js/drone-monitor.js`。

## 8. MCP / AI

### `mcp_server/server.py`

已確認 tool：

- `get_drone_status`
- `get_recent_events`
- `get_event_detail`
- `summarize_recent_events`

環境變數：

- `RESCUE_LARAVEL_BASE`
- `OLLAMA_BASE_URL`
- `OLLAMA_MODEL`

### `ai_orchestrator/chat.py`

功能：

- 以 MCP client 啟動 `mcp_server/server.py`。
- 根據問題選擇 MCP tool。
- 將 tool 結果交給 Ollama `/api/generate`。

論文用途：

- 可描述為 local AI event summarization layer。
- 不應描述為雲端大型模型服務，除非未來改用雲端 API。

