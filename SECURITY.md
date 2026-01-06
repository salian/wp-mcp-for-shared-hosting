# Security Model

## Threat model

This MCP can mutate WordPress content. Treat it as **privileged infrastructure**.

---

## API Keys

- Random, high-entropy tokens
- Stored hashed
- Revocable
- Scoped per capability

---

## WordPress credentials

- Use WordPress Application Passwords
- Assign a minimal-privilege WordPress user
- Rotate credentials regularly

---

## Logging & audit

- Every tool call is logged
- Inputs and results stored
- Logs should be considered sensitive

---

## Recommendations

- Put MCP server behind HTTPS only
- Restrict IPs where possible
- Never expose MCP endpoint publicly without auth
- Do not reuse WordPress admin credentials

---

**Security posture:** conservative by design
