# MCP Event Database Access

The MCP server reads rescue events in two layers:

1. Preferred path: Laravel API
   - `GET /api/events/recent`
   - `GET /api/events/{id}`
2. Fallback path: direct database read
   - Reads `.env`
   - Supports `DB_CONNECTION=mysql`, `mariadb`, and `sqlite`
   - Uses `PyMySQL` for MySQL/MariaDB

For this project, `.env` currently uses:

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=rescue_drone_platform
```

So `mcp_server/server.py` can now read `rescue_events` directly from MySQL when
the Laravel API is unavailable.

Install MCP dependencies:

```powershell
pip install -r mcp_server/requirements.txt
```

Quick local check:

```powershell
python -c "from mcp_server.server import _db_recent_events; print(_db_recent_events(5))"
```
