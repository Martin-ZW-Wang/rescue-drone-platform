from __future__ import annotations

import json
import os
from typing import Any

import requests
from mcp.server.fastmcp import FastMCP


mcp = FastMCP("rescue-drone-platform")

LARAVEL_BASE = os.getenv("RESCUE_LARAVEL_BASE", "http://127.0.0.1:8000").rstrip("/")
OLLAMA_BASE = os.getenv("OLLAMA_BASE_URL", "http://127.0.0.1:11434").rstrip("/")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "gemma3:4b")


def _get_json(path: str, **params: Any) -> dict[str, Any]:
    response = requests.get(f"{LARAVEL_BASE}{path}", params=params, timeout=8)
    response.raise_for_status()
    return response.json()


def _ask_ollama(prompt: str) -> str:
    response = requests.post(
        f"{OLLAMA_BASE}/api/generate",
        json={
            "model": OLLAMA_MODEL,
            "prompt": prompt,
            "stream": False,
        },
        timeout=90,
    )
    response.raise_for_status()
    return response.json().get("response", "")


@mcp.tool()
def get_drone_status() -> dict[str, Any]:
    """Get current drone connection, streaming, tracking, battery, and error status."""
    return _get_json("/api/drone/status")


@mcp.tool()
def get_recent_events(limit: int = 10) -> dict[str, Any]:
    """Get recent rescue detection events from Laravel."""
    limit = max(1, min(int(limit), 50))
    return _get_json("/api/events/recent", limit=limit)


@mcp.tool()
def get_event_detail(event_id: int) -> dict[str, Any]:
    """Get one rescue detection event by database ID."""
    return _get_json(f"/api/events/{int(event_id)}")


@mcp.tool()
def summarize_recent_events(limit: int = 10) -> dict[str, Any]:
    """Summarize recent rescue events with the local Ollama model."""
    events_payload = get_recent_events(limit)
    prompt = (
        "你是救援無人機平台的本地 AI 助手。請根據以下 JSON 事件資料，"
        "用繁體中文輸出簡潔摘要，包含事件數量、最高風險、需要注意的狀態，"
        "以及下一步建議。不要編造資料。\n\n"
        f"{json.dumps(events_payload, ensure_ascii=False, indent=2)}"
    )

    return {
        "ok": True,
        "model": OLLAMA_MODEL,
        "summary": _ask_ollama(prompt),
        "source": events_payload,
    }


if __name__ == "__main__":
    mcp.run()
