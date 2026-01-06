# wp-mcp-for-shared-hosting

**A WordPress MCP (Model Context Protocol) server that can be self-hosted on any budget shared hosting that supports PHP + MySQL.**

This project provides a lightweight **remote MCP server** that allows LLMs (Claude, ChatGPT, local agents, etc.) to safely control WordPress marketing sites via the WordPress REST API.

It is designed specifically for **shared hosting environments** (DreamHost, Bluehost, Hostinger, cPanel hosts, etc.) where you cannot run long-lived processes or custom daemons.

---

## Supported capabilities

- Create WordPress pages
- Update existing pages
- Get a page by slug (`get_page`)
- Insert sections after headings (best-effort HTML insertion)

---

## Security defaults (important)

- Maintenance mode **defaults ON** in `config/config.example.php`
- HTTPS required (`require_https=true`)
- Signed requests required (`require_signed_requests=true`)
- Rate limiting per API key per minute (`rate_limit_per_minute`)

See `docs/CLIENTS.md` for signed request examples.

---

## Quick start

1. Upload repo and point web root to `public/`
2. Visit `/install.php` once (generates config + creates API key + signing secret)
3. Register WordPress site via `/site_helper.php` (optional; then delete/disable it)
4. Call `POST /mcp` with signed requests

---

## Repo layout

```
public/ (web root)
src/
config/
sql/
docs/
```
