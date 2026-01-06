# wp-mcp-for-shared-hosting

**A WordPress MCP (Model Context Protocol) server that can be self-hosted on any budget shared hosting that supports PHP + MySQL.**

This project provides a lightweight **remote MCP server** that allows LLMs (Claude, ChatGPT, local agents, etc.) to safely control WordPress marketing sites via the WordPress REST API.

It is designed specifically for **shared hosting environments** (DreamHost, Bluehost, Hostinger, cPanel hosts, etc.) where you cannot run long-lived processes or custom daemons.

---

## Why this exists

Most AI–CMS integrations assume:
- Node.js or Python servers
- background workers
- WebSockets or streaming
- managed infrastructure

This project intentionally avoids all of that.

**Goals:**
- Works on cheap shared hosting
- Stateless HTTP requests only
- PHP + MySQL only
- LLM-agnostic
- WordPress remains the CMS

---

## What this is / is not

**This is:**
- A remote MCP server
- A control plane for WordPress
- A tool interface for LLMs

**This is NOT:**
- A WordPress plugin
- A CMS
- A UI dashboard
- An autonomous agent runtime

---

## High-level architecture

```
LLM Client (Claude / ChatGPT)
        ↓ MCP over HTTP
wp-mcp-for-shared-hosting
        ↓ WordPress REST API
WordPress Site
```

---

## Supported capabilities

- Create WordPress pages
- Update existing pages
- Insert sections after headings (best-effort, see notes)
- Add items to WordPress menus (site-specific; may require WP-side endpoint)

All actions are logged and scoped.

---

## Quick start (shared hosting)

1. Create a MySQL database and user.
2. Upload this repo to a folder (recommended: a subdomain) and point web root to `public/`.
3. Copy `config/config.example.php` to `config/config.php` and fill values.
4. Run SQL from `sql/schema.sql`.
5. Create an MCP API key record (see `sql/bootstrap.sql`).
6. Register a WordPress site in `wp_sites` with base URL and application password.
7. Call `POST /mcp` with `Authorization: Bearer <MCP_API_KEY>`.

---

## Endpoints

- `GET /mcp` – health + basic server info
- `POST /mcp` – MCP JSON requests

---

## Repository layout

```
/wp-mcp-for-shared-hosting/
├── public/
│   ├── index.php
│   └── .htaccess
├── src/
│   ├── MCPServer.php
│   ├── ToolRegistry.php
│   ├── Auth.php
│   ├── DB.php
│   ├── WPClient.php
│   ├── Crypto.php
│   └── Tools/
├── config/
│   ├── config.example.php
│   └── config.php          (ignored)
├── docs/
├── sql/
└── examples/
```

---

## Status

Skeleton implementation intended for contributors. Review SECURITY.md before deploying publicly.

## WordPress site registration helper

After installation, use `https://YOUR_MCP_HOST/site_helper.php` to register a WordPress site (encrypts the WP Application Password and upserts into `wp_sites`). It requires the same `Authorization: Bearer <MCP_API_KEY>` header. Delete the file afterwards or set `site_helper_disabled => true` in `config/config.php`.
