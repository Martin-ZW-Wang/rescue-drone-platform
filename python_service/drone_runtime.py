from __future__ import annotations

import threading
from dataclasses import dataclass
from time import sleep
from typing import Optional, Tuple

import cv2
from djitellopy import Tello


@dataclass
class DroneState:
    connected: bool = False
    streaming: bool = False
    battery: Optional[int] = None
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
        self._stop = threading.Event()

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

    def _poll_battery(self) -> None:
        while not self._stop.is_set():
            try:
                with self._lock:
                    if self._state.connected:
                        self._state.battery = int(self._tello.get_battery())
                        self._state.last_error = None
            except Exception as e:
                self._state.last_error = f"BATTERY_FAILED: {e}"
            sleep(3)

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
            return True
        except Exception as e:
            self._state.last_error = f"RC_FAILED: {e}"
            return False

    def land(self) -> bool:
        try:
            with self._lock:
                self._tello.land()
            return True
        except Exception as e:
            self._state.last_error = f"LAND_FAILED: {e}"
            return False
