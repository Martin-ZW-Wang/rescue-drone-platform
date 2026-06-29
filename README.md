# Rescue Drone Platform

Rescue Drone Platform 是一個結合 Laravel、Python Flask-SocketIO、Tello 無人機、YOLO/SEG/Pose 偵測與本地 Ollama/MCP 工具的救援偵測平台。

系統目標是讓使用者可以透過 Laravel Dashboard 控制 Python 無人機服務，觀看即時影像串流，偵測疑似救援事件，將事件寫入資料庫，並透過 MCP / Ollama 對近期事件做摘要分析。

## 目前狀態

這個專案已具備主要原始碼與設定樣板，但若要讓別人 clone 後完整重建，仍有幾個需要注意的限制：

- `README.md` 已提供從零建置流程。
- `.env.example` 已包含 Laravel、Drone API、Ollama 的基本環境變數。
- `composer.json`、`package.json`、migration 都已存在。
- 目前尚未提供 `python_service/requirements.txt`，所以本 README 先提供手動 `pip install` 指令。
- YOLO 權重檔不建議直接放進 Git，請依本 README 放到指定位置。
- `resources/js/drone-monitor.js` 目前在 Blade 中被 `@vite` 載入，但 `vite.config.js` 的 input 尚未列入該檔；若 production build 或測試遇到 Vite manifest 錯誤，請參考「已知限制」章節。

## 技術棧

- Laravel 12 / PHP 8.2
- Vite / Tailwind CSS / Blade
- Python Flask / Flask-SocketIO
- OpenCV / NumPy / Ultralytics YOLO
- DJI Tello / `djitellopy`
- SQLite 預設開發資料庫
- MCP server
- Ollama 本地模型

## 系統架構

```text
使用者
  -> Laravel Blade Dashboard
  -> Laravel API /api/drone/*
  -> DroneService
  -> Python Flask-SocketIO service :5001
  -> Tello / OpenCV / YOLO SEG / Pose
  -> Socket.IO frame/status/event 回傳前端
  -> Python POST /api/events/ingest
  -> Laravel RescueEvent
  -> SQLite / database
  -> Events 頁面與 MCP AI 摘要
```

主要角色分工：

- Laravel：Dashboard、事件頁面、API proxy、事件儲存、MCP AI 頁面。
- Python service：Tello 連線、影像串流、YOLO/SEG/Pose 偵測、Socket.IO 推送。
- MCP server：提供本地工具給 AI 使用，讀取 Laravel 的 drone status 與 rescue events。
- Ollama：本地 LLM，用於事件摘要與問答。

## 目錄結構

```text
rescue-drone-platform/
├─ app/                         Laravel Controller / Model / Service
├─ config/                      Laravel 設定
├─ database/                    migrations / factories / seeders
├─ docs/                        專案說明與稽核文件
├─ mcp_server/                  MCP tools server
├─ ai_orchestrator/             MCP client / Ollama 測試腳本
├─ python_service/              Flask-SocketIO drone service
├─ resources/                   Blade / JS / CSS
├─ routes/                      Laravel web/api routes
├─ public/                      Laravel public entry
├─ tests/                       Laravel tests
├─ .env.example                 環境變數樣板
├─ composer.json                PHP 依賴與啟動腳本
├─ package.json                 Node/Vite 依賴
└─ vite.config.js               Vite 設定
```

## 前置需求

建議環境：

- Windows 10/11 或 Linux/macOS
- PHP 8.2+
- Composer
- Node.js 20+ 與 npm
- Python 3.10+，建議 3.11 或 3.12
- SQLite PHP extension
- Tello 無人機與 Tello Wi-Fi，若只測 Laravel 頁面可先不連
- Ollama，若要使用 MCP AI 摘要功能

確認工具：

```powershell
php -v
composer --version
node -v
npm -v
python --version
```

## 從零安裝

### 1. 取得專案

```powershell
git clone <your-repo-url>
cd rescue-drone-platform
```

如果你是直接拿到資料夾，請進入專案根目錄：

```powershell
cd C:\xampp\htdocs\rescue-drone-platform
```

### 2. 安裝 Laravel 依賴

```powershell
composer install
```

### 3. 安裝 Node / Vite 依賴

```powershell
npm install
```

### 4. 建立 Laravel 環境檔

```powershell
copy .env.example .env
php artisan key:generate
```

確認 `.env` 至少包含：

```dotenv
APP_URL=http://localhost
DB_CONNECTION=sqlite
DRONE_API_BASE=http://127.0.0.1:5001
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=gemma3:4b
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

### 5. 建立 SQLite 資料庫

```powershell
New-Item -ItemType File -Force database/database.sqlite
php artisan migrate
```

Linux/macOS 可用：

```bash
touch database/database.sqlite
php artisan migrate
```

### 6. 安裝 Python drone service 依賴

目前專案尚未提供 `python_service/requirements.txt`。請先用以下指令安裝必要套件：

```powershell
python -m venv python_service/.venv
python_service\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install flask flask-cors flask-socketio opencv-python numpy requests ultralytics djitellopy
```

如果 PowerShell 阻擋啟動 venv，可改用：

```powershell
python_service\.venv\Scripts\python.exe -m pip install --upgrade pip
python_service\.venv\Scripts\python.exe -m pip install flask flask-cors flask-socketio opencv-python numpy requests ultralytics djitellopy
```

建議後續將上述套件整理成：

```text
python_service/requirements.txt
```

### 7. 安裝 MCP / AI orchestrator 依賴

```powershell
pip install -r mcp_server/requirements.txt
pip install -r ai_orchestrator/requirements.txt
```

如果使用剛才建立的 Python venv，請確認已啟用 venv 後再執行。

### 8. 準備 YOLO 模型權重

模型權重通常不建議直接提交到 Git。請將必要權重放到以下位置：

```text
python_service/SEG6-2.pt
python_service/yolov8n-pose.pt
```

目前程式引用位置：

- `python_service/drone.py` 的 `DETECT_MODEL_PATH = BASE_DIR / "SEG6-2.pt"`
- `python_service/drone.py` 的 `POSE_MODEL_PATH = BASE_DIR / "yolov8n-pose.pt"`

若你從 GitHub 下載本專案但沒有模型權重，Python service 會在載入 YOLO 模型時失敗。建議將模型放在 GitHub Releases、雲端硬碟或 Git LFS，並在正式發佈時補上下載連結。

## 啟動方式

### 方式 A：只啟動 Laravel + Vite

適合先檢查 Laravel 頁面與基本前端。

```powershell
composer dev
```

預設會啟動：

- Laravel server：`http://127.0.0.1:8000`
- Queue listener
- Laravel pail logs
- Vite dev server

### 方式 B：啟動完整 rescue drone stack

適合完整開發流程。

```powershell
composer dev:drone
```

這會同時啟動：

```text
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
npm run dev
python python_service/drone_server.py
python mcp_server/server.py
```

服務關係：

| 服務 | 預設位置 |
|---|---|
| Laravel | `http://127.0.0.1:8000` |
| Python drone service | `http://127.0.0.1:5001` |
| Ollama | `http://127.0.0.1:11434` |

### 方式 C：分開啟動 Python service

若你想分開除錯：

```powershell
python python_service/drone_server.py
```

Python service 預設啟動在：

```text
http://127.0.0.1:5001
```

可檢查：

```powershell
curl http://127.0.0.1:5001/status
```

### 方式 D：啟動 MCP server

```powershell
python mcp_server/server.py
```

手動測試 AI orchestrator：

```powershell
python ai_orchestrator/chat.py "摘要最近 10 筆救援事件"
```

## Ollama 設定

如果要使用 MCP AI 摘要功能，請先安裝並啟動 Ollama，並準備 `.env` 中設定的模型。

檢查模型：

```powershell
ollama list
```

如果使用預設模型：

```powershell
ollama pull gemma3:4b
```

`.env` 預設：

```dotenv
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=gemma3:4b
```

## 主要頁面

| 頁面 | 說明 |
|---|---|
| `/` | Drone Dashboard |
| `/events` | 救援事件列表 |
| `/events/{event}` | 救援事件詳情 |
| `/mcp` | MCP AI 問答與摘要頁 |

## API 摘要

Drone API：

| Method | Path | 說明 |
|---|---|---|
| GET | `/api/drone/status` | 取得 Python drone service 狀態 |
| POST | `/api/drone/start` | 啟動 SEG 偵測 |
| POST | `/api/drone/stop` | 停止 SEG 偵測 |
| POST | `/api/drone/pose/start` | 啟動 Pose 輔助 |
| POST | `/api/drone/pose/stop` | 停止 Pose 輔助 |
| GET | `/api/drone/debug/seg` | 執行 SEG debug diagnostics |

Event API：

| Method | Path | 說明 |
|---|---|---|
| POST | `/api/events/ingest` | Python 偵測事件寫入 Laravel |
| GET | `/api/events/recent` | 取得近期事件 |
| GET | `/api/events/{event}` | 取得單筆事件 |

MCP AI API：

| Method | Path | 說明 |
|---|---|---|
| GET | `/api/mcp/status` | 取得 MCP AI 狀態 |
| GET | `/api/mcp/events/recent` | 取得近期事件 |
| POST | `/api/mcp/summarize` | 呼叫 Ollama 摘要/問答 |

## 資料庫

主要資料表：

- `rescue_events`：儲存 Python 偵測到的救援事件。
- `system_logs`：系統日誌資料表，目前程式碼中未明顯大量使用，可能是預留功能。
- Laravel 預設：`users`、`cache`、`jobs` 等。

重建資料庫：

```powershell
php artisan migrate:fresh
```

如果需要 seed：

```powershell
php artisan db:seed
```

## 測試與檢查

PHP 語法檢查範例：

```powershell
php -l app/Services/DroneService.php
php -l routes/api.php
```

Laravel 測試：

```powershell
composer test
```

前端 build：

```powershell
npm run build
```

Python 語法檢查：

```powershell
python -m py_compile python_service/drone_server.py python_service/drone.py python_service/drone_runtime.py
```

## Git 與大型檔案規則

這個專案的 `.gitignore` 會排除：

- `.env`
- `vendor/`
- `node_modules/`
- `python_service/.venv/`
- `__pycache__/`
- `public/build/`
- `storage/framework/`
- `storage/logs/`
- `database/database.sqlite`
- `python_service/debug_outputs/`
- `*.pt`, `*.pth`, `*.onnx`

因此 clone 後需要自行安裝依賴、建立 `.env`、建立 SQLite DB、放置模型權重。

## 已知限制與待補強

### 1. 缺少 `python_service/requirements.txt`

目前 README 已提供手動安裝指令，但正式上 GitHub 前建議新增：

```text
python_service/requirements.txt
```

### 2. 模型權重沒有下載來源

目前必要權重位置為：

```text
python_service/SEG6-2.pt
python_service/yolov8n-pose.pt
```

正式發佈時應補下載連結、版本、檔案大小與校驗方式。

### 3. Vite manifest 可能缺少 `drone-monitor.js`

目前 `resources/views/layouts/app.blade.php` 載入：

```php
@vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/drone-monitor.js'])
```

但 `vite.config.js` 目前 input 只有：

```js
input: ['resources/css/app.css', 'resources/js/app.js']
```

如果 `npm run build` 或測試環境遇到 manifest 找不到 `resources/js/drone-monitor.js`，可採用其中一種修法：

1. 將 `resources/js/drone-monitor.js` 加進 `vite.config.js` input。
2. 或在 `resources/js/app.js` import `./drone-monitor`，並讓 Blade 只載入 `app.js`。

### 4. `/api/events/ingest` 尚未看到驗證機制

若部署到非本機環境，建議加入 shared secret、token 或簽章，避免任意來源寫入假事件。

### 5. Tello 硬體相依

Python service 需要連線到 Tello Wi-Fi 才能取得實際影像串流。若沒有 Tello，Laravel 頁面仍可開啟，但 drone status / stream 會回報連線錯誤。

## 後續建議

- 新增 `python_service/requirements.txt`。
- 新增 `docs/model-weights.md`，說明模型來源。
- 新增 `docs/architecture.md`，放正式架構圖。
- 新增 `docs/api.md`，整理 API payload。
- 精選少量偵測成果圖片放到 `docs/screenshots/`，不要提交整包 debug output。
- 將 `composer.json` 的 `name`、`description` 從 Laravel 預設值改成專案資訊。
