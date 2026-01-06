-- MySQL / MariaDB schema for wp-mcp-for-shared-hosting

CREATE TABLE IF NOT EXISTS mcp_api_keys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_hash CHAR(64) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  scopes_json TEXT NOT NULL DEFAULT '[]',
  signing_secret_enc TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wp_sites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id VARCHAR(64) NOT NULL UNIQUE,
  base_url VARCHAR(255) NOT NULL,
  wp_username VARCHAR(255) NOT NULL,
  wp_app_password_enc TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mcp_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  api_key_id INT NOT NULL,
  site_id VARCHAR(64) NULL,
  tool_name VARCHAR(64) NOT NULL,
  input_json MEDIUMTEXT NOT NULL,
  result_json MEDIUMTEXT NOT NULL,
  status VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (api_key_id),
  INDEX (site_id),
  INDEX (tool_name),
  CONSTRAINT fk_mcp_logs_api_key FOREIGN KEY (api_key_id) REFERENCES mcp_api_keys(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mcp_rate_limits (
  api_key_id INT NOT NULL,
  window_start TIMESTAMP NOT NULL,
  count INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (api_key_id, window_start),
  CONSTRAINT fk_mcp_rl_api_key FOREIGN KEY (api_key_id) REFERENCES mcp_api_keys(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
