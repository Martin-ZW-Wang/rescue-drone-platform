from __future__ import annotations

import json
import os
import sqlite3
from pathlib import Path
from typing import Any

import requests
from mcp.server.fastmcp import FastMCP


PROJECT_ROOT = Path(__file__).resolve().parents[1]

mcp = FastMCP("rescue-drone-platform")

LARAVEL_BASE = os.getenv("RESCUE_LARAVEL_BASE", "http://127.0.0.1:8000").rstrip("/")
OLLAMA_BASE = os.getenv("OLLAMA_BASE_URL", "http://127.0.0.1:11434").rstrip("/")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "gemma3:4b")


def _dotenv_values() -> dict[str, str]:
    values: dict[str, str] = {}
    env_path = PROJECT_ROOT / ".env"
    if not env_path.exists():
        return values

    for raw_line in env_path.read_text(encoding="utf-8", errors="ignore").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key.strip()] = value.strip().strip('"').strip("'")

    return values


DOTENV = _dotenv_values()


def _env(key: str, default: str | None = None) -> str | None:
    return os.getenv(key) or DOTENV.get(key) or default


def _db_connection() -> str:
    return (_env("RESCUE_DB_CONNECTION") or _env("DB_CONNECTION", "sqlite") or "sqlite").lower()


def _sqlite_path() -> Path:
    configured = _env("RESCUE_DB_PATH") or (_env("DB_DATABASE") if _db_connection() == "sqlite" else None)
    if configured:
        path = Path(configured)
        return path if path.is_absolute() else PROJECT_ROOT / path

    return PROJECT_ROOT / "database" / "database.sqlite"


def _json_or_none(value: Any) -> Any:
    if value in (None, ""):
        return None

    try:
        return json.loads(value)
    except (TypeError, json.JSONDecodeError):
        return value


def _event_from_row(row: sqlite3.Row) -> dict[str, Any]:
    return {
        "id": row["id"],
        "event_type": row["event_type"],
        "tid": row["tid"],
        "status": row["status"],
        "conf": row["conf"],
        "message": row["message"],
        "bbox_json": _json_or_none(row["bbox_json"]),
        "dbg_json": _json_or_none(row["dbg_json"]),
        "event_time": row["event_time"],
        "review_status": row["review_status"],
        "review_note": row["review_note"],
        "created_at": row["created_at"],
        "updated_at": row["updated_at"],
    }


def _get_json(path: str, **params: Any) -> dict[str, Any]:
    response = requests.get(f"{LARAVEL_BASE}{path}", params=params, timeout=8)
    response.raise_for_status()
    return response.json()


def _db_recent_events(limit: int = 10) -> dict[str, Any]:
    if _db_connection() in {"mysql", "mariadb"}:
        return _mysql_recent_events(limit)

    db_path = _sqlite_path()
    if not db_path.exists():
        return {
            "ok": False,
            "source": "sqlite",
            "error": "DB_NOT_FOUND",
            "message": f"SQLite database not found: {db_path}",
            "events": [],
        }

    try:
        with sqlite3.connect(db_path) as conn:
            conn.row_factory = sqlite3.Row
            rows = conn.execute(
                """
                SELECT id, event_type, tid, status, conf, message, bbox_json, dbg_json,
                       event_time, review_status, review_note, created_at, updated_at
                FROM rescue_events
                ORDER BY COALESCE(event_time, created_at) DESC, id DESC
                LIMIT ?
                """,
                (limit,),
            ).fetchall()
    except sqlite3.Error as exc:
        return {
            "ok": False,
            "source": "sqlite",
            "error": "DB_QUERY_FAILED",
            "message": str(exc),
            "db_path": str(db_path),
            "events": [],
        }

    return {
        "ok": True,
        "source": "sqlite",
        "db_path": str(db_path),
        "events": [_event_from_row(row) for row in rows],
    }


def _db_event_detail(event_id: int) -> dict[str, Any]:
    if _db_connection() in {"mysql", "mariadb"}:
        return _mysql_event_detail(event_id)

    db_path = _sqlite_path()
    if not db_path.exists():
        return {
            "ok": False,
            "source": "sqlite",
            "error": "DB_NOT_FOUND",
            "message": f"SQLite database not found: {db_path}",
        }

    try:
        with sqlite3.connect(db_path) as conn:
            conn.row_factory = sqlite3.Row
            row = conn.execute(
                """
                SELECT id, event_type, tid, status, conf, message, bbox_json, dbg_json,
                       event_time, review_status, review_note, created_at, updated_at
                FROM rescue_events
                WHERE id = ?
                LIMIT 1
                """,
                (event_id,),
            ).fetchone()
    except sqlite3.Error as exc:
        return {
            "ok": False,
            "source": "sqlite",
            "error": "DB_QUERY_FAILED",
            "message": str(exc),
            "db_path": str(db_path),
        }

    if row is None:
        return {
            "ok": False,
            "source": "sqlite",
            "error": "EVENT_NOT_FOUND",
            "message": f"Event #{event_id} was not found.",
        }

    return {
        "ok": True,
        "source": "sqlite",
        "db_path": str(db_path),
        "event": _event_from_row(row),
    }


def _mysql_connection():
    try:
        import pymysql
    except ModuleNotFoundError as exc:
        raise RuntimeError("PyMySQL is not installed. Run: pip install -r mcp_server/requirements.txt") from exc

    return pymysql.connect(
        host=_env("DB_HOST", "127.0.0.1"),
        port=int(_env("DB_PORT", "3306") or "3306"),
        user=_env("DB_USERNAME", "root"),
        password=_env("DB_PASSWORD", "") or "",
        database=_env("DB_DATABASE", "rescue_drone_platform"),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=8,
        read_timeout=8,
    )


def _mysql_recent_events(limit: int = 10) -> dict[str, Any]:
    try:
        with _mysql_connection() as conn:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT id, event_type, tid, status, conf, message, bbox_json, dbg_json,
                           event_time, review_status, review_note, created_at, updated_at
                    FROM rescue_events
                    ORDER BY COALESCE(event_time, created_at) DESC, id DESC
                    LIMIT %s
                    """,
                    (limit,),
                )
                rows = cursor.fetchall()
    except Exception as exc:
        return {
            "ok": False,
            "source": "mysql",
            "error": "DB_QUERY_FAILED",
            "message": str(exc),
            "events": [],
        }

    return {
        "ok": True,
        "source": "mysql",
        "database": _env("DB_DATABASE", "rescue_drone_platform"),
        "events": [_event_from_mapping(row) for row in rows],
    }


def _mysql_event_detail(event_id: int) -> dict[str, Any]:
    try:
        with _mysql_connection() as conn:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT id, event_type, tid, status, conf, message, bbox_json, dbg_json,
                           event_time, review_status, review_note, created_at, updated_at
                    FROM rescue_events
                    WHERE id = %s
                    LIMIT 1
                    """,
                    (event_id,),
                )
                row = cursor.fetchone()
    except Exception as exc:
        return {
            "ok": False,
            "source": "mysql",
            "error": "DB_QUERY_FAILED",
            "message": str(exc),
        }

    if row is None:
        return {
            "ok": False,
            "source": "mysql",
            "error": "EVENT_NOT_FOUND",
            "message": f"Event #{event_id} was not found.",
        }

    return {
        "ok": True,
        "source": "mysql",
        "database": _env("DB_DATABASE", "rescue_drone_platform"),
        "event": _event_from_mapping(row),
    }


def _event_from_mapping(row: dict[str, Any]) -> dict[str, Any]:
    return {
        "id": row.get("id"),
        "event_type": row.get("event_type"),
        "tid": row.get("tid"),
        "status": row.get("status"),
        "conf": row.get("conf"),
        "message": row.get("message"),
        "bbox_json": _json_or_none(row.get("bbox_json")),
        "dbg_json": _json_or_none(row.get("dbg_json")),
        "event_time": str(row.get("event_time")) if row.get("event_time") is not None else None,
        "review_status": row.get("review_status"),
        "review_note": row.get("review_note"),
        "created_at": str(row.get("created_at")) if row.get("created_at") is not None else None,
        "updated_at": str(row.get("updated_at")) if row.get("updated_at") is not None else None,
    }


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
    """Get recent rescue detection events from Laravel, falling back to SQLite."""
    limit = max(1, min(int(limit), 50))

    try:
        payload = _get_json("/api/events/recent", limit=limit)
        payload["source"] = "laravel_api"
        return payload
    except Exception as exc:
        payload = _db_recent_events(limit)
        payload["api_error"] = str(exc)
        return payload


@mcp.tool()
def get_event_detail(event_id: int) -> dict[str, Any]:
    """Get one rescue detection event by database ID."""
    event_id = int(event_id)

    try:
        payload = _get_json(f"/api/events/{event_id}")
        payload["source"] = "laravel_api"
        return payload
    except Exception as exc:
        payload = _db_event_detail(event_id)
        payload["api_error"] = str(exc)
        return payload


@mcp.tool()
def summarize_recent_events(limit: int = 10) -> dict[str, Any]:
    """Summarize recent rescue events with the local Ollama model."""
    events_payload = get_recent_events(limit)
    prompt = (
        "You are an incident-support assistant for a rescue drone command center. "
        "Summarize the recent rescue events, highlight urgent items, note confidence "
        "and timestamps, and recommend operator next steps. Answer in Traditional Chinese.\n\n"
        f"EVENTS JSON:\n{json.dumps(events_payload, ensure_ascii=False, indent=2)}"
    )

    return {
        "ok": True,
        "model": OLLAMA_MODEL,
        "summary": _ask_ollama(prompt),
        "source": events_payload,
    }


if __name__ == "__main__":
    mcp.run()
