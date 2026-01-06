# Example MCP calls

## List tools

```bash
curl -sS \
  -H "Authorization: Bearer YOUR_MCP_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' \
  https://YOUR_MCP_HOST/mcp
```

## Create a page

```bash
curl -sS \
  -H "Authorization: Bearer YOUR_MCP_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc":"2.0",
    "id":2,
    "method":"tools/call",
    "params":{
      "name":"create_page",
      "arguments":{
        "site_id":"example-main",
        "title":"Pricing",
        "slug":"pricing",
        "content":"<h2>Simple pricing</h2><p>Start free.</p>",
        "status":"draft"
      }
    }
  }' \
  https://YOUR_MCP_HOST/mcp
```
