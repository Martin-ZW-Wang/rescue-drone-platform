from pathlib import Path
import cv2
import time
import math
import numpy as np
from ultralytics import YOLO

# =========================
# 1) Models
# =========================
BASE_DIR = Path(__file__).resolve().parent
DETECT_MODEL_PATH = BASE_DIR / "SEG6-2.pt"
POSE_MODEL_PATH = BASE_DIR / "yolov8n-pose.pt"
DEBUG_OUTPUT_DIR = BASE_DIR / "debug_outputs"

det_model = YOLO(str(DETECT_MODEL_PATH))
pose_model = None
pose_enabled = False


def set_pose_enabled(enabled: bool):
    global pose_enabled
    pose_enabled = bool(enabled)


def get_pose_enabled() -> bool:
    return pose_enabled


def _get_pose_model():
    global pose_model
    if pose_model is None:
        pose_model = YOLO(str(POSE_MODEL_PATH))
    return pose_model

# =========================
# 2) Parameters
# =========================
# 比原本更容易累積到疑似受傷 / 待救援
SUSPECT_THRESHOLD = 2
RESCUE_THRESHOLD = 6

# 判定稍微放寬
BBOX_HORIZONTAL_RATIO = 0.82
TORSO_ANGLE_DEG = 42
KP_CONF = 0.5

# 簡易 tracking
TRACK_GRID = 80
TRACK_FORGET_SEC = 4.0
TRACK_IOU_THRESHOLD = 0.20
TRACK_CENTER_DIST_THRESHOLD = 120.0

# 效能優化：保留，但不要太保守
POSE_MIN_CONF = 0.5       # 原本 0.45，太高
POSE_MIN_AREA = 128 * 128   # 原本 75*75，太大
POSE_EVERY_N_FRAMES = 1    # 每次 process_frame 都允許跑 pose
DETECT_IMGSZ = 640         # Match the SEG training image size.
SEG_CONF = 0.5            # Runtime injured-person segmentation confidence.
DETECT_IOU = 0.5          # YOLO/NMS IoU threshold; separate from temporal matching.

# 全域 tracking 狀態
tracks = {}
next_track_id = 1
frame_counter = 0

# =========================
# 3) Utilities
# =========================
def angle_with_vertical(p1, p2):
    dx = p2[0] - p1[0]
    dy = p2[1] - p1[1]
    return math.degrees(math.atan2(abs(dx), abs(dy) + 1e-6))

def simple_track_id(cx, cy, grid=TRACK_GRID):
    return (int(cx // grid), int(cy // grid))


def bbox_center(xyxy):
    x1, y1, x2, y2 = [float(v) for v in xyxy]
    return ((x1 + x2) / 2.0, (y1 + y2) / 2.0)


def center_distance(center_a, center_b):
    return math.hypot(center_a[0] - center_b[0], center_a[1] - center_b[1])


def get_event_state(evidence):
    if evidence >= RESCUE_THRESHOLD:
        return "Rescue Needed"
    if evidence >= SUSPECT_THRESHOLD:
        return "Suspected Injury"
    if evidence > 0:
        return "Detected Injury"
    return None


def bbox_iou(a, b):
    ax1, ay1, ax2, ay2 = [float(v) for v in a]
    bx1, by1, bx2, by2 = [float(v) for v in b]
    inter_x1 = max(ax1, bx1)
    inter_y1 = max(ay1, by1)
    inter_x2 = min(ax2, bx2)
    inter_y2 = min(ay2, by2)
    inter_w = max(0.0, inter_x2 - inter_x1)
    inter_h = max(0.0, inter_y2 - inter_y1)
    inter_area = inter_w * inter_h
    area_a = max(0.0, ax2 - ax1) * max(0.0, ay2 - ay1)
    area_b = max(0.0, bx2 - bx1) * max(0.0, by2 - by1)
    union = area_a + area_b - inter_area
    return 0.0 if union <= 0.0 else inter_area / union


def bbox_center_shift(a, b):
    ax1, ay1, ax2, ay2 = [float(v) for v in a]
    bx1, by1, bx2, by2 = [float(v) for v in b]
    acx, acy = (ax1 + ax2) / 2.0, (ay1 + ay2) / 2.0
    bcx, bcy = (bx1 + bx2) / 2.0, (by1 + by2) / 2.0
    return math.hypot(acx - bcx, acy - bcy)


def refine_bbox_from_seg_mask(det, index, fallback_xyxy, frame_shape):
    if det.masks is None or det.masks.xy is None or index >= len(det.masks.xy):
        return shrink_bbox(fallback_xyxy, frame_shape)

    points = det.masks.xy[index]
    if points is None or len(points) == 0:
        return shrink_bbox(fallback_xyxy, frame_shape)

    xs = points[:, 0]
    ys = points[:, 1]
    return clamp_bbox((float(xs.min()), float(ys.min()), float(xs.max()), float(ys.max())), frame_shape)


def shrink_bbox(xyxy, frame_shape, ratio=0.06):
    x1, y1, x2, y2 = xyxy
    w = max(1.0, x2 - x1)
    h = max(1.0, y2 - y1)
    return clamp_bbox((
        x1 + w * ratio,
        y1 + h * ratio,
        x2 - w * ratio,
        y2 - h * ratio,
    ), frame_shape)


def clamp_bbox(xyxy, frame_shape):
    x1, y1, x2, y2 = xyxy
    height, width = frame_shape[:2]
    return (
        max(0.0, min(float(x1), width - 1.0)),
        max(0.0, min(float(y1), height - 1.0)),
        max(0.0, min(float(x2), width - 1.0)),
        max(0.0, min(float(y2), height - 1.0)),
    )


def enhance_low_light_bgr(frame_bgr: np.ndarray) -> np.ndarray:
    lab = cv2.cvtColor(frame_bgr, cv2.COLOR_BGR2LAB)
    l_channel, a_channel, b_channel = cv2.split(lab)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    enhanced_l = clahe.apply(l_channel)
    enhanced = cv2.merge((enhanced_l, a_channel, b_channel))
    return cv2.cvtColor(enhanced, cv2.COLOR_LAB2BGR)


def run_seg_diagnostics(frame_bgr: np.ndarray, output_dir: Path | None = None) -> dict:
    output_dir = output_dir or DEBUG_OUTPUT_DIR
    output_dir.mkdir(parents=True, exist_ok=True)

    stamp = time.strftime("%Y%m%d_%H%M%S")
    cv2.imwrite(str(output_dir / f"{stamp}_raw_bgr.jpg"), frame_bgr)

    variants = {
        "bgr_current": frame_bgr,
        "rgb_channels_swapped": cv2.cvtColor(frame_bgr, cv2.COLOR_BGR2RGB),
        "low_light_enhanced_bgr": enhance_low_light_bgr(frame_bgr),
    }
    thresholds = [0.05, 0.10, 0.20, SEG_CONF]

    report = {
        "model": str(DETECT_MODEL_PATH),
        "task": getattr(det_model, "task", None),
        "names": getattr(det_model, "names", None),
        "imgsz": DETECT_IMGSZ,
        "seg_conf": SEG_CONF,
        "detect_iou": DETECT_IOU,
        "frame_shape": list(frame_bgr.shape),
        "frame_bgr_mean": [float(v) for v in frame_bgr.reshape(-1, 3).mean(axis=0)],
        "runs": [],
    }

    for variant_name, image in variants.items():
        for conf in thresholds:
            result = det_model.predict(
                image,
                conf=conf,
                iou=DETECT_IOU,
                imgsz=DETECT_IMGSZ,
                max_det=20,
                verbose=False,
            )[0]

            box_count = 0 if result.boxes is None else len(result.boxes)
            mask_count = 0 if result.masks is None or result.masks.xy is None else len(result.masks.xy)
            best_conf = None
            if result.boxes is not None and len(result.boxes) > 0 and result.boxes.conf is not None:
                best_conf = float(result.boxes.conf.max().cpu().item())

            output_name = f"{stamp}_{variant_name}_conf{str(conf).replace('.', '')}.jpg"
            output_path = output_dir / output_name
            annotated = result.plot()
            cv2.imwrite(str(output_path), annotated)

            report["runs"].append({
                "variant": variant_name,
                "conf": conf,
                "boxes": box_count,
                "masks": mask_count,
                "best_conf": best_conf,
                "image": str(output_path),
            })

    return report

def draw_skeleton_precise(frame, kpts, conf_thr=KP_CONF):
    edges = [
        (5, 7), (7, 9), (6, 8), (8, 10),
        (5, 6),
        (5, 11), (6, 12),
        (11, 12),
        (11, 13), (13, 15),
        (12, 14), (14, 16),
        (0, 1), (1, 3),
        (0, 2), (2, 4),
        (0, 5), (0, 6)
    ]

    for a, b in edges:
        xa, ya, ca = kpts[a]
        xb, yb, cb = kpts[b]
        if ca > conf_thr and cb > conf_thr:
            cv2.line(frame, (int(xa), int(ya)), (int(xb), int(yb)), (0, 255, 0), 2)

    for i in range(kpts.shape[0]):
        x, y, c = kpts[i]
        if c > conf_thr:
            pt = (int(x), int(y))
            cv2.circle(frame, pt, 3, (0, 0, 0), -1)
            cv2.circle(frame, pt, 2, (0, 255, 0), -1)

def extract_best_pose_keypoints(pose_res, x1i, y1i):
    if pose_res.keypoints is None or len(pose_res.keypoints) == 0:
        return None

    xy_all = pose_res.keypoints.xy.cpu().numpy()
    cf_all = pose_res.keypoints.conf.cpu().numpy() if pose_res.keypoints.conf is not None else None

    if xy_all.ndim == 2:
        xy_all = xy_all[None, ...]
    elif xy_all.ndim != 3:
        return None

    if cf_all is None:
        xy = xy_all[0]
        cf = np.ones((xy.shape[0], 1), dtype=np.float32) * 0.5
    else:
        if cf_all.ndim == 1:
            cf_all = cf_all[None, ...]
        best_i = int(np.argmax(cf_all.mean(axis=1)))
        xy = xy_all[best_i]
        cf = cf_all[best_i].reshape(-1, 1)

    kpts = np.concatenate([xy, cf], axis=1).astype(np.float32)
    kpts[:, 0] += float(x1i)
    kpts[:, 1] += float(y1i)
    return kpts


def extract_pose_instances(pose_res):
    if pose_res.keypoints is None or len(pose_res.keypoints) == 0:
        return []

    xy_all = pose_res.keypoints.xy.cpu().numpy()
    cf_all = pose_res.keypoints.conf.cpu().numpy() if pose_res.keypoints.conf is not None else None

    if xy_all.ndim == 2:
        xy_all = xy_all[None, ...]
    if cf_all is not None and cf_all.ndim == 1:
        cf_all = cf_all[None, ...]

    boxes = None
    box_conf = None
    if pose_res.boxes is not None and len(pose_res.boxes) > 0:
        boxes = pose_res.boxes.xyxy.cpu().numpy()
        box_conf = pose_res.boxes.conf.cpu().numpy() if pose_res.boxes.conf is not None else None

    instances = []
    for i, xy in enumerate(xy_all):
        if cf_all is None:
            cf = np.ones((xy.shape[0], 1), dtype=np.float32) * 0.5
        else:
            cf = cf_all[i].reshape(-1, 1)

        kpts = np.concatenate([xy, cf], axis=1).astype(np.float32)

        if boxes is not None and i < len(boxes):
            x1, y1, x2, y2 = [float(v) for v in boxes[i]]
        else:
            valid = kpts[:, 2] > 0.10
            if not np.any(valid):
                continue
            x1 = float(kpts[valid, 0].min())
            y1 = float(kpts[valid, 1].min())
            x2 = float(kpts[valid, 0].max())
            y2 = float(kpts[valid, 1].max())

        conf = float(box_conf[i]) if box_conf is not None and i < len(box_conf) else float(kpts[:, 2].mean())
        instances.append({
            "bbox": (x1, y1, x2, y2),
            "conf": conf,
            "kpts": kpts,
        })

    return instances

def analyze_pose_topdown(xyxy, kpts):
    x1, y1, x2, y2 = xyxy
    w = max(1.0, x2 - x1)
    h = max(1.0, y2 - y1)
    ratio = w / h

    ls = kpts[5]
    rs = kpts[6]
    lh = kpts[11]
    rh = kpts[12]

    valid = (ls[2] > KP_CONF and rs[2] > KP_CONF and lh[2] > KP_CONF and rh[2] > KP_CONF)

    torso_ang = 0.0
    if valid:
        shoulder = ((ls[0] + rs[0]) / 2, (ls[1] + rs[1]) / 2)
        hip = ((lh[0] + rh[0]) / 2, (lh[1] + rh[1]) / 2)
        torso_ang = angle_with_vertical(shoulder, hip)

    # 稍微放寬條件，躺平更容易判成受傷
    injured = (torso_ang > TORSO_ANGLE_DEG) if valid else False

    dbg = {
        "bbox_ratio": float(ratio),
        "torso_angle": float(torso_ang),
        "pose_valid": bool(valid),
        "decision_source": "pose"
    }
    return injured, dbg

def _cleanup_tracks():
    now = time.time()
    dead = [k for k, v in tracks.items() if now - v.get("last_seen", now) > TRACK_FORGET_SEC]
    for k in dead:
        tracks.pop(k, None)


def _create_track(grid_id, bbox, center):
    global next_track_id

    tid = next_track_id
    next_track_id += 1
    tracks[tid] = {
        "target_id": tid,
        "grid_id": grid_id,
        "bbox": [int(v) for v in bbox],
        "center": center,
        "evidence": 0,
        "state": None,
        "last_seen": time.time(),
        "event_emitted": False,
    }
    return tid


def match_track(bbox, matched_ids):
    center = bbox_center(bbox)
    grid_id = simple_track_id(center[0], center[1])

    # Priority 1: same grid ID.
    same_grid = [
        (tid, st) for tid, st in tracks.items()
        if tid not in matched_ids and st.get("grid_id") == grid_id
    ]
    if same_grid:
        tid, _ = max(same_grid, key=lambda item: item[1].get("last_seen", 0.0))
        return tid, {
            "grid_id": grid_id,
            "match_method": "grid",
            "match_iou": None,
            "match_center_distance": None,
        }

    # Priority 2: IoU / center-distance fallback for grid-boundary jitter.
    best = None
    for tid, st in tracks.items():
        if tid in matched_ids:
            continue

        iou = bbox_iou(st.get("bbox", bbox), bbox)
        distance = center_distance(st.get("center", center), center)
        if iou >= TRACK_IOU_THRESHOLD or distance <= TRACK_CENTER_DIST_THRESHOLD:
            score = (iou, -distance)
            if best is None or score > best[0]:
                best = (score, tid, iou, distance)

    if best is not None:
        _, tid, iou, distance = best
        return tid, {
            "grid_id": grid_id,
            "match_method": "iou_center",
            "match_iou": float(iou),
            "match_center_distance": float(distance),
        }

    # Priority 3: create new target.
    tid = _create_track(grid_id, bbox, center)
    return tid, {
        "grid_id": grid_id,
        "match_method": "new",
        "match_iou": None,
        "match_center_distance": None,
    }


def decay_unmatched_tracks(matched_ids):
    for tid, st in list(tracks.items()):
        if tid in matched_ids:
            continue

        st["evidence"] = max(int(st.get("evidence", 0)) - 1, 0)
        st["state"] = get_event_state(st["evidence"])
        if st["evidence"] == 0:
            st["event_emitted"] = False
        tracks[tid] = st


def update_track_decision(tid, conf, bbox, dbg):
    st = tracks[tid]
    center = bbox_center(bbox)
    st["bbox"] = [int(v) for v in bbox]
    st["center"] = center
    st["grid_id"] = dbg.get("grid_id", simple_track_id(center[0], center[1]))
    st["last_seen"] = time.time()
    st["evidence"] = int(st.get("evidence", 0)) + 1
    st["state"] = get_event_state(st["evidence"])

    event = None
    if st["state"] == "Rescue Needed" and not st.get("event_emitted", False):
        msg = (
            f"[RESCUE NEEDED] ID={tid} SEG={conf * 100:.0f}% "
            f"evidence={st['evidence']} ratio={dbg.get('bbox_ratio')} "
            f"pose_valid={dbg.get('pose_valid')}"
        )
        print(msg)
        st["event_emitted"] = True
        event = {
            "type": "RESCUE",
            "message": msg,
            "tid": str(tid),
            "conf": conf,
            "bbox": [int(v) for v in bbox],
            "dbg": dbg,
            "decision_source": "seg",
            "ts": time.time()
        }

    tracks[tid] = st
    return st["state"], event

# =========================
# 4) Main API
# =========================
def _legacy_process_frame_grid_only(frame_bgr: np.ndarray):
    """
    輸入：BGR frame
    輸出：
      - infos: 結構化結果（給 overlay / event / database 用）
    """
    global frame_counter
    frame_counter += 1

    infos = []
    _cleanup_tracks()

    if pose_enabled:
        try:
            pose_res = _get_pose_model().predict(
                frame_bgr,
                conf=0.20,
                imgsz=DETECT_IMGSZ,
                max_det=10,
                verbose=False,
            )[0]
            pose_instances = extract_pose_instances(pose_res)
        except Exception as e:
            print("POSE_FULL_FRAME_FAILED:", e)
            pose_instances = []

        for instance in pose_instances:
            x1, y1, x2, y2 = clamp_bbox(instance["bbox"], frame_bgr.shape)
            conf = instance["conf"]
            kpts = instance["kpts"]

            cx, cy = (x1 + x2) / 2, (y1 + y2) / 2
            tid = simple_track_id(cx, cy)

            injured, dbg = analyze_pose_topdown((x1, y1, x2, y2), kpts)
            pose_unclear = not dbg.get("pose_valid", False)
            if pose_unclear:
                injured = False
                dbg["decision_source"] = "pose_unclear"

            bbox = [int(x1), int(y1), int(x2), int(y2)]
            status, event = update_track_decision(tid, injured, pose_unclear, conf, bbox, dbg)

            infos.append({
                "tid": str(tid),
                "status": status,
                "conf": conf,
                "bbox": bbox,
                "dbg": dbg,
                "decision_source": dbg.get("decision_source"),
                "kpts": kpts.tolist(),
                "event": event,
            })

        return infos

    # detector conf 不要太高，讓倒地人物較容易被抓出
    det = det_model.predict(frame_bgr, conf=SEG_CONF, iou=DETECT_IOU, imgsz=DETECT_IMGSZ, max_det=20, verbose=False)[0]
    if det.boxes is None or len(det.boxes) == 0:
        return infos

    for index, box in enumerate(det.boxes):
        conf = float(box.conf[0]) if box.conf is not None else 0.0
        raw_xyxy = [float(v) for v in box.xyxy[0]]
        x1, y1, x2, y2 = refine_bbox_from_seg_mask(det, index, raw_xyxy, frame_bgr.shape)

        cx, cy = (x1 + x2) / 2, (y1 + y2) / 2
        tid = simple_track_id(cx, cy)

        w = max(1.0, x2 - x1)
        h = max(1.0, y2 - y1)
        area = w * h

        x1i, y1i, x2i, y2i = map(
            int,
            [max(0, x1), max(0, y1), min(frame_bgr.shape[1] - 1, x2), min(frame_bgr.shape[0] - 1, y2)]
        )
        crop = frame_bgr[y1i:y2i, x1i:x2i]
        if crop.size == 0:
            continue

        should_run_pose = (
            pose_enabled and
            conf >= POSE_MIN_CONF and
            area >= POSE_MIN_AREA and
            (frame_counter % POSE_EVERY_N_FRAMES == 0)
        )

        kpts = None
        injured = False
        pose_unclear = False
        ratio = w / h
        dbg = {
            "bbox_ratio": float(ratio),
            "torso_angle": None,
            "pose_valid": False,
            "decision_source": "seg",
            "requires_stability": True,
        }

        if should_run_pose:
            try:
                pose_res = _get_pose_model().predict(crop, conf=0.20, verbose=False)[0]
                kpts = extract_best_pose_keypoints(pose_res, x1i, y1i)
            except Exception:
                kpts = None

        if pose_enabled:
            if kpts is not None and kpts.shape[0] >= 17:
                injured, dbg = analyze_pose_topdown((x1, y1, x2, y2), kpts)
            else:
                pose_unclear = True
                injured = False
                dbg["decision_source"] = "pose_unclear"
        else:
            injured = conf >= SEG_CONF

        st = tracks.get(tid, {
            "sus": 0,
            "rescue": 0,
            "last_seen": time.time(),
            "printed": False,
            "last_bbox": None
        })
        st["last_seen"] = time.time()

        prev_bbox = st.get("last_bbox")
        bbox = [int(x1), int(y1), int(x2), int(y2)]
        stable_detection = True
        if prev_bbox is not None:
            iou = bbox_iou(prev_bbox, bbox)
            shift = bbox_center_shift(prev_bbox, bbox)
            stable_detection = (iou >= TRACK_IOU_THRESHOLD) or (shift <= TRACK_CENTER_DIST_THRESHOLD)
            dbg["stable_iou"] = float(iou)
            dbg["center_shift"] = float(shift)
        dbg["stable_detection"] = bool(stable_detection)
        st["last_bbox"] = bbox

        if injured and stable_detection:
            st["sus"] += 1
            st["rescue"] += 1
        else:
            # 不要掉太快，避免快要累積到 rescue 又歸零
            st["sus"] = max(0, st["sus"] - 1)
            st["rescue"] = max(0, st["rescue"] - 1)
            if st["rescue"] == 0:
                st["printed"] = False

        tracks[tid] = st

        status = "OK"
        if pose_unclear:
            status = "Pose Unclear"
        elif st["sus"] >= SUSPECT_THRESHOLD:
            status = "Suspected Injury"
        elif injured and st["sus"] > 0:
            status = "Detected Injury"

        event = None
        if st["rescue"] >= RESCUE_THRESHOLD:
            status = "Rescue Needed"
            if not st["printed"]:
                msg = f"[RESCUE NEEDED] ID={tid} SEG={conf * 100:.0f}% ratio={dbg['bbox_ratio']} ang={dbg['torso_angle']}"
                print(msg)
                st["printed"] = True
                tracks[tid] = st
                event = {
                    "type": "RESCUE",
                    "message": msg,
                    "tid": str(tid),
                    "conf": conf,
                    "bbox": [int(x1), int(y1), int(x2), int(y2)],
                    "dbg": dbg,
                    "decision_source": dbg.get("decision_source"),
                    "ts": time.time()
                }

        infos.append({
            "tid": str(tid),
            "status": status,
            "conf": conf,
            "bbox": bbox,
            "dbg": dbg,
            "decision_source": dbg.get("decision_source"),
            "kpts": None if kpts is None else kpts.tolist(),
            "event": event,
        })

    return infos


def process_frame(frame_bgr: np.ndarray):
    """
    SEG is the primary injured-person recognition path.
    Pose is auxiliary only and never rejects a positive SEG candidate.
    """
    global frame_counter
    frame_counter += 1

    infos = []
    matched_ids = set()
    _cleanup_tracks()

    det = det_model.predict(
        frame_bgr,
        conf=SEG_CONF,
        iou=DETECT_IOU,
        imgsz=DETECT_IMGSZ,
        max_det=20,
        verbose=False,
    )[0]

    if det.boxes is None or len(det.boxes) == 0:
        decay_unmatched_tracks(matched_ids)
        return infos

    for index, box in enumerate(det.boxes):
        conf = float(box.conf[0]) if box.conf is not None else 0.0
        raw_xyxy = [float(v) for v in box.xyxy[0]]
        x1, y1, x2, y2 = refine_bbox_from_seg_mask(det, index, raw_xyxy, frame_bgr.shape)

        w = max(1.0, x2 - x1)
        h = max(1.0, y2 - y1)
        area = w * h
        ratio = w / h

        bbox = [int(x1), int(y1), int(x2), int(y2)]
        dbg = {
            "bbox_ratio": float(ratio),
            "torso_angle": None,
            "pose_valid": False,
            "decision_source": "seg",
            "seg_conf": float(conf),
            "seg_threshold": SEG_CONF,
            "detect_iou": DETECT_IOU,
            "pose_aux_enabled": bool(pose_enabled),
        }

        x1i, y1i, x2i, y2i = map(
            int,
            [max(0, x1), max(0, y1), min(frame_bgr.shape[1] - 1, x2), min(frame_bgr.shape[0] - 1, y2)]
        )
        crop = frame_bgr[y1i:y2i, x1i:x2i]
        if crop.size == 0:
            continue

        kpts = None
        should_run_pose = (
            pose_enabled and
            conf >= POSE_MIN_CONF and
            area >= POSE_MIN_AREA and
            (frame_counter % POSE_EVERY_N_FRAMES == 0)
        )

        if should_run_pose:
            try:
                pose_res = _get_pose_model().predict(crop, conf=0.20, verbose=False)[0]
                kpts = extract_best_pose_keypoints(pose_res, x1i, y1i)
            except Exception as e:
                dbg["pose_error"] = str(e)
                kpts = None

        if kpts is not None and kpts.shape[0] >= 17:
            pose_injured, pose_dbg = analyze_pose_topdown((x1, y1, x2, y2), kpts)
            dbg.update({
                "torso_angle": pose_dbg.get("torso_angle"),
                "pose_valid": pose_dbg.get("pose_valid", False),
                "pose_aux_injured": bool(pose_injured),
                "pose_aux_source": "pose",
            })
        elif pose_enabled:
            dbg["pose_aux_source"] = "pose_unavailable"

        tid, match_dbg = match_track(bbox, matched_ids)
        matched_ids.add(tid)
        dbg.update(match_dbg)

        status, event = update_track_decision(tid, conf, bbox, dbg)

        infos.append({
            "tid": str(tid),
            "status": status,
            "conf": conf,
            "bbox": bbox,
            "dbg": dbg,
            "decision_source": "seg",
            "kpts": None if kpts is None else kpts.tolist(),
            "event": event,
        })

    decay_unmatched_tracks(matched_ids)
    return infos


def overlay_infos(frame_bgr: np.ndarray, infos: list):
    """
    把最近一次推論結果疊到最新影像上
    這樣顯示比較順，不需要每張顯示畫面都重新跑推論
    """
    out = frame_bgr.copy()

    for it in infos:
        x1, y1, x2, y2 = it["bbox"]
        status = it["status"]
        conf = it["conf"]
        dbg = it.get("dbg", {})
        tid = it.get("tid", "?")
        decision_source = it.get("decision_source") or dbg.get("decision_source", "seg")
        is_pose_overlay = str(decision_source).startswith("pose")

        if status == "Rescue Needed":
            color = (0, 0, 255)       # 紅
        elif status == "Suspected Injury":
            color = (0, 165, 255)     # 橘
        elif status == "Pose Unclear":
            color = (255, 0, 255)     # 紫
        else:
            color = (255, 255, 0)     # 黃藍

        conf_pct = int(round(conf * 100))

        if not is_pose_overlay:
            cv2.rectangle(out, (x1, y1), (x2, y2), color, 2)
            cv2.putText(
                out,
                f"ID{tid} {status} | SEG {conf_pct}%",
                (x1, max(0, y1 - 8)),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.6,
                color,
                2
            )
        else:
            cv2.putText(
                out,
                f"Pose {status}",
                (x1, max(0, y1 - 8)),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.55,
                color,
                2
            )

        if (not is_pose_overlay) and dbg.get("bbox_ratio") is not None:
            txt = f"r={dbg['bbox_ratio']:.2f}"
            if dbg.get("torso_angle") is not None:
                txt += f" a={dbg['torso_angle']:.1f}"
            cv2.putText(
                out,
                txt,
                (x1, min(out.shape[0] - 5, y2 + 20)),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.5,
                color,
                2
            )

        # 只要有 keypoints 就畫，不再限制 status != OK
        kpts = it.get("kpts")
        if kpts is not None:
            try:
                kpts_np = np.array(kpts, dtype=np.float32)
                draw_skeleton_precise(out, kpts_np, conf_thr=KP_CONF)
            except Exception:
                pass

    return out
