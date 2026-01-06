# Architecture

## High-level flow

1. HTTP request arrives at `public/index.php` (web root).
2. The request is routed to `src/MCPServer.php`.
3. Server checks:
   - `maintenance_mode` (503)
   - HTTPS enforcement (403)
4. Auth:
   - bearer API key validation + scope extraction
5. Rate limit:
   - DB-based per-key per-minute (429)
6. Signed request verification:
   - timestamp + HMAC signature (401)
7. MCP method dispatch:
   - `tools/list`
   - `tools/call`
8. Tool handler calls WordPress REST API via `src/WPClient.php`.
9. Result is returned and logged into `mcp_logs`.

## Components

- `public/index.php`: entrypoint
- `src/MCPServer.php`: request parsing, auth, security gates, tool dispatch
- `src/Auth.php`: bearer key validation + scope checks
- `src/RateLimiter.php`: DB-based rate limiting
- `src/WPClient.php`: WordPress REST API client
- `src/Tools/*`: tool implementations
- `sql/schema.sql`: tables for keys, sites, logs, rate limit windows

## Shared hosting constraints

- No background workers
- No long-lived processes
- No streaming responses
- Minimal dependencies (PDO only)

Last regenerated: 2026-01-06
