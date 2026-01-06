# Deployment


## HTTPS enforcement

When `require_https=true` (default), the server rejects plain HTTP. If behind a proxy/CDN, ensure it sets `X-Forwarded-Proto: https`.
