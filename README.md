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
- Insert sections after headings
- Add items to WordPress menus

All actions are logged and scoped.

---

## Hosting requirements

- PHP 8.0+
- MySQL / MariaDB
- HTTPS
- Ability to create subdomains (recommended)

---

## Repository layout

```
/wp-mcp-for-shared-hosting/
├── public/
│   └── index.php
├── src/
├── config/
├── logs/
└── docs/
```

---

## Status

Early but functional. Designed to be extended conservatively.

