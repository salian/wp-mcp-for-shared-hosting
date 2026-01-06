# Architecture Overview

## Request lifecycle

1. LLM sends MCP request to `/mcp`
2. API key validated
3. Request parsed (handshake / list_tools / call_tool)
4. Tool handler invoked
5. WordPress REST API called
6. Response returned to LLM
7. Action logged to database

---

## Key principles

- Stateless requests
- Short execution time
- Explicit tool boundaries
- No implicit autonomy

---

## Why WordPress logic stays out of MCP

- WordPress internals change frequently
- Themes vary wildly
- Block insertion rules are site-specific

This MCP delegates WordPress-specific behavior to:
- REST endpoints
- Optional custom WP plugins (out of scope here)

---

## Scaling model

- Horizontal scaling via stateless HTTP
- Database as single source of truth
- Reverse proxy / CDN friendly
