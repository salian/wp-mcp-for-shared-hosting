# Security

This server is designed to be usable on shared hosting while still having strong default security controls.

## Defaults

- **Deny by default**: `maintenance_mode=true` in `config.example.php`
- **HTTPS-only**: `require_https=true` enforced in code
- **Signed requests**: `require_signed_requests=true` (timestamp + HMAC)
- **Rate limiting**: `rate_limit_per_minute=60` (per API key per minute)
- **Audit logs**: tool calls are recorded in `mcp_logs`

## Threat model (practical)

### Leaked bearer token
Mitigation: signed requests require a second secret (`signing secret`) per API key.

### Brute force / abuse
Mitigation: per-key rate limiting.

### Accidental exposure during setup
Mitigation: maintenance mode default ON; installer should be deleted after use.

## Hardening checklist

- Use a dedicated subdomain for the MCP server (separate from WordPress).
- Keep `maintenance_mode=true` until you're ready.
- Delete or disable:
  - `public/install.php` after install
  - `public/site_helper.php` after registering sites
- Rotate keys and signing secrets if suspected compromised.
- Use WordPress Application Passwords with minimal privileges.

## Signed request details

Headers:
- `X-MCP-Timestamp`: unix seconds
- `X-MCP-Signature`: hex hmac sha256 of `timestamp + "\n" + sha256(body)`

See `docs/CLIENTS.md`.

Last regenerated: 2026-01-06
