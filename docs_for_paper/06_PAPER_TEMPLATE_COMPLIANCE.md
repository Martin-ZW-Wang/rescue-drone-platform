# MDPI / Engineering Proceedings 論文模板符合性檢查

## 1. 基本資訊

| 項目 | 建議內容 | 狀態 |
|---|---|---|
| Paper type | Conference paper / Engineering Proceedings style | 待確認投稿規範 |
| Title | YOLO-Based Instance Segmentation and Pose-Assisted Injured Target Detection for a Local Near-Edge Rescue Drone Prototype | 已有建議 |
| Authors | 作者與單位 | 待補 |
| Corresponding author | email | 待補 |
| Abstract | 約 100 words | 已於 `07_READY_TO_USE_PAPER_TEXT.md` 提供草稿 |
| Keywords | 3-10 個 | 建議補 |

建議 keywords：

- rescue drone
- YOLO
- instance segmentation
- pose estimation
- near-edge inference
- Flask-SocketIO
- Laravel dashboard
- event detection

## 2. 建議章節

| 章節 | 狀態 | 備註 |
|---|---|---|
| Abstract | 已可草擬 | 約 100 words |
| Keywords | 待補 | 3-10 個 |
| Introduction | 已可草擬 | 強調搜救場景與 near-edge 原型 |
| Materials and Methods | 已可草擬 | 描述 YOLO/Pose/event decision |
| System Architecture | 已可草擬 | Laravel + Python + MCP/Ollama |
| Experimental Results | 部分可草擬 | 缺正式結果檔驗證 |
| Discussion | 已可草擬 | 可放限制與誤判分析 |
| Conclusions | 已可草擬 | 強調 preliminary feasibility |
| Data Availability Statement | 待決定 | 權重/資料集是否公開 |
| Funding | 待補 | 若無可寫 Not applicable |
| Institutional Review Board Statement | 待補 | 若非人體受試者需謹慎說明 |
| Informed Consent Statement | 待補 | 若使用真人影像需補同意 |
| Generative AI Disclosure | 建議加入 | 說明使用 ChatGPT/Codex 協助文件整理 |
| Conflicts of Interest | 待補 | 若無可寫 The authors declare no conflict of interest |
| Abbreviations | 建議加入 | YOLO, ROI, MCP, API 等 |

## 3. 目前最不符合模板的缺口

1. 缺正式 experiment assets。
2. 缺 dataset description。
3. 缺 train/validation split 說明。
4. 缺 negative/background-only images 說明。
5. 缺 hardware / runtime environment。
6. 缺 latency / FPS 實測。
7. 缺 IRB / informed consent 判斷。
8. 缺 references。

## 4. 生成式 AI 揭露建議

可使用以下文字，依實際情況修改：

> During the preparation of this manuscript, the authors used ChatGPT/Codex to assist with project documentation organization, wording refinement, and checklist generation. The authors reviewed and edited the content and take full responsibility for the final manuscript.

## 5. Data availability 建議寫法

若資料集與權重不公開：

> The source code required to reproduce the software prototype is planned to be made available in a public repository. Model weights and image datasets are not included in the repository due to file size, privacy, and licensing considerations. Instructions for obtaining or training the required models will be provided where applicable.

若資料集可公開：

> The source code and selected non-sensitive demonstration assets are available in the project repository. The training configuration and evaluation outputs are provided in the supplementary material. Large model weights are distributed separately via release assets or external download links.

## 6. IRB / consent 風險

如果資料集中有人像或模擬傷者影像：

- 需要確認是否涉及可識別人物。
- 需要確認是否有同意書或影像授權。
- 若是公開資料集或自製模擬圖，也需描述來源與使用條件。

不建議在未確認前寫：

- “No human subjects were involved”
- “All participants provided consent”
- “The dataset is fully anonymized”

除非有明確證據。

