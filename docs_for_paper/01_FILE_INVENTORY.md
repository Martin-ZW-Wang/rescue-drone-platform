# 論文專案檔案盤點

## 1. 核心程式檔案

### Laravel routes

| 路徑 | 用途 | 論文關聯 |
|---|---|---|
| `routes/web.php` | Dashboard、events、MCP 頁面路由 | command-center UI |
| `routes/api.php` | Drone proxy、event ingest/query、MCP API | system integration |

### Laravel controllers / service / model

| 路徑 | 用途 | 論文關聯 |
|---|---|---|
| `app/Services/DroneService.php` | Laravel 呼叫 Python drone service 的 proxy layer | Laravel 與 Python 角色分工 |
| `app/Http/Controllers/Api/DroneApiController.php` | `/api/drone/*` 控制入口 | Dashboard 控制 Python service |
| `app/Http/Controllers/Api/EventIngestController.php` | 接收 Python POST 事件 | RescueEvent event-oriented output |
| `app/Http/Controllers/Api/EventQueryController.php` | 查詢近期與單筆事件 | event review / dashboard |
| `app/Http/Controllers/Api/McpAiController.php` | Laravel 端 Ollama summary 與 web/general/rescue mode | AI event summary |
| `app/Models/RescueEvent.php` | 事件資料模型 | 實驗事件儲存 |

### Laravel views / frontend

| 路徑 | 用途 | 論文關聯 |
|---|---|---|
| `resources/views/layouts/app.blade.php` | 共用 layout，含 `@vite(...)` | UI shell |
| `resources/views/dashboard.blade.php` | 影像串流與控制頁 | command-center screenshot |
| `resources/views/events/index.blade.php` | Rescue events list | event review screenshot |
| `resources/views/events/show.blade.php` | 單筆事件細節 | event detail screenshot |
| `resources/views/mcp/index.blade.php` | MCP AI chat / summary 頁 | AI assistant screenshot |
| `resources/js/drone-monitor.js` | Dashboard SocketIO / API 操作 | realtime UI logic |
| `resources/js/app.js` | Vite app entry | frontend entry |
| `resources/css/app.css` | frontend CSS entry | frontend styling |

注意：`resources/views/layouts/app.blade.php` 目前載入 `resources/js/drone-monitor.js`，但 `vite.config.js` 的 input 只列出 `resources/css/app.css` 與 `resources/js/app.js`。這是論文部署文件與 GitHub README 需要提醒或修正的風險點。

### Python near-edge service

| 路徑 | 用途 | 論文關聯 |
|---|---|---|
| `python_service/drone.py` | YOLO segmentation / pose / event decision 核心 | 方法章核心 |
| `python_service/drone_server.py` | Flask-SocketIO service, REST control, event queue | 系統架構核心 |
| `python_service/drone_runtime.py` | Tello/runtime abstraction | drone runtime |
| `python_service/SEG6-2.pt` | segmentation model weights | 不建議直接進 Git，可放 Release/LFS |
| `python_service/yolov8n-pose.pt` | pose model weights | 不建議直接進 Git，可放 Release/LFS |
| `python_service/SEG.pt`, `python_service/15012.pt` | 其他模型權重 | 需人工確認用途 |

### MCP / AI

| 路徑 | 用途 | 論文關聯 |
|---|---|---|
| `mcp_server/server.py` | MCP tools：status/events/detail/summary | AI summary integration |
| `mcp_server/requirements.txt` | MCP Python dependencies | 可重建性 |
| `ai_orchestrator/chat.py` | CLI-like MCP client + Ollama prompt | AI orchestrator |
| `ai_orchestrator/requirements.txt` | AI orchestrator dependencies | 可重建性 |

## 2. 設定與可重建性檔案

| 路徑 | 狀態 | 說明 |
|---|---|---|
| `README.md` | 必留 | 專案主要說明 |
| `.env.example` | 必留 | 包含 `DRONE_API_BASE`, `OLLAMA_BASE_URL`, `OLLAMA_MODEL` |
| `composer.json` | 必留 | Laravel / PHP dependency 與 scripts |
| `composer.lock` | 必留 | 固定 PHP dependency 版本 |
| `package.json` | 必留 | Vite / frontend dependency |
| `package-lock.json` 或其他 lock file | 若存在則必留 | 固定 Node dependency 版本 |
| `vite.config.js` | 必留 | 前端 build 設定 |
| `database/migrations/` | 必留 | DB schema 建立依據 |

## 3. 文件與展示素材

| 路徑 | 建議 | 說明 |
|---|---|---|
| `docs/PROJECT_AUDIT_AND_GPT_SOP.md` | 可選保留 | 專案盤點與交接文件 |
| `mcp_server/README.md` | 必留或合併進主 README | MCP server 啟動說明 |
| `docs_for_paper/` | 新增保留 | 論文整理文件 |
| `python_service/debug_outputs/` | 不建議上 Git | debug 圖像輸出，非正式 figure |

## 4. 目前未找到的實驗結果檔案

本次掃描未在 repo 內找到下列論文常用訓練/驗證輸出，建議後續補放到 `docs/experiments/` 或 `paper_assets/experiments/`，或在 README 說明取得方式：

- `args.yaml`
- `results.csv`
- `results.png`
- `BoxF1_curve.png`
- `BoxP_curve.png`
- `BoxPR_curve.png`
- `BoxR_curve.png`
- `MaskF1_curve.png`
- `MaskP_curve.png`
- `MaskPR_curve.png`
- `MaskR_curve.png`
- `confusion_matrix.png`
- `confusion_matrix_normalized.png`
- `labels.jpg`
- `confidence_histogram.png`
- `detection_count_histogram.png`
- `mask_area_histogram.png`
- `pca_embeddings.png`
- `tsne_embeddings.png`
- `validation_image_level_scores.csv`
- `embedding_projection_index.csv`
- `roc_not_generated.txt`

