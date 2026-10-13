from __future__ import annotations


ERROR_CATALOG = {
    "OK": {
        "severity": "info",
        "message": "Operation completed.",
    },
    "CONNECT_FAILED": {
        "severity": "error",
        "message": "Unable to connect to the drone.",
    },
    "STREAMON_FAILED": {
        "severity": "error",
        "message": "Unable to start the drone video stream.",
    },
    "STREAM_NOT_STARTED": {
        "severity": "warning",
        "message": "Video stream is not started.",
    },
    "DRONE_NOT_FLYING": {
        "severity": "warning",
        "message": "Flight command ignored because the drone is not flying.",
    },
    "PREFLIGHT_BATTERY_LOW": {
        "severity": "error",
        "message": "Preflight check failed because the drone battery is too low.",
    },
    "PREFLIGHT_TELLO_TOO_HOT": {
        "severity": "error",
        "message": "Preflight check failed because the drone reports high temperature.",
    },
    "PREFLIGHT_RADIO_SCAN_REQUIRED": {
        "severity": "error",
        "message": "A fresh command-center radio scan is required before takeoff.",
    },
    "PREFLIGHT_RADIO_CONGESTED": {
        "severity": "warning",
        "message": "The operating channel appears congested.",
    },
    "RADIO_SCAN_UNAVAILABLE": {
        "severity": "warning",
        "message": "No supported radio scanner is available on this command-center host.",
    },
    "RADIO_SCAN_FAILED": {
        "severity": "error",
        "message": "Command-center radio scan failed.",
    },
    "INVALID_RADIO_CHANNEL": {
        "severity": "error",
        "message": "Requested radio channel is not supported.",
    },
    "HOP_UNSUPPORTED_HARDWARE": {
        "severity": "warning",
        "message": "Automatic channel application requires router or radio hardware integration.",
    },
    "OPENWRT_APPLY_NOT_CONFIGURED": {
        "severity": "error",
        "message": "OpenWrt apply is enabled but router SSH settings are incomplete.",
    },
    "OPENWRT_APPLY_FAILED": {
        "severity": "error",
        "message": "OpenWrt router rejected or failed the channel apply command.",
    },
    "OPENWRT_INVALID_RADIO": {
        "severity": "error",
        "message": "OpenWrt radio section name is invalid.",
    },
}


def describe_error(code: str | None) -> dict:
    if not code:
        code = "OK"

    root_code = str(code).split(":", 1)[0]
    meta = ERROR_CATALOG.get(root_code, {
        "severity": "error",
        "message": "Uncataloged command-center error.",
    })

    return {
        "code": root_code,
        "severity": meta["severity"],
        "message": meta["message"],
        "raw": code,
    }


def error_payload(code: str, detail: str | None = None, **extra) -> dict:
    payload = {
        "ok": False,
        "error": code,
        "error_info": describe_error(code),
    }
    if detail:
        payload["message"] = detail
    payload.update(extra)
    return payload
