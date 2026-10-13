from __future__ import annotations

import os
import platform
import re
import subprocess
import time
from dataclasses import dataclass, asdict
from typing import Any

try:
    from env_loader import load_project_env
except ModuleNotFoundError:
    from .env_loader import load_project_env

try:
    from command_center_errors import describe_error, error_payload
except ModuleNotFoundError:
    from .command_center_errors import describe_error, error_payload


load_project_env()

PREFERRED_CHANNELS_24GHZ = (1, 6, 11)
SUPPORTED_CHANNELS_24GHZ = tuple(range(1, 14))
FRESH_SCAN_SECONDS = int(os.environ.get("RADIO_SCAN_MAX_AGE_SEC", "300"))
CONGESTION_WARN_SCORE = float(os.environ.get("RADIO_CONGESTION_WARN_SCORE", "185"))
DEFAULT_OPERATING_CHANNEL = int(os.environ.get("RADIO_OPERATING_CHANNEL", "6"))
OPENWRT_TIMEOUT_SECONDS = int(os.environ.get("OPENWRT_TIMEOUT_SEC", "20"))


@dataclass
class RadioNetwork:
    ssid: str
    bssid: str | None
    channel: int | None
    signal: int | None
    security: str | None = None
    band: str | None = None


class RadioManager:
    def __init__(self) -> None:
        self._networks: list[RadioNetwork] = []
        self._last_scan_ts: float | None = None
        self._last_error: str | None = None
        self._operating_channel = DEFAULT_OPERATING_CHANNEL
        self._target_channel: int | None = None
        self._hop_mode = "monitor"

    def status(self) -> dict:
        analysis = self._analyze(self._networks)
        return {
            "ok": self._last_error is None,
            "scanner": self._scanner_name(),
            "last_scan_ts": self._last_scan_ts,
            "last_scan_age_sec": None if self._last_scan_ts is None else round(time.time() - self._last_scan_ts, 1),
            "last_error": self._last_error,
            "last_error_info": describe_error(self._last_error),
            "network_count": len(self._networks),
            "operating_channel": self._operating_channel,
            "target_channel": self._target_channel,
            "hop_mode": self._hop_mode,
            "supported_channels": list(SUPPORTED_CHANNELS_24GHZ),
            "preferred_channels": list(PREFERRED_CHANNELS_24GHZ),
            "openwrt": self._openwrt_status(),
            "recommended_channel": analysis["recommended_channel"],
            "congestion": analysis["congestion"],
            "networks": [asdict(item) for item in self._networks[:40]],
        }

    def scan(self) -> dict:
        try:
            networks = self._scan_with_host()
        except FileNotFoundError:
            self._last_error = "RADIO_SCAN_UNAVAILABLE"
            return error_payload(
                "RADIO_SCAN_UNAVAILABLE",
                "Install or enable netsh on Windows, nmcli on Linux, or airport on macOS.",
                status=self.status(),
            )
        except Exception as exc:
            detail = self._friendly_scan_error(str(exc))
            self._last_error = f"RADIO_SCAN_FAILED: {detail}"
            return error_payload("RADIO_SCAN_FAILED", detail, status=self.status())

        self._networks = networks
        self._last_scan_ts = time.time()
        self._last_error = None

        return {
            "ok": True,
            "message": "Radio scan complete",
            **self.status(),
        }

    def preflight(self) -> dict:
        if not self._has_fresh_scan():
            scan_result = self.scan()
            if not scan_result.get("ok"):
                return error_payload(
                    "PREFLIGHT_RADIO_SCAN_REQUIRED",
                    "Takeoff is blocked until the command center can complete a radio scan.",
                    status=self.status(),
                )

        analysis = self._analyze(self._networks)
        current = analysis["congestion"].get(str(self._operating_channel), {})
        issues = []

        if current.get("score", 0) >= CONGESTION_WARN_SCORE:
            issues.append({
                "code": "PREFLIGHT_RADIO_CONGESTED",
                "severity": "warning",
                "message": "Operating channel is congested; auto-hop is recommended before takeoff.",
                "channel": self._operating_channel,
                "score": current.get("score", 0),
            })

        return {
            "ok": not any(item["severity"] == "error" for item in issues),
            "message": "Radio preflight complete",
            "issues": issues,
            "recommended_channel": analysis["recommended_channel"],
            "operating_channel": self._operating_channel,
            "status": self.status(),
        }

    def auto_hop(self) -> dict:
        if not self._has_fresh_scan():
            scan_result = self.scan()
            if not scan_result.get("ok"):
                return scan_result

        analysis = self._analyze(self._networks)
        recommended = analysis["recommended_channel"]
        if recommended is None:
            return error_payload("RADIO_SCAN_FAILED", "No 2.4 GHz channel recommendation was available.", status=self.status())

        return self.set_channel(recommended, automatic=True)

    def set_channel(self, channel: int, automatic: bool = False) -> dict:
        if channel not in SUPPORTED_CHANNELS_24GHZ:
            return error_payload(
                "INVALID_RADIO_CHANNEL",
                f"Supported command-center 2.4GHz channels are {SUPPORTED_CHANNELS_24GHZ}.",
                status=self.status(),
            )

        self._target_channel = channel

        if self._openwrt_enabled():
            return self._apply_openwrt_channel(channel, automatic=automatic)

        if os.environ.get("RADIO_SOFTWARE_HOP_ENABLED", "").lower() in {"1", "true", "yes"}:
            self._operating_channel = channel
            self._hop_mode = "software-applied"
            return {
                "ok": True,
                "message": "Command-center software channel state updated",
                "applied": True,
                "automatic": automatic,
                "status": self.status(),
            }

        self._hop_mode = "pending-router-apply"
        return {
            "ok": True,
            "message": "Best channel selected; router/radio hardware integration is required to apply it physically.",
            "applied": False,
            "automatic": automatic,
            "warning": "HOP_UNSUPPORTED_HARDWARE",
            "warning_info": describe_error("HOP_UNSUPPORTED_HARDWARE"),
            "status": self.status(),
        }

    def _openwrt_enabled(self) -> bool:
        return os.environ.get("OPENWRT_APPLY_ENABLED", "").lower() in {"1", "true", "yes"}

    def _openwrt_status(self) -> dict:
        host = os.environ.get("OPENWRT_HOST", "").strip()
        user = os.environ.get("OPENWRT_USER", "root").strip()
        radio = os.environ.get("OPENWRT_RADIO", "radio0").strip()
        return {
            "enabled": self._openwrt_enabled(),
            "configured": bool(host and user and radio),
            "dry_run": self._openwrt_dry_run_enabled(),
            "host": host or None,
            "user": user or None,
            "port": int(os.environ.get("OPENWRT_PORT", "22")),
            "radio": radio or None,
            "key_path": os.environ.get("OPENWRT_SSH_KEY", "").strip() or None,
        }

    def _openwrt_dry_run_enabled(self) -> bool:
        return os.environ.get("OPENWRT_DRY_RUN", "").lower() in {"1", "true", "yes"}

    def _apply_openwrt_channel(self, channel: int, automatic: bool) -> dict:
        status = self._openwrt_status()
        if not status["configured"]:
            self._hop_mode = "openwrt-not-configured"
            return error_payload(
                "OPENWRT_APPLY_NOT_CONFIGURED",
                "Set OPENWRT_HOST, OPENWRT_USER, and OPENWRT_RADIO before enabling OpenWrt apply.",
                automatic=automatic,
                status=self.status(),
            )

        radio = str(status["radio"])
        if not re.fullmatch(r"[A-Za-z0-9_.-]+", radio):
            self._hop_mode = "openwrt-invalid-radio"
            return error_payload(
                "OPENWRT_INVALID_RADIO",
                f"Invalid OpenWrt radio section: {radio}",
                automatic=automatic,
                status=self.status(),
            )

        if self._openwrt_dry_run_enabled():
            remote_command = " && ".join([
                f"printf '%s\\n' '{int(channel)}' > /tmp/command-center-radio-channel",
                "cat /tmp/command-center-radio-channel",
            ])
        else:
            remote_command = " && ".join([
                f"uci set wireless.{radio}.channel='{int(channel)}'",
                "uci commit wireless",
                "wifi reload",
            ])
        ssh_command = self._openwrt_ssh_command(status, remote_command)

        try:
            result = subprocess.run(
                ssh_command,
                capture_output=True,
                text=True,
                timeout=OPENWRT_TIMEOUT_SECONDS,
                check=False,
            )
        except Exception as exc:
            self._hop_mode = "openwrt-apply-failed"
            return error_payload("OPENWRT_APPLY_FAILED", str(exc), automatic=automatic, status=self.status())

        if result.returncode != 0:
            self._hop_mode = "openwrt-apply-failed"
            detail = (result.stderr or result.stdout or "OpenWrt command failed").strip()
            return error_payload("OPENWRT_APPLY_FAILED", detail, automatic=automatic, status=self.status())

        self._operating_channel = channel
        self._hop_mode = "openwrt-dry-run" if self._openwrt_dry_run_enabled() else "openwrt-applied"
        self._last_error = None
        return {
            "ok": True,
            "message": "OpenWrt dry-run channel command accepted" if self._openwrt_dry_run_enabled() else "OpenWrt router channel applied",
            "applied": not self._openwrt_dry_run_enabled(),
            "dry_run": self._openwrt_dry_run_enabled(),
            "automatic": automatic,
            "channel": channel,
            "openwrt": status,
            "stdout": result.stdout.strip(),
            "status": self.status(),
        }

    def _openwrt_ssh_command(self, status: dict, remote_command: str) -> list[str]:
        command = [
            "ssh",
            "-p",
            str(status["port"]),
            "-o",
            "BatchMode=yes",
            "-o",
            "ConnectTimeout=8",
        ]

        if os.environ.get("OPENWRT_STRICT_HOST_KEY_CHECKING", "accept-new").strip():
            command.extend([
                "-o",
                f"StrictHostKeyChecking={os.environ.get('OPENWRT_STRICT_HOST_KEY_CHECKING', 'accept-new')}",
            ])

        if status.get("key_path"):
            command.extend(["-i", str(status["key_path"])])

        destination = f"{status['user']}@{status['host']}"
        command.extend([destination, remote_command])
        return command

    def _has_fresh_scan(self) -> bool:
        return self._last_scan_ts is not None and (time.time() - self._last_scan_ts) <= FRESH_SCAN_SECONDS

    def _scanner_name(self) -> str:
        system = platform.system().lower()
        if system == "windows":
            return "netsh"
        if system == "linux":
            return "nmcli"
        if system == "darwin":
            return "airport"
        return "unsupported"

    def _scan_with_host(self) -> list[RadioNetwork]:
        scanner = self._scanner_name()
        if scanner == "netsh":
            return self._scan_windows_netsh()
        if scanner == "nmcli":
            return self._scan_linux_nmcli()
        if scanner == "airport":
            return self._scan_macos_airport()
        raise FileNotFoundError(scanner)

    def _scan_windows_netsh(self) -> list[RadioNetwork]:
        result = subprocess.run(
            ["netsh", "wlan", "show", "networks", "mode=bssid"],
            capture_output=True,
            encoding="utf-8",
            errors="replace",
            text=True,
            timeout=18,
            check=False,
        )
        stdout = (result.stdout or "").strip()
        stderr = (result.stderr or "").strip()

        if result.returncode != 0:
            raise RuntimeError(stderr or stdout or "netsh failed")

        if not stdout:
            raise RuntimeError("netsh returned no Wi-Fi scan output.")

        return self._parse_netsh(stdout)

    def _friendly_scan_error(self, detail: str) -> str:
        lower = detail.lower()
        if "location permission" in lower or "location services" in lower or "requires elevation" in lower:
            return (
                "Windows blocked Wi-Fi scanning. Enable Location services, allow desktop apps "
                "to access location, then restart composer dev:drone. If it still fails, run "
                "PowerShell as Administrator."
            )
        if "no wi-fi scan output" in lower:
            return "Windows returned no Wi-Fi scan data. Restart the Python service and try Scan again."
        return detail

    def _parse_netsh(self, output: str) -> list[RadioNetwork]:
        networks: list[RadioNetwork] = []
        current_ssid = ""
        current_security = None
        current_bssid = None
        current_signal = None
        current_band = None
        awaiting_channel_after_band = False

        for raw_line in output.splitlines():
            line = raw_line.strip()
            if not line:
                continue

            ssid_match = re.match(r"SSID\s+\d+\s*:\s*(.*)", line, re.IGNORECASE)
            if ssid_match:
                current_ssid = ssid_match.group(1).strip()
                current_security = None
                current_bssid = None
                current_signal = None
                current_band = None
                awaiting_channel_after_band = False
                continue

            if line.lower().startswith("authentication"):
                current_security = line.split(":", 1)[-1].strip()
                continue

            if re.match(r"BSSID\s+\d+\s*:", line, re.IGNORECASE):
                current_bssid = line.split(":", 1)[-1].strip()
                current_signal = None
                current_band = None
                awaiting_channel_after_band = False
                continue

            if line.lower().startswith("signal"):
                value = line.split(":", 1)[-1].strip().rstrip("%")
                current_signal = self._safe_int(value)
                continue

            signal_match = re.search(r":\s*(\d{1,3})\s*%", line)
            if current_bssid and current_signal is None and signal_match:
                current_signal = self._safe_int(signal_match.group(1))
                continue

            detected_band = self._netsh_band_from_line(line)
            if detected_band:
                current_band = detected_band
                awaiting_channel_after_band = True
                continue

            if line.lower().startswith("channel"):
                channel = self._safe_int(line.split(":", 1)[-1].strip())
                networks.append(RadioNetwork(
                    ssid=current_ssid,
                    bssid=current_bssid,
                    channel=channel,
                    signal=current_signal,
                    security=current_security,
                    band=current_band or self._band_for_channel(channel),
                ))
                awaiting_channel_after_band = False
                continue

            if current_bssid and awaiting_channel_after_band:
                channel = self._netsh_channel_from_line(line)
                if channel is not None:
                    networks.append(RadioNetwork(
                        ssid=current_ssid,
                        bssid=current_bssid,
                        channel=channel,
                        signal=current_signal,
                        security=current_security,
                        band=current_band or self._band_for_channel(channel),
                    ))
                    awaiting_channel_after_band = False

        return networks

    def _netsh_band_from_line(self, line: str) -> str | None:
        if re.search(r"\b2\.4\s*GHz\b", line, re.IGNORECASE):
            return "2.4GHz"
        if re.search(r"\b5\s*GHz\b", line, re.IGNORECASE):
            return "5GHz"
        return None

    def _netsh_channel_from_line(self, line: str) -> int | None:
        if ":" not in line:
            return None

        value = line.split(":", 1)[-1].strip()
        if "%" in value:
            return None

        match = re.fullmatch(r"(\d{1,3})", value)
        if not match:
            return None

        channel = self._safe_int(match.group(1))
        if channel is None or channel <= 0 or channel > 196:
            return None
        return channel

    def _scan_linux_nmcli(self) -> list[RadioNetwork]:
        result = subprocess.run(
            ["nmcli", "-t", "-f", "SSID,BSSID,CHAN,SIGNAL,SECURITY", "dev", "wifi", "list"],
            capture_output=True,
            text=True,
            timeout=18,
            check=False,
        )
        if result.returncode != 0:
            raise RuntimeError(result.stderr.strip() or result.stdout.strip() or "nmcli failed")

        networks: list[RadioNetwork] = []
        for line in result.stdout.splitlines():
            parts = self._split_nmcli(line)
            if len(parts) < 5:
                continue
            channel = self._safe_int(parts[2])
            networks.append(RadioNetwork(
                ssid=parts[0],
                bssid=parts[1],
                channel=channel,
                signal=self._safe_int(parts[3]),
                security=parts[4] or None,
                band=self._band_for_channel(channel),
            ))
        return networks

    def _scan_macos_airport(self) -> list[RadioNetwork]:
        airport = "/System/Library/PrivateFrameworks/Apple80211.framework/Versions/Current/Resources/airport"
        result = subprocess.run(
            [airport, "-s"],
            capture_output=True,
            text=True,
            timeout=18,
            check=False,
        )
        if result.returncode != 0:
            raise RuntimeError(result.stderr.strip() or result.stdout.strip() or "airport failed")

        networks: list[RadioNetwork] = []
        for line in result.stdout.splitlines()[1:]:
            parts = line.split()
            if len(parts) < 4:
                continue
            channel = self._safe_int(parts[-2].split(",", 1)[0])
            signal = abs(self._safe_int(parts[-3]) or 0)
            networks.append(RadioNetwork(
                ssid=" ".join(parts[:-4]) or "<hidden>",
                bssid=parts[-4],
                channel=channel,
                signal=max(0, min(100, 100 - signal)),
                security=parts[-1],
                band=self._band_for_channel(channel),
            ))
        return networks

    def _analyze(self, networks: list[RadioNetwork]) -> dict[str, Any]:
        congestion: dict[str, dict[str, Any]] = {}
        for channel in SUPPORTED_CHANNELS_24GHZ:
            related = [
                item for item in networks
                if item.channel is not None and abs(item.channel - channel) <= 2 and item.band == "2.4GHz"
            ]
            score = round(sum((item.signal or 35) for item in related) + len(related) * 12, 1)
            congestion[str(channel)] = {
                "channel": channel,
                "network_count": len(related),
                "max_signal": max([item.signal or 0 for item in related], default=0),
                "score": score,
            }

        recommended = min(PREFERRED_CHANNELS_24GHZ, key=lambda ch: congestion[str(ch)]["score"])
        return {
            "recommended_channel": recommended,
            "congestion": congestion,
        }

    def _band_for_channel(self, channel: int | None) -> str | None:
        if channel is None:
            return None
        if 1 <= channel <= 14:
            return "2.4GHz"
        if channel > 14:
            return "5GHz"
        return None

    def _safe_int(self, value: Any) -> int | None:
        try:
            return int(float(str(value).strip()))
        except (TypeError, ValueError):
            return None

    def _split_nmcli(self, line: str) -> list[str]:
        parts = []
        current = []
        escaped = False
        for char in line:
            if escaped:
                current.append(char)
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == ":":
                parts.append("".join(current))
                current = []
            else:
                current.append(char)
        parts.append("".join(current))
        return parts
