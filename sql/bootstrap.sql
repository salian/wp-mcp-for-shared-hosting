-- Bootstrap examples (do NOT use in production as-is)

-- 1) Create an MCP API key
-- Generate a random token and store ONLY the sha256 hash.
-- Example: use `php -r "echo bin2hex(random_bytes(24));"` to generate a token

-- INSERT INTO mcp_api_keys (key_hash, name, scopes_json, status)
-- VALUES (SHA2('PASTE_YOUR_TOKEN_HERE', 256), 'local-dev', '["pages.write","menus.write"]', 'active');

-- 2) Register a WordPress site
-- Store the application password encrypted with app_secret using the included Crypto helper.
-- INSERT INTO wp_sites (site_id, base_url, wp_username, wp_app_password_enc)
-- VALUES ('example-main', 'https://example.com', 'wp_api_user', 'ENCRYPTED_VALUE_HERE');


-- NOTE: Signed requests require a signing secret stored in mcp_api_keys.signing_secret_enc.
