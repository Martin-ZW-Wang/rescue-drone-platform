from __future__ import annotations

import time
import threading
from collections import deque
from queue import Full, Queue

import cv2
import numpy as np
import requests
from flask import Flask, jsonify
from flask_cors import CORS
from flask_socketio import SocketIO, emit

from drone_runtime import DroneRuntime
from drone import get_pose_enabled, overlay_infos, process_frame, run_seg_diagnostics, set_pose_enabled


app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": ["http://127.0.0.1:8000", "http://localhost:8000"]}})

socketio = SocketIO(
    app,
    cors_allowed_origins=["http://127.0.0.1:8000", "http://localhost:8000"],
    async_mode="threading",
)

runtime = DroneRuntime()

# =========================
# Laravel event ingest
# =========================
LARAVEL_EVENT_INGEST_URL = "http://127.0.0.1:8000/api/events/ingest"

# =========================
# Performance knobs
# =========================
OUT_SIZE = 512
STREAM_FPS = 12.0
PROC_FPS = 2.0
JPEG_QUALITY = 60

tracking_active = False

# 最新原始 frame
_frame_lock = threading.Lock()
_latest_frame = None
_latest_err = None

# 最新推論結果
_infos_lock = threading.Lock()
_latest_infos = []
_latest_infos_shape = None
_last_infer_ts = 0.0
_last_infer_ms = 0.0
_last_det_count = 0
_no_det_streak = 0

# 最新顯示 jpg
_disp_lock = threading.Lock()
_last_jpg = None

# 事件
_events = deque(maxlen=200)
_events_lock = threading.Lock()
_event_save_queue = Queue(maxsize=100)

_started = False
_stop = threading.Event()


def _encode_jpg(frame_bgr: np.ndarray) -> bytes:
    ok, buf = cv2.imencode(".jpg", frame_bgr, [int(cv2.IMWRITE_JPEG_QUALITY), JPEG_QUALITY])
    return buf.tobytes() if ok else b""


def _error_jpg(text: str) -> bytes:
    img = np.zeros((520, 900, 3), dtype=np.uint8)
    cv2.rectangle(img, (0, 0), (900, 520), (20, 20, 20), -1)
    cv2.putText(img, "DRONE STREAM ERROR", (20, 80),
                cv2.FONT_HERSHEY_SIMPLEX, 1.2, (0, 0, 255), 3)
    y = 140
    for line in (text or "UNKNOWN").splitlines():
        cv2.putText(img, line[:90], (20, y),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.8, (200, 200, 200), 2)
        y += 40
        if y > 480:
            break
    return _encode_jpg(img)


def _status_dict():
    st = runtime.state
    return {
        "connected": st.connected,
        "streaming": st.streaming,
        "battery": st.battery,
        "error": st.last_error,
        "tracking": tracking_active,
        "seg_enabled": tracking_active,
        "pose_enabled": get_pose_enabled(),
        "out_size": OUT_SIZE,
        "stream_fps": STREAM_FPS,
        "proc_fps": PROC_FPS,
        "last_infer_ms": round(_last_infer_ms, 1),
        "last_det_count": _last_det_count,
        "no_det_streak": _no_det_streak,
        "jpeg_quality": JPEG_QUALITY,
    }


def _save_event_to_laravel(evt: dict):
    try:
        payload = {
            "event_type": evt.get("type", "RESCUE"),
            "tid": str(evt.get("tid")) if evt.get("tid") is not None else None,
            "status": "Rescue Needed",
            "conf": evt.get("conf"),
            "message": evt.get("message"),
            "bbox_json": evt.get("bbox"),
            "dbg_json": evt.get("dbg"),
            "event_time": time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(evt.get("ts", time.time()))),
        }

        r = requests.post(LARAVEL_EVENT_INGEST_URL, json=payload, timeout=5)
        print("Laravel ingest:", r.status_code, r.text)
    except Exception as e:
        print("SAVE_EVENT_TO_LARAVEL_FAILED:", e)


def _push_event(evt: dict):
    with _events_lock:
        _events.append(evt)

    try:
        _event_save_queue.put_nowait(evt)
    except Full:
        print("EVENT_SAVE_QUEUE_FULL: dropped event save")


def _ensure_started() -> bool:
    global _started
    if _started:
        return True

    if not runtime.start_stream():
        return False

    _stop.clear()
    threading.Thread(target=_capture_loop, daemon=True).start()
    threading.Thread(target=_inference_loop, daemon=True).start()
    threading.Thread(target=_display_loop, daemon=True).start()
    threading.Thread(target=_push_loop, daemon=True).start()
    threading.Thread(target=_event_loop, daemon=True).start()
    threading.Thread(target=_event_save_loop, daemon=True).start()

    _started = True
    return True


def _event_save_loop():
    while not _stop.is_set():
        evt = _event_save_queue.get()
        try:
            _save_event_to_laravel(evt)
        finally:
            _event_save_queue.task_done()


def _scale_infos_for_display(infos: list, src_shape, dst_shape) -> list:
    if not infos or not src_shape:
        return []

    src_h, src_w = src_shape[:2]
    dst_h, dst_w = dst_shape[:2]
    sx = dst_w / max(1, src_w)
    sy = dst_h / max(1, src_h)

    scaled = []
    for item in infos:
        copied = dict(item)
        x1, y1, x2, y2 = copied.get("bbox", [0, 0, 0, 0])
        copied["bbox"] = [int(x1 * sx), int(y1 * sy), int(x2 * sx), int(y2 * sy)]

        kpts = copied.get("kpts")
        if kpts is not None:
            copied["kpts"] = [[pt[0] * sx, pt[1] * sy, pt[2]] for pt in kpts]

        scaled.append(copied)

    return scaled


def _capture_loop():
    global _latest_frame, _latest_err
    while not _stop.is_set():
        ok, frame, err = runtime.frame()
        with _frame_lock:
            if ok and frame is not None:
                _latest_frame = frame
                _latest_err = None
            else:
                _latest_err = err or "FRAME ERROR"
        time.sleep(0.003)


def _inference_loop():
    global _latest_infos, _latest_infos_shape, _last_infer_ts, _last_infer_ms, _last_det_count, _no_det_streak
    proc_interval = 1.0 / max(1e-6, PROC_FPS)

    while not _stop.is_set():
        now = time.time()
        if (now - _last_infer_ts) < proc_interval:
            time.sleep(0.005)
            continue

        with _frame_lock:
            frame = None if _latest_frame is None else _latest_frame.copy()

        if frame is None:
            time.sleep(0.01)
            continue

        if tracking_active:
            try:
                infer_started = time.time()
                infos = process_frame(frame)
                _last_infer_ms = (time.time() - infer_started) * 1000.0
                _last_det_count = len(infos)
                _no_det_streak = 0 if infos else _no_det_streak + 1

                for it in infos:
                    evt = it.get("event")
                    if isinstance(evt, dict):
                        _push_event(evt)

                with _infos_lock:
                    # Keep the last valid overlay briefly so one missed SEG frame
                    # does not look like the detector permanently disappeared.
                    if infos or _no_det_streak >= 3:
                        _latest_infos = infos
                        _latest_infos_shape = frame.shape

            except Exception as e:
                print("PROCESS_FAILED:", e)
        else:
            with _infos_lock:
                _latest_infos = []
                _latest_infos_shape = None
            _last_det_count = 0
            _no_det_streak = 0

        _last_infer_ts = time.time()


def _display_loop():
    global _last_jpg

    interval = 1.0 / max(1e-6, STREAM_FPS)

    while not _stop.is_set():
        with _frame_lock:
            frame = None if _latest_frame is None else _latest_frame.copy()
            err = _latest_err

        if frame is None:
            jpg = _error_jpg(err or "NO FRAME")
            with _disp_lock:
                _last_jpg = jpg
            time.sleep(interval)
            continue

        frame = cv2.resize(frame, (OUT_SIZE, OUT_SIZE))

        with _infos_lock:
            infos = list(_latest_infos)
            infos_shape = _latest_infos_shape

        try:
            infos = _scale_infos_for_display(infos, infos_shape, frame.shape)
            if tracking_active and infos:
                display_frame = overlay_infos(frame, infos)
            else:
                display_frame = frame

            jpg = _encode_jpg(display_frame)
        except Exception as e:
            jpg = _error_jpg(f"DISPLAY_FAILED: {e}")

        with _disp_lock:
            _last_jpg = jpg

        time.sleep(interval)


def _push_loop():
    interval = 1.0 / max(1e-6, STREAM_FPS)
    while not _stop.is_set():
        with _disp_lock:
            jpg = _last_jpg
        if jpg:
            socketio.emit("frame", jpg)
        time.sleep(interval)


def _event_loop():
    last_len = 0
    while not _stop.is_set():
        with _events_lock:
            n = len(_events)
            evt = _events[-1] if n > 0 else None

        if evt and n != last_len:
            last_len = n
            socketio.emit("event", evt)
        time.sleep(0.2)


@app.get("/status")
def status():
    return jsonify(_status_dict())


@app.get("/debug/seg")
def debug_seg():
    if not _ensure_started():
        return jsonify({"ok": False, "error": runtime.state.last_error}), 503

    with _frame_lock:
        frame = None if _latest_frame is None else _latest_frame.copy()

    if frame is None:
        return jsonify({"ok": False, "error": "NO_FRAME"}), 503

    try:
        report = run_seg_diagnostics(frame)
        report["ok"] = True
        return jsonify(report), 200
    except Exception as e:
        return jsonify({"ok": False, "error": "SEG_DIAGNOSTICS_FAILED", "message": str(e)}), 500


@app.post("/start")
def start_tracking():
    global tracking_active
    if not _ensure_started():
        return jsonify({"ok": False, "error": runtime.state.last_error}), 503
    set_pose_enabled(False)
    tracking_active = True
    return jsonify({"ok": True, "message": "SEG detection started", "pose_enabled": False}), 200


@app.post("/stop")
def stop_tracking():
    global tracking_active
    tracking_active = False
    return jsonify({"ok": True, "message": "Tracking stopped"}), 200


@app.post("/pose/start")
def start_pose():
    set_pose_enabled(True)
    return jsonify({"ok": True, "pose_enabled": True, "message": "Pose assist enabled"}), 200


@app.post("/pose/stop")
def stop_pose():
    set_pose_enabled(False)
    return jsonify({"ok": True, "pose_enabled": False, "message": "Pose assist disabled"}), 200


@socketio.on("connect")
def on_connect():
    _ensure_started()
    emit("status", _status_dict())


@socketio.on("get_status")
def on_get_status():
    emit("status", _status_dict())


if __name__ == "__main__":
    socketio.run(app, host="0.0.0.0", port=5001, allow_unsafe_werkzeug=True)
