# 論文圖表規劃

## 1. 建議 Figures

| Figure | 標題建議 | 來源/狀態 | 備註 |
|---|---|---|---|
| Fig. 1 | Overall system architecture | 需繪製 | Laravel + Python + YOLO/Pose + MCP/Ollama |
| Fig. 2 | Local near-edge inference workflow | 需繪製 | frame -> segmentation -> pose -> temporal decision |
| Fig. 3 | Laravel dashboard interface | 需截圖 | `resources/views/dashboard.blade.php` |
| Fig. 4 | Rescue event list and detail view | 需截圖 | `resources/views/events/` |
| Fig. 5 | MCP AI event summary interface | 需截圖 | `resources/views/mcp/index.blade.php` |
| Fig. 6 | Box and mask F1 curves | 未找到檔案 | 需補 `BoxF1_curve.png`, `MaskF1_curve.png` |
| Fig. 7 | Precision-recall curves | 未找到檔案 | 需補 `BoxPR_curve.png`, `MaskPR_curve.png` |
| Fig. 8 | Confusion matrix | 未找到檔案 | 需補 `confusion_matrix.png` |
| Fig. 9 | Qualitative detection examples | 可從正式驗證輸出選圖 | 不建議直接用 debug output 當正式結果 |
| Fig. 10 | Failure cases | 需整理 | pillow/blanket/background confusion, top-down pose instability |

## 2. 建議 Tables

| Table | 標題建議 | 內容 |
|---|---|---|
| Table 1 | Software and hardware environment | Laravel, Python, Flask-SocketIO, YOLO, Tello, local machine |
| Table 2 | System modules and responsibilities | Laravel, Python service, MCP, Ollama |
| Table 3 | Key detection parameters | `RESCUE_CONFIRM_FRAMES`, `BBOX_HORIZONTAL_RATIO`, `TORSO_ANGLE_DEG`, thresholds |
| Table 4 | YOLO detection and segmentation metrics | Box mAP, Mask mAP, F1 |
| Table 5 | Confusion matrix summary | TP, FP, FN, recall |
| Table 6 | Limitations and mitigation plan | dataset, deployment, latency, privacy |

## 3. Figure caption 草稿

### Fig. 1

**Overall architecture of the proposed local near-edge rescue drone prototype.** The Python Flask-SocketIO service performs YOLO-based instance segmentation and pose-assisted event decision near the drone, while Laravel provides the dashboard, API proxy, event storage, and MCP/Ollama-based event summary interface.

### Fig. 2

**Injured target detection workflow.** Each frame is processed by YOLO instance segmentation to identify injured target candidates. Candidate regions are further examined using pose-related cues, bounding-box ratio, torso angle, keypoint confidence, and temporal consistency before a Rescue Needed event is generated.

### Fig. 3

**Laravel dashboard for real-time monitoring and control.** The dashboard receives image frames, status updates, and rescue events from the Python near-edge service through SocketIO and controls the service through Laravel API proxy endpoints.

### Fig. 4

**Event-oriented rescue output.** Confirmed events are stored as `RescueEvent` records in Laravel and can be reviewed through event list and detail pages.

## 4. Table 3 範例內容

| Parameter | Value | Source |
|---|---:|---|
| `RESCUE_CONFIRM_FRAMES` | 6 | `python_service/drone.py` |
| `SUSPECT_FRAMES` | 2 | `python_service/drone.py` |
| `BBOX_HORIZONTAL_RATIO` | 0.82 | `python_service/drone.py` |
| `TORSO_ANGLE_DEG` | 42 | `python_service/drone.py` |
| `KP_CONF` | 0.5 | `python_service/drone.py` |
| `POSE_MIN_CONF` | 0.5 | `python_service/drone.py` |
| `DETECT_IMGSZ` | 640 | `python_service/drone.py` |
| `DETECT_CONF` | 0.25 | `python_service/drone.py` |

## 5. 圖片資產整理建議

建議建立：

```text
docs/figures/
  architecture/
  dashboard/
  experiments/
  qualitative/
  failure_cases/
```

注意：

- 不要把大量原始 screenshots 全部放進 Git。
- 正式論文圖應選少量、有代表性、可追溯來源的圖片。
- 若使用真實人物影像，需處理隱私、同意與去識別化。

