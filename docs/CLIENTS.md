# Clients


## Signed curl requests (required by default)

### Compute signature (bash + openssl)

```bash
TS="$(date +%s)"
BODY='{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'
BODY_HASH="$(printf "%s" "$BODY" | openssl dgst -sha256 | awk '{print $2}')"
SIG="$(printf "%s
%s" "$TS" "$BODY_HASH" | openssl dgst -sha256 -hmac "YOUR_SIGNING_SECRET" | awk '{print $2}')"
```

### Call MCP

```bash
curl -sS   -H "Authorization: Bearer YOUR_MCP_API_KEY"   -H "X-MCP-Timestamp: $TS"   -H "X-MCP-Signature: $SIG"   -H "Content-Type: application/json"   -d "$BODY"   https://YOUR_MCP_HOST/mcp
```
