from __future__ import annotations

import time
import threading
from collections import deque
from queue import Full, Queue

import cv2
import numpy as np
import requests
from flask import Flask, jsonify, request
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
RC_API_MIN = -100
RC_API_MAX = 100
RC_SAFE_MIN = -50
RC_SAFE_MAX = 50
NUDGE_VERTICAL_SPEED = 30
NUDGE_VERTICAL_DURATION_SEC = 0.45
NUDGE_HORIZONTAL_SPEED = 25
NUDGE_HORIZONTAL_DURATION_SEC = 0.35
NUDGE_YAW_SPEED = 30
NUDGE_YAW_DURATION_SEC = 0.35

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
    runtime.refresh_state()
    st = runtime.state
    return {
        "connected": st.connected,
        "streaming": st.streaming,
        "flying": st.flying,
        "battery": st.battery,
        "tello_state": st.tello_state,
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


def _clamp(value: int, lower: int, upper: int) -> int:
    return max(lower, min(upper, value))


def _status_response(ok: bool, message: str, status_code: int = 200, error=None):
    status_data = _status_dict()
    payload = {
        "ok": ok,
        "message": message,
        **status_data,
        "status": status_data,
    }
    if error is not None:
        payload["error"] = error
    socketio.emit("status", status_data)
    return jsonify(payload), status_code


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


def _ensure_connected() -> bool:
    return runtime.connect()


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


@app.post("/takeoff")
def takeoff():
    if not _ensure_connected():
        return _status_response(False, "Unable to connect before takeoff", 503, runtime.state.last_error)

    if not runtime.takeoff():
        return _status_response(False, "Takeoff failed", 503, runtime.state.last_error)

    if not _ensure_started():
        return _status_response(True, "Takeoff complete, but stream restart failed", 200, runtime.state.last_error)

    return _status_response(True, "Takeoff complete")


@app.post("/land")
def land():
    runtime.stop_motion()

    if not runtime.land():
        return _status_response(False, "Landing failed", 503, runtime.state.last_error)

    return _status_response(True, "Landing complete")


@app.post("/emergency")
def emergency():
    if not runtime.emergency():
        return _status_response(False, "Emergency stop failed", 500, runtime.state.last_error)

    return _status_response(True, "Emergency stop command sent")


@app.post("/sdk/reset")
def reset_sdk():
    global _started
    _started = False

    if not runtime.reset_sdk():
        return _status_response(False, "SDK reset failed", 503, runtime.state.last_error)

    return _status_response(True, "SDK reset complete")


@app.post("/rc")
def rc():
    payload = request.get_json(silent=True) or {}
    values = {}

    for key in ("lr", "fb", "ud", "yaw"):
        if key not in payload:
            return _status_response(False, f"Missing RC value: {key}", 400, "MISSING_RC_VALUE")

        try:
            raw_value = int(payload[key])
        except (TypeError, ValueError):
            return _status_response(False, f"Invalid RC value: {key}", 400, "INVALID_RC_VALUE")

        api_limited = _clamp(raw_value, RC_API_MIN, RC_API_MAX)
        values[key] = _clamp(api_limited, RC_SAFE_MIN, RC_SAFE_MAX)

    if not runtime.state.flying:
        return _status_response(False, "RC ignored because drone is not flying", 409, "DRONE_NOT_FLYING")

    if not runtime.rc(values["lr"], values["fb"], values["ud"], values["yaw"]):
        return _status_response(False, "RC command failed", 503, runtime.state.last_error)

    return _status_response(True, "RC command sent")


def _nudge(lr: int = 0, fb: int = 0, ud: int = 0, yaw: int = 0):
    if not runtime.nudge_rc(lr=lr, fb=fb, ud=ud, yaw=yaw, duration_sec=NUDGE_HORIZONTAL_DURATION_SEC):
        status_code = 409 if runtime.state.last_error == "DRONE_NOT_FLYING" else 503
        return _status_response(False, "Nudge failed", status_code, runtime.state.last_error)

    return _status_response(True, "Nudge complete")


def _nudge_yaw(yaw: int):
    if not runtime.nudge_rc(yaw=yaw, duration_sec=NUDGE_YAW_DURATION_SEC):
        status_code = 409 if runtime.state.last_error == "DRONE_NOT_FLYING" else 503
        return _status_response(False, "Yaw nudge failed", status_code, runtime.state.last_error)

    return _status_response(True, "Yaw nudge complete")


@app.post("/nudge/up")
def nudge_up():
    if not runtime.nudge_rc(ud=NUDGE_VERTICAL_SPEED, duration_sec=NUDGE_VERTICAL_DURATION_SEC):
        status_code = 409 if runtime.state.last_error == "DRONE_NOT_FLYING" else 503
        return _status_response(False, "Nudge up failed", status_code, runtime.state.last_error)

    return _status_response(True, "Nudge up complete")


@app.post("/nudge/down")
def nudge_down():
    if not runtime.nudge_rc(ud=-NUDGE_VERTICAL_SPEED, duration_sec=NUDGE_VERTICAL_DURATION_SEC):
        status_code = 409 if runtime.state.last_error == "DRONE_NOT_FLYING" else 503
        return _status_response(False, "Nudge down failed", status_code, runtime.state.last_error)

    return _status_response(True, "Nudge down complete")


@app.post("/nudge/left")
def nudge_left():
    return _nudge(lr=-NUDGE_HORIZONTAL_SPEED)


@app.post("/nudge/right")
def nudge_right():
    return _nudge(lr=NUDGE_HORIZONTAL_SPEED)


@app.post("/nudge/forward")
def nudge_forward():
    return _nudge(fb=NUDGE_HORIZONTAL_SPEED)


@app.post("/nudge/back")
def nudge_back():
    return _nudge(fb=-NUDGE_HORIZONTAL_SPEED)


@app.post("/nudge/yaw-left")
def nudge_yaw_left():
    return _nudge_yaw(-NUDGE_YAW_SPEED)


@app.post("/nudge/yaw-right")
def nudge_yaw_right():
    return _nudge_yaw(NUDGE_YAW_SPEED)


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
    _ensure_connected()
    emit("status", _status_dict())


@socketio.on("get_status")
def on_get_status():
    emit("status", _status_dict())


if __name__ == "__main__":
    socketio.run(app, host="0.0.0.0", port=5001, allow_unsafe_werkzeug=True)
