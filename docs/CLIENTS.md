# Clients / Examples

This server speaks MCP over HTTP via `POST /mcp`.

## Environment variables (recommended)

- `MCP_HOST` – e.g. `https://mcp.example.com`
- `MCP_API_KEY` – bearer token from installer
- `MCP_SIGNING_SECRET` – signing secret from installer

## Signed curl request

### 1) Compute signature (bash + openssl)

```bash
TS="$(date +%s)"
BODY='{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'
BODY_HASH="$(printf "%s" "$BODY" | openssl dgst -sha256 | awk '{print $2}')"
SIG="$(printf "%s\n%s" "$TS" "$BODY_HASH" | openssl dgst -sha256 -hmac "$MCP_SIGNING_SECRET" | awk '{print $2}')"
```

### 2) Call MCP

```bash
curl -sS \
  -H "Authorization: Bearer $MCP_API_KEY" \
  -H "X-MCP-Timestamp: $TS" \
  -H "X-MCP-Signature: $SIG" \
  -H "Content-Type: application/json" \
  -d "$BODY" \
  "$MCP_HOST/mcp"
```

## Claude / ChatGPT usage pattern

- Always call `tools/list` first.
- Use `get_page` to check if a slug exists before `create_page`.
- Prefer creating pages as `draft` unless you intend to publish.

Last regenerated: 2026-01-06
