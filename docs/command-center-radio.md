# Command Center Radio Control

This project now treats radio awareness as a command-center responsibility.
The drone remains a flight and camera source; scanning, preflight checks, and
channel decisions run in the local Python command-center service.

## API

Laravel proxy endpoints:

- `GET /api/drone/radio/status`
- `POST /api/drone/radio/scan`
- `POST /api/drone/radio/preflight`
- `POST /api/drone/radio/auto-hop`
- `POST /api/drone/radio/channel`
- `GET /api/drone/errors`

Python service endpoints:

- `GET /radio/status`
- `POST /radio/scan`
- `POST /radio/preflight`
- `POST /radio/auto-hop`
- `POST /radio/channel`

## Behavior

- `radio/scan` scans nearby Wi-Fi networks from the command-center host.
- `radio/preflight` requires a fresh scan before takeoff and warns when the
  current control channel is congested.
- `radio/auto-hop` selects the least congested rescue channel among `1`, `6`,
  and `11`.
- Physical channel changes can be applied to an OpenWrt router when
  `OPENWRT_APPLY_ENABLED=true` and SSH settings are configured. Without that,
  the API records the selected target channel and returns
  `HOP_UNSUPPORTED_HARDWARE` as a warning.

## OpenWrt Apply

When enabled, `radio/auto-hop` and `radio/channel` run this command over SSH:

```sh
uci set wireless.radio0.channel='<channel>' && uci commit wireless && wifi reload
```

The channel is validated by the command-center service and currently limited to
`1`, `6`, and `11`. The radio section name is also validated before the SSH
command is built.

Recommended OpenWrt setup:

1. Create or reuse a command-center SSH key.
2. Add the command-center public key to `/etc/dropbear/authorized_keys` on the
   OpenWrt router.
3. Confirm manual SSH works from the command center:

```powershell
ssh root@192.168.1.1 "uci get wireless.radio0.channel"
```

4. Set the `.env` values below and restart `composer dev:drone`.

## Error Codes

The Laravel catalog is defined in `app/Support/CommandCenterErrors.php`.
The Python catalog is defined in `python_service/command_center_errors.py`.

Important radio and protection codes:

- `PREFLIGHT_RADIO_SCAN_REQUIRED`
- `PREFLIGHT_RADIO_CONGESTED`
- `RADIO_SCAN_UNAVAILABLE`
- `RADIO_SCAN_FAILED`
- `INVALID_RADIO_CHANNEL`
- `HOP_UNSUPPORTED_HARDWARE`
- `DRONE_NOT_FLYING`
- `PREFLIGHT_BATTERY_LOW`
- `PREFLIGHT_TELLO_TOO_HOT`

## Environment

```dotenv
RADIO_OPERATING_CHANNEL=6
RADIO_SCAN_MAX_AGE_SEC=300
RADIO_CONGESTION_WARN_SCORE=185
RADIO_SOFTWARE_HOP_ENABLED=false
OPENWRT_APPLY_ENABLED=false
OPENWRT_HOST=192.168.1.1
OPENWRT_USER=root
OPENWRT_PORT=22
OPENWRT_RADIO=radio0
OPENWRT_SSH_KEY=
OPENWRT_STRICT_HOST_KEY_CHECKING=accept-new
OPENWRT_TIMEOUT_SEC=20
```

Set `RADIO_SOFTWARE_HOP_ENABLED=true` only for lab simulations. It updates the
command-center software channel state but does not reconfigure Tello Wi-Fi or a
physical router.

Set `OPENWRT_APPLY_ENABLED=true` only when the router belongs to the project and
is safe to reload. `wifi reload` can briefly disconnect clients.
