# Rescue Drone Platform

An integrated rescue-drone command center built with Laravel, Vite, a Python
Flask-SocketIO drone service, YOLO-based casualty detection, posture assessment,
incident review, local AI assistance through Ollama/MCP, and optional OpenWrt
radio-channel testing.

Repository:

```text
https://github.com/Martin-ZW-Wang/rescue-drone-platform
```

## Features

- Live drone video dashboard
- DJI Tello / RoboMaster TT command proxy
- Casualty detection and posture-assisted assessment
- Incident review with original and AI-annotated evidence images
- Local AI assistant named Sunny
- Command Center Radio scan and channel recommendation
- Optional OpenWrt dry-run VM for channel-hopping tests

## Architecture

```text
Browser dashboard
  -> Laravel Blade UI / Laravel API
  -> DroneService
  -> Python Flask-SocketIO service on :5001
  -> Tello / RoboMaster TT, OpenCV, YOLO SEG, YOLO Pose
  -> Incident ingest API
  -> SQLite rescue_events table
  -> Incident Review and Sunny AI Assistant
  -> Ollama and MCP tools
```

## Prerequisites

Install these tools first:

- PHP 8.2 or newer
- Composer
- Node.js 20 or newer
- npm
- Python 3.10 to 3.12
- SQLite PHP extension
- Git
- Optional: Ollama for Sunny AI
- Optional: Oracle VirtualBox for OpenWrt dry-run tests
- Optional: DJI Tello / RoboMaster TT for live drone operation

Check your local versions:

```powershell
php -v
composer --version
node -v
npm -v
python --version
git --version
```

## Fresh Setup

Clone the repository:

```powershell
git clone https://github.com/Martin-ZW-Wang/rescue-drone-platform.git
cd rescue-drone-platform
```

Install PHP dependencies:

```powershell
composer install
```

Install Node dependencies:

```powershell
npm install
```

Create the Laravel environment file:

```powershell
copy .env.example .env
php artisan key:generate
```

Create the SQLite database and run migrations:

```powershell
New-Item -ItemType File -Force database/database.sqlite
php artisan migrate
```

On macOS/Linux:

```bash
touch database/database.sqlite
php artisan migrate
```

Install Python drone-service dependencies:

```powershell
python -m venv python_service/.venv
python_service\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install -r python_service/requirements.txt
```

Install MCP dependencies:

```powershell
pip install -r mcp_server/requirements.txt
```

Build the frontend once:

```powershell
npm run build
```

## Environment Configuration

The default `.env.example` is ready for local development. Important values:

```dotenv
APP_URL=http://localhost
DB_CONNECTION=sqlite

DRONE_API_BASE=http://127.0.0.1:5001
DRONE_CORS_ORIGINS=*
LARAVEL_EVENT_INGEST_URL=http://127.0.0.1:8000/api/events/ingest

TELLO_HOST=192.168.10.1
TELLO_COMMAND_PORT=8889

OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=gemma3:4b

RADIO_OPERATING_CHANNEL=6
RADIO_SCAN_MAX_AGE_SEC=300
RADIO_CONGESTION_WARN_SCORE=185
RADIO_SOFTWARE_HOP_ENABLED=false

OPENWRT_APPLY_ENABLED=false
OPENWRT_HOST=192.168.1.1
OPENWRT_USER=root
OPENWRT_PORT=22
OPENWRT_RADIO=radio0
OPENWRT_DRY_RUN=false
OPENWRT_SSH_KEY=
```

For VirtualBox OpenWrt dry-run, use local values like:

```dotenv
OPENWRT_APPLY_ENABLED=true
OPENWRT_DRY_RUN=true
OPENWRT_HOST=127.0.0.1
OPENWRT_PORT=2222
OPENWRT_USER=root
OPENWRT_RADIO=radio0
OPENWRT_SSH_KEY=C:\Users\user\.ssh\openwrt_dry_run
```

## Model Weights

YOLO model weights are not committed to Git because they are large binary files.
Place them manually in `python_service/`:

```text
python_service/SEG6-2.pt
python_service/yolov8n-pose.pt
```

The current code expects:

```text
python_service/drone.py -> SEG6-2.pt
python_service/drone.py -> yolov8n-pose.pt
```

Without these model files, the Python drone service can fail during startup
because the models are loaded at import time.

## Running The Project

### Option A: Laravel dashboard only

Use this for Laravel UI, routes, events pages, and frontend development:

```powershell
composer dev
```

Open:

```text
http://127.0.0.1:8000
```

### Option B: Full local drone stack

Use this when the Python drone service and MCP server should run together:

```powershell
composer dev:drone
```

This starts:

```text
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:listen --tries=1 --timeout=0
npm run dev
python python_service/drone_server.py
python mcp_server/server.py
```

Open:

```text
Dashboard: http://127.0.0.1:8000
Python drone service: http://127.0.0.1:5001
```

### Option C: OpenWrt dry-run workflow

Use this when a VirtualBox OpenWrt VM is configured and you want the app and VM to
start together:

```powershell
composer dev:drone:openwrt
```

The script:

1. Finds `VBoxManage.exe`
2. Starts the `OpenWrtDryRun` VM
3. Waits for SSH on `OPENWRT_HOST:OPENWRT_PORT`
4. Verifies the SSH key when available
5. Runs `composer dev:drone`
6. Attempts to stop the VM when the app exits

If VirtualBox is installed somewhere else, set:

```powershell
$env:VBOXMANAGE="C:\Program Files\Oracle\VirtualBox\VBoxManage.exe"
```

## Ollama / Sunny AI

Install and start Ollama, then pull the configured model:

```powershell
ollama pull gemma3:4b
ollama list
```

Sunny uses:

```dotenv
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=gemma3:4b
```

Open Sunny:

```text
http://127.0.0.1:8000/mcp
```

## Tello / RoboMaster TT Operation

For real drone operation:

1. Connect the computer to the Tello / RoboMaster TT Wi-Fi network, or route the
   Tello network through a supported router setup.
2. Confirm the drone host is reachable:

   ```powershell
   ping 192.168.10.1
   ```

3. Start the full stack:

   ```powershell
   composer dev:drone
   ```

4. Open the dashboard and use the flight-control panel.

Flight controls are intentionally guarded by state checks. Manual trim commands
are ignored when the drone is not flying.

## Radio / Channel Hopping

The dashboard includes Command Center Radio:

- Scan nearby 2.4 GHz channels
- Recommend a less congested control channel
- Require preflight scan before takeoff
- Apply target channel through OpenWrt when configured

OpenWrt router control requires a router that supports OpenWrt and SSH access.
Do not flash firmware for a different router model.

Related docs:

```text
docs/command-center-radio.md
docs/robomaster-tt-lan-test.md
```

## Routes

Web routes:

| Path | Purpose |
| --- | --- |
| `/` | Dashboard |
| `/events` | Incident Review |
| `/events/{event}` | Incident detail and visual evidence |
| `/mcp` | Sunny local AI assistant |

Drone API:

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/drone/status` | Read drone and service state |
| GET | `/api/drone/errors` | Read command-center error catalog |
| POST | `/api/drone/start` | Start casualty detection |
| POST | `/api/drone/stop` | Stop casualty detection |
| POST | `/api/drone/takeoff` | Take off |
| POST | `/api/drone/land` | Land |
| POST | `/api/drone/emergency` | Emergency stop |
| POST | `/api/drone/sdk/reset` | Reset SDK state |
| POST | `/api/drone/pose/start` | Enable posture assessment |
| POST | `/api/drone/pose/stop` | Disable posture assessment |
| POST | `/api/drone/radio/scan` | Scan radio channels |
| POST | `/api/drone/radio/preflight` | Run preflight radio check |
| POST | `/api/drone/radio/auto-hop` | Select or apply recommended channel |
| POST | `/api/drone/radio/channel` | Apply selected channel |

Event API:

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/api/events/ingest` | Python service ingests detection event |
| GET | `/api/events/recent` | Recent incidents |
| GET | `/api/events/{event}` | Incident detail |

MCP AI API:

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/mcp/status` | MCP, model, drone, and database status |
| GET | `/api/mcp/events/recent` | Recent events for Sunny |
| DELETE | `/api/mcp/events/clear` | Clear test incidents |
| POST | `/api/mcp/summarize` | Ask Sunny for analysis |

## Verification Commands

Run these before pushing changes:

```powershell
php -l app/Services/DroneService.php
php -l routes/api.php
php -l routes/web.php
php -l resources/views/layouts/app.blade.php
npm run build
python -m py_compile python_service/drone_server.py python_service/drone.py python_service/drone_runtime.py python_service/radio_manager.py
```

Laravel tests:

```powershell
composer test
```

## Git Hygiene

Do not commit:

- `.env`
- `.ssh/`
- `vendor/`
- `node_modules/`
- Python virtual environments
- SQLite databases
- YOLO weights (`*.pt`, `*.onnx`, etc.)
- OpenWrt VM images (`*.img`, `*.vdi`)
- Generated rescue event images (`public/rescue-events/`)

Commit source code, migrations, docs, and configuration examples only.

## Troubleshooting

### Laravel cannot find the SQLite database

```powershell
New-Item -ItemType File -Force database/database.sqlite
php artisan migrate
```

### Python service cannot import a package

```powershell
python_service\.venv\Scripts\Activate.ps1
pip install -r python_service/requirements.txt
```

### Python service fails at YOLO startup

Check that these files exist:

```text
python_service/SEG6-2.pt
python_service/yolov8n-pose.pt
```

### Sunny does not answer

```powershell
ollama list
curl http://127.0.0.1:11434/api/tags
```

### OpenWrt SSH times out

```powershell
ssh root@127.0.0.1 -p 2222
```

Then confirm `OPENWRT_HOST`, `OPENWRT_PORT`, and `OPENWRT_SSH_KEY` in `.env`.
