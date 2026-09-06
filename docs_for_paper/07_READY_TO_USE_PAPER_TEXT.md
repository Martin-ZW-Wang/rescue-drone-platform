# 可直接改寫進論文的文字草稿

以下內容為英文論文草稿，已刻意使用保守措辭。正式投稿前請再依實際資料集、實驗環境、作者資訊與 references 修正。

## 1. Abstract 約 100 words

This paper presents a local near-edge rescue drone prototype for injured target detection using YOLO-based instance segmentation and pose-assisted decision rules. The system processes drone video frames on a nearby local computer rather than on the drone itself. Candidate injured targets are detected with an instance segmentation model and further examined using pose-related cues, bounding-box geometry, keypoint confidence, and temporal consistency. A Flask-SocketIO Python service streams frames and events to a Laravel command-center dashboard, where confirmed Rescue Needed events are stored and reviewed. Preliminary results suggest that the prototype can support event-oriented rescue monitoring in simulated rescue-oriented scenes.

## 2. Introduction

Rapid visual assessment is important in rescue-oriented scenarios where operators must identify possible injured targets from aerial or elevated viewpoints. Small drones provide flexible sensing, but full onboard AI deployment can be constrained by payload, power, and computational resources. Therefore, this work investigates a local near-edge prototype in which video frames are processed on a nearby computer while the drone or camera provides the visual stream.

The proposed system combines YOLO-based instance segmentation with pose-assisted decision rules. Instead of relying only on bounding boxes, the system examines candidate regions using segmentation confidence, bounding-box geometry, keypoint information, torso angle, and temporal consistency. When the same candidate satisfies the rescue decision criteria across multiple frames, the system generates a Rescue Needed event.

The contribution of this work is a practical end-to-end prototype that connects real-time computer vision, event-oriented rescue output, a Laravel-based command-center dashboard, and a local MCP/Ollama event summarization interface. The prototype is intended as a preliminary feasibility study in simulated and augmented rescue-oriented scenes rather than a validated field-deployment system.

## 3. Materials and Methods

The system consists of a Python near-edge inference service, a Laravel web application, and a local AI summary layer. The Python service receives frames from the drone or camera runtime and processes them using a YOLO instance segmentation model. Candidate injured targets are identified from segmentation outputs. For each candidate region, pose estimation is applied when the region is sufficiently large and confident enough for keypoint analysis.

The decision logic uses multiple cues. Bounding-box geometry is used to identify horizontally oriented candidates. Pose-derived information, including torso angle and keypoint confidence, is used to support or reject a candidate. A temporal consistency rule is applied so that a rescue event is generated only after a candidate remains stable for a specified number of frames. In the current implementation, `RESCUE_CONFIRM_FRAMES` is set to 6, and the decision thresholds are implemented in `python_service/drone.py`.

Confirmed events are represented as structured event objects containing status, confidence, track ID, bounding-box information, debug metadata, and timestamp. The Python service posts these events to the Laravel `/api/events/ingest` endpoint, where they are stored as `RescueEvent` records.

## 4. System Architecture

The prototype follows a mixed web and near-edge service architecture. The Python Flask-SocketIO service performs video processing, YOLO segmentation, pose-assisted decision-making, and event generation. It exposes REST endpoints for status and control and uses SocketIO to stream frames, status updates, and events to the browser.

Laravel provides the command-center interface, API proxy, event ingestion endpoint, database storage, event review pages, and MCP AI page. The dashboard controls the Python service through Laravel API endpoints, while the Python service posts confirmed rescue events back to Laravel for persistent storage.

The MCP server and Ollama-based AI layer are used for event summarization. They query recent rescue events from Laravel and generate textual summaries for operator review. This AI layer does not perform visual inference; it operates on stored event records.

## 5. Experimental Results

The current repository contains the runtime prototype and model weights, but the formal training result files such as `results.csv`, PR/F1 curves, and confusion matrix images were not found during this scan. The following values were provided as project-level experimental results and should be verified against the original training outputs before final submission: box mAP@0.5 of approximately 0.895, mask mAP@0.5 of approximately 0.912, best box F1 of approximately 0.87 at confidence 0.596, and best mask F1 of approximately 0.88 at confidence 0.600.

The provided confusion matrix summary reports 299 true positives, 38 false positives, and 47 false negatives, corresponding to an injured-target recall of approximately 0.86. ROC and image-level custom precision-recall curves were not generated because the validation set did not include sufficient negative or background-only images.

## 6. Discussion

The prototype demonstrates that a local near-edge architecture can connect drone video streaming, YOLO-based segmentation, pose-assisted decision logic, event storage, and operator-facing review interfaces. The use of temporal consistency reduces the chance that a single unstable frame immediately becomes a rescue event. The Laravel event model also makes the system suitable for later review, annotation, and AI-assisted summarization.

However, the evaluation remains preliminary. Dataset scale, scene diversity, and validation design strongly affect the generalizability of the reported metrics. If training and validation frames come from similar scenes or consecutive video frames, the validation score may overestimate performance. The lack of negative/background-only validation images also prevents robust ROC and image-level false alarm analysis.

## 7. Conclusions

This work presents a local near-edge rescue drone prototype that integrates YOLO instance segmentation, pose-assisted injured target decision rules, real-time Flask-SocketIO streaming, Laravel event storage, and MCP/Ollama-based event summarization. The system is designed to support event-oriented rescue monitoring in simulated rescue-oriented scenes. Future work should expand the dataset, include negative scenes, measure latency and FPS under different hardware configurations, and evaluate the system in more diverse and realistic environments.

## 8. Limitations and Future Work

The current prototype has several limitations. First, the system is not a fully onboard edge-AI deployment; inference runs on a nearby local computer. Second, the current validation setting lacks sufficient background-only images, limiting image-level false-positive analysis. Third, pose estimation may be unstable under top-down viewpoints, occlusion, blankets, pillows, or unusual body postures. Fourth, the system has not yet been validated in real disaster environments. Future work should include dataset expansion, field-oriented testing, latency profiling, privacy-preserving data handling, and model deployment options for compact edge devices.

## 9. Data Availability Statement

The source code required to reproduce the software prototype is intended to be made available in a public repository. Large model weights, datasets, and raw image outputs are not recommended for direct inclusion in the Git repository and should be distributed through release assets, Git LFS, or external download instructions where appropriate.

## 10. Funding

Funding information should be added here. If no specific funding was received, use: “This research received no external funding.”

## 11. Institutional Review Board Statement

This section must be completed based on the actual data source. If human images are used, confirm whether institutional review, consent, anonymization, or dataset license statements are required.

## 12. Informed Consent Statement

This section must be completed based on whether identifiable human images or participant data were collected. Do not claim consent unless the consent process is documented.

## 13. Generative AI Acknowledgement

During the preparation of this manuscript, the authors used ChatGPT/Codex to assist with project documentation organization, wording refinement, and checklist generation. The authors reviewed and edited the content and take full responsibility for the final manuscript.

## 14. Conflicts of Interest

The authors declare no conflict of interest.

## 15. Abbreviations

| Abbreviation | Meaning |
|---|---|
| AI | Artificial Intelligence |
| API | Application Programming Interface |
| CV | Computer Vision |
| FPS | Frames Per Second |
| MCP | Model Context Protocol |
| ROI | Region of Interest |
| YOLO | You Only Look Once |

