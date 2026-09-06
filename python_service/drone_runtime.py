from __future__ import annotations

import threading
from dataclasses import dataclass
from time import sleep
from typing import Any, Optional, Tuple

import cv2
from djitellopy import Tello


@dataclass
class DroneState:
    connected: bool = False
    streaming: bool = False
    flying: bool = False
    battery: Optional[int] = None
    tello_state: Optional[dict[str, Any]] = None
    last_error: Optional[str] = None


class DroneRuntime:
    """
    - 只負責：連線、啟用串流、抓 frame、電池輪詢、RC/land（目前不會起飛）
    - 連不上 Tello 時，不拋例外給上層，改回傳 False 並寫入 last_error
    """
    def __init__(self) -> None:
        self._lock = threading.RLock()
        self._tello = Tello()
        self._state = DroneState()
        self._battery_thread: Optional[threading.Thread] = None
        self._hover_thread: Optional[threading.Thread] = None
        self._hover_stop = threading.Event()
        self._stop = threading.Event()
        self._preflight_min_battery = 30
        self._preflight_max_temperature = 85

    @property
    def state(self) -> DroneState:
        return self._state

    def connect(self) -> bool:
        with self._lock:
            if self._state.connected:
                return True

            try:
                self._tello.connect()
                self._state.connected = True
                self._state.last_error = None
                self.refresh_state()
                return True
            except Exception as e:
                self._state.connected = False
                self._state.streaming = False
                self._state.last_error = f"CONNECT_FAILED: {e}"
                return False

    def start_stream(self) -> bool:
        with self._lock:
            if not self._state.connected:
                if not self.connect():
                    return False

            if self._state.streaming:
                return True

            try:
                self._tello.streamon()
                self._state.streaming = True
                self._state.last_error = None
            except Exception as e:
                self._state.streaming = False
                self._state.last_error = f"STREAMON_FAILED: {e}"
                return False

            if self._battery_thread is None:
                self._battery_thread = threading.Thread(target=self._poll_battery, daemon=True)
                self._battery_thread.start()

            return True

    def stop_stream(self) -> bool:
        with self._lock:
            if not self._state.streaming:
                return True

            try:
                self._tello.streamoff()
                self._state.streaming = False
                self._state.last_error = None
                return True
            except Exception as e:
                self._state.last_error = f"STREAMOFF_FAILED: {e}"
                return False

    def _enter_sdk_mode(self) -> bool:
        self._tello.send_control_command("command")
        return True

    def reset_sdk(self) -> bool:
        with self._lock:
            try:
                if self._state.streaming:
                    try:
                        self._tello.streamoff()
                    except Exception:
                        pass

                self._state.streaming = False
                self._state.flying = False
                self._stop_hover_keepalive()
                sleep(0.5)
                self._enter_sdk_mode()
                self._state.connected = True
                self._state.last_error = None
                self.refresh_state()
                return True
            except Exception as e:
                self._state.connected = False
                self._state.streaming = False
                self._state.last_error = f"SDK_RESET_FAILED: {e}"
                return False

    def _poll_battery(self) -> None:
        while not self._stop.is_set():
            try:
                with self._lock:
                    if self._state.connected:
                        self._state.tello_state = dict(self._tello.get_current_state())
                        battery = self._state.tello_state.get("bat")
                        self._state.battery = int(battery if battery is not None else self._tello.get_battery())
            except Exception as e:
                self._state.last_error = f"BATTERY_FAILED: {e}"
            sleep(3)

    def refresh_state(self) -> None:
        try:
            with self._lock:
                if self._state.connected:
                    self._state.tello_state = dict(self._tello.get_current_state())
                    battery = self._state.tello_state.get("bat")
                    if battery is not None:
                        self._state.battery = int(battery)
                    if self._state.flying and self._state_int("h") == 0 and self._state_int("time") == 0:
                        self._state.flying = False
                        self._stop_hover_keepalive()
        except Exception as e:
            self._state.last_error = f"STATE_REFRESH_FAILED: {e}"

    def preflight_takeoff_error(self) -> Optional[str]:
        self.refresh_state()

        with self._lock:
            state = self._state.tello_state or {}
            battery = self._state.battery
            temp = state.get("temph")
            if temp is None:
                temp = state.get("templ")

            if battery is not None and battery < self._preflight_min_battery:
                return f"PREFLIGHT_BATTERY_LOW: battery {battery}% < {self._preflight_min_battery}%"

            if temp is not None and temp >= self._preflight_max_temperature:
                return (
                    "PREFLIGHT_TELLO_TOO_HOT: "
                    f"temperature {temp}C >= {self._preflight_max_temperature}C. "
                    "This is the internal sensor temperature; power off the Tello and let it cool before takeoff."
                )

        return None

    def _state_int(self, key: str) -> Optional[int]:
        value = (self._state.tello_state or {}).get(key)
        if value is None:
            return None

        try:
            return int(float(value))
        except (TypeError, ValueError):
            return None

    def _state_indicates_airborne(self) -> bool:
        height = self._state_int("h")
        if height is not None and height >= 20:
            return True

        return False

    def frame(self) -> Tuple[bool, Optional[any], Optional[str]]:
        """
        回傳 (ok, frame, error)
        - ok False 時，frame 為 None，error 為原因
        """
        with self._lock:
            if not self._state.streaming:
                return False, None, (self._state.last_error or "STREAM_NOT_STARTED")

        try:
            # DJITelloPy returns PIL-derived RGB frames, while OpenCV drawing,
            # JPEG encoding, and Ultralytics numpy inputs expect BGR arrays.
            frame = cv2.cvtColor(self._tello.get_frame_read().frame, cv2.COLOR_RGB2BGR)
            return True, frame, None
        except Exception as e:
            self._state.last_error = f"FRAME_FAILED: {e}"
            return False, None, self._state.last_error

    def rc(self, lr: int, fb: int, ud: int, yaw: int) -> bool:
        try:
            with self._lock:
                self._tello.send_rc_control(lr, fb, ud, yaw)
                self._state.last_error = None
            return True
        except Exception as e:
            self._state.last_error = f"RC_FAILED: {e}"
            return False

    def stop_motion(self) -> bool:
        try:
            with self._lock:
                self._tello.send_rc_control(0, 0, 0, 0)
                self._state.last_error = None
            return True
        except Exception as e:
            self._state.last_error = f"STOP_MOTION_FAILED: {e}"
            return False

    def _start_hover_keepalive(self) -> None:
        if self._hover_thread is not None and self._hover_thread.is_alive():
            return

        self._hover_stop.clear()
        self._hover_thread = threading.Thread(target=self._hover_keepalive_loop, daemon=True)
        self._hover_thread.start()

    def _stop_hover_keepalive(self) -> None:
        self._hover_stop.set()

    def _hover_keepalive_loop(self) -> None:
        while not self._hover_stop.is_set():
            with self._lock:
                if not self._state.flying:
                    break

                try:
                    self._tello.send_rc_control(0, 0, 0, 0)
                except Exception as e:
                    self._state.last_error = f"HOVER_KEEPALIVE_FAILED: {e}"

            sleep(1.0)

    def nudge_rc(self, lr: int = 0, fb: int = 0, ud: int = 0, yaw: int = 0, duration_sec: float = 0.45) -> bool:
        with self._lock:
            if not self._state.flying:
                self._state.last_error = "DRONE_NOT_FLYING"
                return False

        lr = max(-35, min(35, int(lr)))
        fb = max(-35, min(35, int(fb)))
        ud = max(-35, min(35, int(ud)))
        yaw = max(-35, min(35, int(yaw)))
        duration = max(0.1, min(1.0, float(duration_sec)))

        try:
            if not self.rc(lr, fb, ud, yaw):
                return False
            sleep(duration)
            return self.stop_motion()
        except Exception as e:
            self._state.last_error = f"NUDGE_RC_FAILED: {e}"
            return False

    def nudge_vertical(self, ud: int, duration_sec: float = 0.45) -> bool:
        return self.nudge_rc(ud=ud, duration_sec=duration_sec)

    def takeoff(self) -> bool:
        restore_stream = False

        with self._lock:
            if not self._state.connected:
                if not self.connect():
                    return False

            preflight_error = self.preflight_takeoff_error()
            if preflight_error is not None:
                self._state.last_error = preflight_error
                return False

            restore_stream = self._state.streaming
            if restore_stream and not self.stop_stream():
                return False

            try:
                self._enter_sdk_mode()
                sleep(0.5)
                self._tello.send_control_command("takeoff", timeout=Tello.TAKEOFF_TIMEOUT)
                self._state.last_error = None

                verified = False
                for _ in range(16):
                    sleep(0.5)
                    self.refresh_state()
                    if self._state_indicates_airborne():
                        verified = True
                        break

                if not verified:
                    height = self._state_int("h")
                    tof = self._state_int("tof")
                    self._state.flying = False
                    self._state.last_error = (
                        "TAKEOFF_UNVERIFIED: command returned ok but Tello state did not show lift. "
                        f"h={height}, tof={tof}. Check the aircraft, propellers, bottom sensors, and test with the official Tello app."
                    )
                    return False

                self._state.flying = True
                self._start_hover_keepalive()
            except Exception as e:
                self._state.last_error = f"TAKEOFF_FAILED: {e}"
                return False

        self.stop_motion()
        if restore_stream:
            self.start_stream()
        return True

    def land(self) -> bool:
        try:
            with self._lock:
                self._tello.send_control_command("land")
                self._state.flying = False
                self._stop_hover_keepalive()
                self._state.last_error = None
            return True
        except Exception as e:
            self._state.last_error = f"LAND_FAILED: {e}"
            return False

    def emergency(self) -> bool:
        try:
            with self._lock:
                self._tello.send_control_command("emergency")
                self._state.flying = False
                self._stop_hover_keepalive()
                self._state.last_error = None
            return True
        except Exception as e:
            self._state.last_error = f"EMERGENCY_FAILED: {e}"
            return False
