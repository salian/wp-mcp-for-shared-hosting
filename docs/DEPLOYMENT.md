# Deployment (Shared Hosting)

This project is built for shared hosting. The most reliable deployment pattern is:

1. Create a **subdomain** (e.g. `mcp.example.com`)
2. Set that subdomain's **document root** to the repo's `public/` directory
3. Keep the rest of the repo outside the web root

---

## Common requirements

- PHP 8.0+
- PDO MySQL enabled
- HTTPS enabled on the MCP subdomain
- Ability to create a MySQL database + user

---

## Recommended directory layout

Upload the repo so it looks like:

```
/home/USER/mcp.example.com/
  ├── public/   <-- document root
  ├── src/
  ├── config/
  ├── sql/
  └── docs/
```

---

## Installer and helper endpoints

- `https://YOUR_MCP_HOST/install.php` (run once; delete after)
- `https://YOUR_MCP_HOST/site_helper.php` (optional; delete/disable after)

---

## HTTPS enforcement

This server rejects plain HTTP when `require_https=true` (default).

If behind a reverse proxy/CDN, ensure it sets:

- `X-Forwarded-Proto: https`

---

# Host-specific notes

## DreamHost (shared hosting)

**Recommended:** use a subdomain.

1. Create a subdomain in DreamHost panel.
2. Point it to the directory containing the repo.
3. Ensure the web root is `public/` (DreamHost “Web directory” can be `/public`).
4. Enable HTTPS via DreamHost panel.
5. Run `install.php` and then remove it.

---

## GoDaddy (cPanel-based shared hosting)

GoDaddy shared hosting typically uses **cPanel** with restrictions around document roots.

### Recommended setup (subdomain)

1. GoDaddy → **My Products → Web Hosting → cPanel Admin**
2. cPanel → **Domains → Subdomains**
3. Create a subdomain (e.g. `mcp.yourdomain.com`)
4. Set the **Document Root** to the repo's `public/` folder
5. Upload the repo so:

```
/home/USERNAME/mcp.yourdomain.com/
  ├── public/   <-- document root
  ├── src/
  ├── config/
  ├── sql/
  └── docs/
```

### HTTPS notes

- Enable AutoSSL / Managed SSL in GoDaddy
- GoDaddy usually sets `X-Forwarded-Proto: https` automatically
- If HTTPS isn't active yet, requests will be rejected (`https_required`)

### Common GoDaddy pitfalls

- PHP version too old → set **PHP 8.0+** (MultiPHP Manager)
- Missing PDO MySQL → enable required extensions
- File permissions: directories `755`, files `644`

---

## Bluehost / HostGator / Generic cPanel hosts

1. Create a subdomain and point it to a folder
2. Upload repo contents to that folder
3. Set document root to `public/` if possible
4. Enable SSL (AutoSSL / Let’s Encrypt if supported)
5. Run installer; delete installer

---

## Hostinger

- Use a subdomain
- Point doc root to `public/`
- Enable SSL from hPanel
- Run installer; delete installer

---

Last regenerated: 2026-01-06
