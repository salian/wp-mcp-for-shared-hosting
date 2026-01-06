# WordPress MCP Server Specification

**Project:** wp-mcp-for-shared-hosting  
**Purpose:** MCP server that controls WordPress sites via REST APIs  
**Hosting:** Shared hosting (DreamHost-class)  
**Stack:** PHP + MySQL  
**Scope:** MCP server only (no WordPress plugin, no UI)

---

## 1. Design Goals

- HTTP-based MCP (no stdio, no long-lived processes)
- Compatible with shared hosting constraints
- LLM-agnostic
- Secure, auditable, minimal
- WordPress remains the CMS; MCP is the control plane

---

## 2. Transport

- **Protocol:** MCP over HTTP (request/response)
- **Endpoint:** `POST /mcp`
- Optional: `GET /mcp` for health/capabilities
- No SSE, no streaming

---

## 3. Authentication

### MCP Client → MCP Server

- Header: `Authorization: Bearer MCP_API_KEY`
- API keys stored hashed in MySQL
- Keys scoped (e.g. `pages.write`, `menus.write`)

### MCP Server → WordPress

- WordPress Application Passwords
- Per-site credentials stored encrypted

---

## 4. MCP Tools

### create_page

```json
{
  "site_id": "string",
  "title": "string",
  "slug": "string",
  "content": "string",
  "status": "draft | publish"
}
```

### update_page

```json
{
  "site_id": "string",
  "page_id": "integer",
  "slug": "string",
  "title": "string",
  "content": "string",
  "status": "draft | publish"
}
```

### insert_section

```json
{
  "site_id": "string",
  "page_id": "integer",
  "anchor_heading": "string",
  "content": "string"
}
```

### add_menu_item

```json
{
  "site_id": "string",
  "menu_location": "string",
  "label": "string",
  "url": "string"
}
```

---

## 5. Database Schema

### mcp_api_keys
- id
- key_hash
- name
- scopes (JSON)
- status
- created_at

### wp_sites
- id
- site_id
- base_url
- wp_username
- wp_app_password (encrypted)
- created_at

### mcp_logs
- id
- api_key_id
- site_id
- tool_name
- input_json
- result_json
- status
- created_at

## Signed requests (replay protection)

When `require_signed_requests=true`, each request must include:
- `X-MCP-Timestamp` (unix seconds)
- `X-MCP-Signature` = hex HMAC-SHA256 of `timestamp + "\n" + sha256(body)` using the per-key signing secret.


## Tool: get_page

Input:
```json
{"site_id":"string","slug":"string"}
```

Output:
```json
{"ok":true,"found":true,"page_id":123,"title":"...","status":"draft","link":"https://..."}
```
