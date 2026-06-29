from __future__ import annotations

import argparse
import asyncio
import json
import os
import sys
from pathlib import Path
from typing import Any

import requests
from mcp import ClientSession, StdioServerParameters
from mcp.client.stdio import stdio_client


ROOT = Path(__file__).resolve().parents[1]
MCP_SERVER = ROOT / "mcp_server" / "server.py"
OLLAMA_BASE = os.getenv("OLLAMA_BASE_URL", "http://127.0.0.1:11434").rstrip("/")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "gemma3:4b")


def _tool_result_to_data(result: Any) -> Any:
    structured = getattr(result, "structuredContent", None) or getattr(result, "structured_content", None)
    if structured is not None:
        return structured

    content = getattr(result, "content", [])
    if content:
        text = getattr(content[0], "text", None)
        if text:
            try:
                return json.loads(text)
            except json.JSONDecodeError:
                return text

    return result


def _ask_ollama(question: str, tool_name: str, tool_data: Any) -> str:
    prompt = (
        "你是救援無人機平台的本地 AI 助手。請根據工具回傳資料回答使用者問題。"
        "使用繁體中文，保持簡潔，不能編造沒有出現在資料中的內容。\n\n"
        f"使用者問題：{question}\n\n"
        f"工具名稱：{tool_name}\n\n"
        f"工具資料：{json.dumps(tool_data, ensure_ascii=False, indent=2)}"
    )

    response = requests.post(
        f"{OLLAMA_BASE}/api/generate",
        json={"model": OLLAMA_MODEL, "prompt": prompt, "stream": False},
        timeout=90,
    )
    response.raise_for_status()
    return response.json().get("response", "")


def _select_tool(question: str, limit: int) -> tuple[str, dict[str, Any]]:
    lowered = question.lower()

    if "狀態" in question or "status" in lowered or "電量" in question:
        return "get_drone_status", {}

    if "摘要" in question or "summary" in lowered or "總結" in question:
        return "summarize_recent_events", {"limit": limit}

    return "get_recent_events", {"limit": limit}


async def main() -> None:
    parser = argparse.ArgumentParser(description="Ask local Ollama to analyze rescue drone MCP data.")
    parser.add_argument("question", nargs="?", default="摘要最近 10 筆救援事件")
    parser.add_argument("--limit", type=int, default=10)
    args = parser.parse_args()

    tool_name, tool_args = _select_tool(args.question, args.limit)
    server_params = StdioServerParameters(command=sys.executable, args=[str(MCP_SERVER)])

    async with stdio_client(server_params) as (read, write):
        async with ClientSession(read, write) as session:
            await session.initialize()
            result = await session.call_tool(tool_name, tool_args)

    tool_data = _tool_result_to_data(result)

    if tool_name == "summarize_recent_events" and isinstance(tool_data, dict) and "summary" in tool_data:
        print(tool_data["summary"])
        return

    print(_ask_ollama(args.question, tool_name, tool_data))


if __name__ == "__main__":
    asyncio.run(main())
