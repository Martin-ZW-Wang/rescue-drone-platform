# Rescue Drone MCP Server

This folder adds a local MCP tool layer for the rescue drone platform.

## Tools

- `get_drone_status`: reads `GET /api/drone/status`
- `get_recent_events`: reads `GET /api/events/recent`
- `get_event_detail`: reads `GET /api/events/{id}`
- `summarize_recent_events`: reads recent events and summarizes them with Ollama

## Setup

```powershell
pip install -r mcp_server/requirements.txt
```

Ollama should already have the local model:

```powershell
ollama list
```

Expected model:

```text
gemma3:4b
```

## Run

Start the rescue platform first:

```powershell
composer dev:drone
```

Then run the MCP server:

```powershell
python mcp_server/server.py
```

For a quick local AI test through the MCP client script:

```powershell
python ai_orchestrator/chat.py "摘要最近 10 筆救援事件"
```

## Environment

Optional overrides:

```powershell
$env:RESCUE_LARAVEL_BASE="http://127.0.0.1:8000"
$env:OLLAMA_BASE_URL="http://127.0.0.1:11434"
$env:OLLAMA_MODEL="gemma3:4b"
```
