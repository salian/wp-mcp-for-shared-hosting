# wp-mcp-for-shared-hosting

**A WordPress MCP (Model Context Protocol) server that can be self-hosted on any budget shared hosting that supports PHP + MySQL.**

This repository contains a lightweight **remote MCP server** that lets LLMs (Claude, ChatGPT, local agents, etc.) control WordPress marketing sites via the WordPress REST API.

Designed for **shared hosting** (DreamHost, GoDaddy, Bluehost, Hostinger, cPanel hosts) where you cannot run long-lived processes.

---

## What you get

- A stateless **HTTP** MCP endpoint: `POST /mcp`
- A small set of **tools** that operate on WordPress pages
- MySQL-backed logs and rate limiting
- Security controls that work on shared hosting:
  - HTTPS-only enforcement
  - Maintenance mode (deny-by-default)
  - Per-key per-minute DB rate limiting
  - Signed requests (timestamp + HMAC) to reduce risk from leaked bearer tokens

---

## Quick start

1. Upload this repo to your host (recommended: a subdomain).
2. Point the web root to `public/`.
3. Visit `https://YOUR_MCP_HOST/install.php` **once** to:
   - create `config/config.php`
   - initialize DB schema
   - create your first MCP API key + signing secret (shown once)
4. (Optional) register a WordPress site using `https://YOUR_MCP_HOST/site_helper.php` then disable/delete that helper.
5. Ensure `maintenance_mode` is **false** in `config/config.php`.
6. Make signed MCP calls. See `docs/CLIENTS.md`.

---

## Repository layout

```
public/        # web root (index.php, install.php, site_helper.php)
src/           # server code + tools
config/        # config.example.php, generated config.php
sql/           # schema.sql
docs/          # documentation
```

---

## Documentation

- `docs/SPEC.md` – protocol, endpoints, tools, request signing
- `docs/ARCHITECTURE.md` – components and request lifecycle
- `docs/SECURITY.md` – threat model and hardening checklist
- `docs/DEPLOYMENT.md` – host-specific deployment notes
- `docs/CLIENTS.md` – curl + Claude + ChatGPT examples

---

Last regenerated: 2026-01-06
