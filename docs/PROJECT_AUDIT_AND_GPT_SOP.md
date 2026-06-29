# Rescue Drone Platform 專案檢查報告 + 給網頁版 GPT 的 SOP

更新日期：2026-05-31

## 1. 你這個專案是什麼

這是一個「Laravel 12 + Python Flask/SocketIO + Tello + YOLO」的救援偵測平台：

- Laravel：提供 Dashboard、事件列表、事件詳情與 API Proxy。
- Python 服務：連線 Tello、讀取影像、跑 YOLO 偵測與姿態、推送畫面與事件。
- 前端頁面：透過 WebSocket 顯示串流與狀態、呼叫 `/api/drone/*` 控制啟停。
- DB：儲存 `rescue_events`、`system_logs`。

## 2. 核心資料流（給 GPT 看最重要）

1. 使用者在 Dashboard 按「開始追蹤」。
2. 前端呼叫 Laravel `POST /api/drone/start`。
3. Laravel `DroneService` 轉發到 Python `POST http://127.0.0.1:5001/start`。
4. Python 啟動影像流與推論迴圈，透過 SocketIO 回傳：
   - `frame`（JPEG 影像）
   - `status`（connected/streaming/tracking/battery/error）
   - `event`（疑似受傷/需救援訊息）
5. Python 偵測到事件時，呼叫 Laravel `POST /api/events/ingest`。
6. Laravel 寫入 `rescue_events`，前台事件頁可瀏覽。

## 3. 我實際檢查過的項目

- 檔案結構、路由、Controller、Service、Model、Migration。
- 前端 JS 與 Blade。
- Python `drone_server.py` / `drone.py` / `drone_runtime.py`。
- 指令驗證：
  - `npm.cmd run build`：成功。
  - `php artisan route:list`：成功。
  - `php artisan test`：失敗（有可修復原因，見下方高優先問題）。

## 4. 風險與問題清單（依優先級）

### P1（高）Vite 載入設定不一致，導致 Feature Test 500

現象：`php artisan test` 失敗，錯誤為找不到 `resources/js/drone-monitor.js` 的 manifest entry。

根因：
- `resources/views/layouts/app.blade.php` 直接載入 `resources/js/drone-monitor.js`。
- 但 `vite.config.js` 的 input 沒有這個檔案。

影響：
- 測試環境或 build manifest 依賴流程會炸掉。

建議：
- 二選一：
  - 把 `drone-monitor.js` 加進 Vite input。
  - 或改由 `resources/js/app.js` `import './drone-monitor';`，Blade 只載入 `app.js`。

### P1（高）Python 事件推送在 deque 滿 200 後可能停止發送前端 event

現象：
- `python_service/drone_server.py` 的 `_event_loop()` 用「長度變化」判斷是否有新事件。
- `_events = deque(maxlen=200)` 滿了後長度固定 200，新事件也不再觸發 `n != last_len`。

影響：
- 前端可能突然收不到新事件，但後端其實還在產生事件。

建議：
- 改成比較最後事件的唯一鍵（例如 `ts` 或自行產生 `event_id`），而不是比較長度。

### P1（高）事件寫 Laravel 是同步 blocking，可能拖慢推論主迴圈

現象：
- `_push_event()` 內直接呼叫 `requests.post(... timeout=5)`。
- 該呼叫從 `_inference_loop()` 觸發。

影響：
- Laravel API 只要慢，推論 FPS 就可能顯著下降甚至卡頓。

建議：
- 將事件寫入改成非同步佇列（背景 worker thread / queue）。
- 或先進 memory queue，由獨立 thread 批次送出。

### P2（中）`/api/events/ingest` 沒有認證機制

現象：
- 目前 API 可直接寫入事件，無 token 驗證。

影響：
- 任意來源可灌假事件（若環境對外）。

建議：
- 至少加 shared secret header（例如 `X-INGEST-TOKEN`）。
- 或使用 Laravel Sanctum / 簽章機制。

### P2（中）Python 模型路徑一部分相對於 CWD，部署易壞

現象：
- `det_model` 用 `BASE_DIR / "15012.pt"`（安全）。
- `pose_model = YOLO("yolov8n-pose.pt")`（依賴執行目錄）。

影響：
- 從不同工作目錄啟動時可能找不到模型。

建議：
- 一律改成 `BASE_DIR / "yolov8n-pose.pt"`。

### P3（低）缺少 Python 依賴檔，團隊接手成本高

現象：
- 沒有 `requirements.txt` / `pyproject.toml`。

影響：
- 新機器重建環境不穩定。

建議：
- 補一份最小可重現依賴清單。

## 5. 跟網頁版 GPT 溝通的 SOP（可直接複製）

### Step A：先貼專案摘要

```text
你是我的技術協作助手，請用「先確認理解 → 提方案 → 給我可直接執行步驟」的格式回答。

我的專案：Rescue Drone Platform
技術棧：Laravel 12 (PHP 8.2) + Vite + Python Flask-SocketIO + djitellopy + Ultralytics YOLO
主要流程：前端呼叫 Laravel API，Laravel 轉發 Python；Python 推流與偵測事件，再回寫 Laravel /api/events/ingest。

已知問題：
1) php artisan test 會報 Vite manifest 找不到 resources/js/drone-monitor.js
2) Python event loop 用 deque length 判斷新事件，maxlen=200 後可能不再推事件
3) Python 寫 Laravel ingest 是同步 requests.post，可能拖慢推論

請先幫我做：
- 根因分析
- 最小改動修復方案
- 驗證清單（我要跑哪些命令、預期結果）
```

### Step B：提問模板（除錯時用）

```text
問題描述：
- 現象：
- 觸發步驟：
- 預期結果：
- 實際結果：

環境資訊：
- OS：Windows
- Laravel：12
- Python：3.x
- 啟動方式：
- 最近改動：

錯誤訊息（完整貼上）：
<貼完整 stack trace>

請輸出：
1) 最可能根因（由高到低）
2) 我現在先做的前三個檢查動作
3) 若要修 code，請提供 patch 級別修改（檔案 + 片段）
```

### Step C：要 GPT 幫你改程式時

```text
請直接提供「可貼回專案的最小修改版本」，並標示：
- 修改檔案路徑
- 修改前/修改後
- 為什麼這樣改
- 可能副作用
- 我該如何回滾
```

### Step D：每次都要求驗證標準

```text
修完後請給我驗證清單：
- 我要跑的命令
- 每條命令預期輸出關鍵字
- 若失敗，下一步該看哪個檔案/哪段 log
```

## 6. 建議你下一輪就先做的三件事

1. 先修 Vite entry 不一致（讓測試先恢復綠燈）。
2. 修 Python event loop 的新事件判斷邏輯（避免事件卡死）。
3. 將 ingest 寫入改為非同步（降低推論阻塞）。

## 7. 重要檔案定位

- Laravel 路由：`routes/api.php`, `routes/web.php`
- Proxy 與儲存：`app/Services/DroneService.php`, `app/Http/Controllers/Api/EventIngestController.php`
- 前端監控：`resources/js/drone-monitor.js`, `resources/views/dashboard.blade.php`
- 版面與 Vite：`resources/views/layouts/app.blade.php`, `vite.config.js`
- Python 主流程：`python_service/drone_server.py`, `python_service/drone.py`, `python_service/drone_runtime.py`
