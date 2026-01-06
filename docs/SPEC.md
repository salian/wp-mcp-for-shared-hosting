# WordPress MCP Server Specification

## 1. Scope

This project provides an **HTTP MCP server** that can control WordPress sites (typically marketing sites) via the WordPress REST API.

- Shared-hosting friendly: **PHP + MySQL**
- Stateless requests: no daemons, no workers, no streaming
- LLM-agnostic: any MCP-capable client can call the tools
- WordPress remains the CMS; this server is the control plane

---

## 2. Transport

- `POST /mcp` – JSON-RPC style MCP request/response
- `GET /mcp` – health/capabilities (optional)

No SSE / streaming.

---

## 3. Authentication

### 3.1 MCP client → MCP server
- `Authorization: Bearer <MCP_API_KEY>`
- API keys stored hashed in MySQL, scoped via `scopes_json`

### 3.2 Signed requests (replay protection)
When enabled (`require_signed_requests=true`), every request must include:

- `X-MCP-Timestamp`: unix epoch seconds
- `X-MCP-Signature`: hex HMAC-SHA256 computed over:

```
msg = timestamp + "\n" + sha256(raw_body)
signature = hmac_sha256(msg, signing_secret)
```

Requests are rejected if timestamp is outside `signature_max_skew_seconds`.

### 3.3 MCP server → WordPress
- WordPress **Application Passwords**
- Per-site credentials stored encrypted in `wp_sites.wp_app_password_enc`

---

## 4. Tools

### create_page
```json
{"site_id":"string","title":"string","slug":"string","content":"string","status":"draft|publish"}
```

### update_page
```json
{"site_id":"string","page_id":123,"slug":"string","title":"string","content":"string","status":"draft|publish"}
```

### get_page
Input:
```json
{"site_id":"string","slug":"string"}
```
Output:
```json
{"ok":true,"found":true,"page_id":123,"title":"...","status":"draft","link":"https://..."}
```

### insert_section
```json
{"site_id":"string","page_id":123,"anchor_heading":"string","content":"string"}
```

### add_menu_item
```json
{"site_id":"string","menu_location":"string","label":"string","url":"string"}
```
> May require a WP-side endpoint/plugin depending on your setup.

---

## 5. Config flags (security)

- `maintenance_mode` (bool): when true returns 503 for all requests
- `require_https` (bool): when true rejects non-HTTPS requests (403)
- `require_signed_requests` (bool): when true enforces signature headers
- `signature_max_skew_seconds` (int): allowed timestamp skew
- `rate_limit_per_minute` (int): per API key per minute; 0 disables

Last regenerated: 2026-01-06
