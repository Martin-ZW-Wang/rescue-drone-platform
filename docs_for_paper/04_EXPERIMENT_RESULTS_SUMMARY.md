# 實驗結果與訓練輸出整理

## 1. 目前倉庫內找到的模型與輸出

### 模型權重

| 路徑 | 狀態 | 說明 |
|---|---|---|
| `python_service/SEG6-2.pt` | 已找到 | `python_service/drone.py` 目前使用的 segmentation model |
| `python_service/yolov8n-pose.pt` | 已找到 | `python_service/drone.py` 目前使用的 pose model |
| `python_service/SEG.pt` | 已找到 | 疑似舊版或備用 segmentation model，需人工確認 |
| `python_service/15012.pt` | 已找到 | 疑似舊版或備用 model，需人工確認 |
| `yolov8n-pose.pt` | 已找到 | root level pose model，疑似重複或手動測試用，需人工確認 |

建議：模型權重不建議直接進 Git，一般可用 Git LFS、GitHub Release assets、雲端下載連結或 README 指令管理。

### Debug output

| 路徑 | 狀態 | 說明 |
|---|---|---|
| `python_service/debug_outputs/` | 已找到 | debug image outputs，不建議當作正式實驗圖表 |

## 2. 本次掃描未找到的正式訓練/驗證結果

本次掃描未找到以下檔案，因此不能從 repo 內直接驗證訓練曲線與統計表：

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

建議後續新增：

```text
docs/experiments/
  training/
    args.yaml
    results.csv
    results.png
    BoxF1_curve.png
    MaskF1_curve.png
    ...
  validation/
    confusion_matrix.png
    confusion_matrix_normalized.png
    validation_image_level_scores.csv
    roc_not_generated.txt
```

## 3. 使用者提供的實驗數字

以下數字來自使用者提供的任務背景；目前 repo 內未找到 `results.csv` 或對應曲線圖可交叉驗證，因此論文使用時應標註為待原始結果檔確認。

| 指標 | 數值 | 驗證狀態 |
|---|---:|---|
| Box mAP@0.5 | 約 0.895 | 使用者提供，未由 repo 結果檔驗證 |
| Mask mAP@0.5 | 約 0.912 | 使用者提供，未由 repo 結果檔驗證 |
| Box best F1 | 約 0.87 at confidence 0.596 | 使用者提供，未由 repo 結果檔驗證 |
| Mask best F1 | 約 0.88 at confidence 0.600 | 使用者提供，未由 repo 結果檔驗證 |
| Confusion matrix TP | 299 | 使用者提供，未由 repo 結果檔驗證 |
| Confusion matrix FP | 38 | 使用者提供，未由 repo 結果檔驗證 |
| Confusion matrix FN | 47 | 使用者提供，未由 repo 結果檔驗證 |
| Normalized injured recall | 約 0.86 | 使用者提供，未由 repo 結果檔驗證 |

## 4. ROC / custom PR 狀態

根據使用者提供背景：

- ROC / custom PR 未生成。
- 原因：validation set 缺少 negative/background-only images。

論文中建議寫法：

> ROC and image-level custom precision-recall curves were not generated because the current validation set did not include sufficient background-only or negative samples. Therefore, the evaluation primarily reports YOLO detection and segmentation metrics, confusion matrix statistics, and qualitative error analysis.

## 5. 結果章建議結構

1. Dataset and validation setting
2. YOLO box and mask performance
3. Confusion matrix analysis
4. Confidence threshold and F1 curves
5. Qualitative detection examples
6. Failure cases
7. System-level event output demonstration

## 6. 不建議直接使用的資料

- `python_service/debug_outputs/`：可作為 debug 或示意，不應宣稱為正式實驗結果。
- 未標記來源的 screenshots：可作展示，不應作為 quantitative evidence。
- 未確認 split 的 validation 圖：若有連續影格或同場景洩漏風險，需要在 limitations 中揭露。

