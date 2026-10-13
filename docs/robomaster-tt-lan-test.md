# RoboMaster TT LAN Test

Use this flow when the RoboMaster TT, command-center computer, and tablet are
connected to the same ordinary router.

## Network Layout

```text
Router
  -> Command-center computer
  -> Tablet or phone
  -> RoboMaster TT
```

The command-center computer runs Laravel and the Python drone service. The
tablet opens the Laravel dashboard over the router LAN.

## Environment

Set the TT IP assigned by the router:

```dotenv
TELLO_HOST=192.168.1.120
TELLO_COMMAND_PORT=8889
DRONE_CORS_ORIGINS=*
```

`TELLO_HOST` is the important value. `TELLO_COMMAND_PORT` is kept at the SDK
default `8889`.

## Start

```powershell
composer dev:drone
```

The `dev:drone` script binds Laravel to `0.0.0.0:8000`, so a tablet on the same
router can open:

```text
http://<command-center-computer-ip>:8000
```

The dashboard automatically connects Socket.IO to the same host on port `5001`.

## Finding The IPs

On the command-center computer:

```powershell
ipconfig
```

Use the Wi-Fi adapter IPv4 address for the tablet URL.

For RoboMaster TT, use one of these:

- Router DHCP client list.
- Router app/admin page.
- A LAN scanner approved for your network.

For stable testing, reserve a fixed DHCP address for the RoboMaster TT in the
router admin page.
