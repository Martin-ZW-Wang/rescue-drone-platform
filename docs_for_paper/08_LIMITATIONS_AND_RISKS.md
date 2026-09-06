# 限制、風險與論文措辭注意事項

## 1. Dataset 風險

### 問題

目前 repo 內未找到正式 dataset、split 設定、`results.csv`、confusion matrix 或曲線圖。

### 為什麼是問題

論文若只有指標數字而沒有可追溯結果檔，審稿或簡報時難以證明實驗可重現。

### 建議

- 補充 `args.yaml`, `results.csv`, curves, confusion matrix。
- 說明 dataset 來源、影像數量、類別、train/validation split。
- 如果資料不能公開，至少提供統計表與取得限制說明。

## 2. Train/validation leakage 風險

### 問題

若 training 與 validation 來自同一影片的連續影格或高度相似場景，可能造成 scene leakage。

### 為什麼是問題

模型可能記住場景背景或姿勢，而非真正泛化到新場景。

### 建議

- 以 scene-level 或 subject-level split。
- 在 limitations 中揭露目前 split 方式。
- 補做跨場景 validation。

## 3. 缺 negative/background-only images

### 問題

使用者提供背景指出 ROC/custom PR 未生成，原因是 validation set 缺少 negative/background-only images。

### 為什麼是問題

無法完整評估 image-level false alarm rate、ROC、AUC。

### 建議

- 補收無傷者、空場景、雜物、床鋪、枕頭、毯子等 negative scenes。
- 補 image-level precision-recall / ROC。

## 4. Pose instability

### 問題

上視角、遮擋、低解析度、躺姿、毯子覆蓋可能讓 keypoints 不穩定。

### 為什麼是問題

Pose-assisted decision 若 keypoint 信心低，可能產生誤判或漏判。

### 建議

- 在論文中寫 pose is used as an auxiliary cue。
- 不要宣稱 pose 能可靠判定受傷狀態。
- 對 top-down and occluded cases 做 qualitative failure analysis。

## 5. Mask roughness / object confusion

### 問題

枕頭、毯子、背景紋理或地面物件可能與傷者外觀相似。

### 為什麼是問題

Segmentation mask 可能誤框或產生 false positive。

### 建議

- 增加 hard negative samples。
- 補 failure cases 圖。
- 在 discussion 中描述 segmentation limitation。

## 6. 非完全 onboard edge AI

### 問題

目前推論位於本機 Python service，而非直接跑在 Tello 或機載板。

### 為什麼是問題

若論文寫成 onboard edge AI，會與實作不符。

### 建議

統一使用：

- local near-edge inference
- nearby local computer
- near-edge prototype

避免使用：

- fully onboard inference
- onboard autonomous edge AI

## 7. Service coupling

### 問題

部分 host/port 寫死：

- `python_service/drone_server.py` 的 Laravel ingest URL。
- Dashboard 的 `socketBase`。

### 為什麼是問題

別人 clone 後如果 port 不同，會無法直接使用。

### 建議

- README 補充 port 關係。
- 後續可改成 `.env` 或 config-driven。

## 8. Frontend build risk

### 問題

`resources/views/layouts/app.blade.php` 載入 `resources/js/drone-monitor.js`，但 `vite.config.js` input 未列該檔。

### 為什麼是問題

production build 可能無法在 manifest 找到該 entry。

### 建議

- 後續修正 Vite input 或改由 `resources/js/app.js` import `drone-monitor.js`。
- GitHub README 應加入 build 驗證。

## 9. Security / deployment risk

### 問題

目前 API routes 未看到明確 auth middleware。

### 為什麼是問題

若公開部署，外部使用者可能控制 drone service 或讀取事件。

### 建議

- 論文中描述為 local prototype。
- 上線前加入 authentication, CSRF/API token, network restriction。

## 10. Privacy / consent

### 問題

若使用真人影像，可能涉及隱私與同意。

### 為什麼是問題

論文需要 IRB / informed consent / data availability 說明。

### 建議

- 使用模擬或去識別素材。
- 補充 consent statement。
- 不公開原始含人像資料。

