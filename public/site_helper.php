<?php
declare(strict_types=1);

/**
 * WordPress site registration helper (one-time / optional).
 *
 * - Encrypts a WordPress Application Password using app_secret
 * - Inserts/updates wp_sites row
 *
 * Requires: Authorization: Bearer <MCP_API_KEY>
 * Can be disabled: site_helper_disabled => true in config/config.php
 *
 * Recommended: delete this file after registering sites.
 */

require_once __DIR__ . '/../src/MCPServer.php';

use WPSharedHostingMCP\DB;
use WPSharedHostingMCP\Auth;
use WPSharedHostingMCP\Crypto;

$repoRoot = dirname(__DIR__);
$configPath = $repoRoot . '/config/config.php';
if (!file_exists($configPath)) { http_response_code(500); echo "Missing config/config.php"; exit; }

$config = require $configPath;
if (!is_array($config)) { http_response_code(500); echo "Invalid config/config.php"; exit; }
if (!empty($config['site_helper_disabled'])) { http_response_code(403); echo "Site helper is disabled."; exit; }

function bearerToken(): ?string {
  $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
  if (!is_string($h) || $h === '') return null;
  if (stripos($h, 'Bearer ') !== 0) return null;
  return trim(substr($h, 7));
}
function clientIp(): string { return (string)($_SERVER['REMOTE_ADDR'] ?? ''); }

$db = new DB($config['db'] ?? []);
$auth = new Auth($db, $config);
$principal = $auth->authenticate(bearerToken(), clientIp());
if ($principal === null) { http_response_code(401); echo "Unauthorized"; exit; }

$appSecret = (string)($config['app_secret'] ?? '');
if ($appSecret === '') { http_response_code(500); echo "app_secret missing"; exit; }

$raw = file_get_contents('php://input') ?: '';
$req = json_decode($raw, true);
if (!is_array($req)) { http_response_code(400); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'invalid_json']); exit; }

$siteId = trim((string)($req['site_id'] ?? ''));
$baseUrl = trim((string)($req['base_url'] ?? ''));
$wpUser = trim((string)($req['wp_username'] ?? ''));
$wpAppPass = (string)($req['wp_app_password'] ?? '');

if ($siteId === '' || $baseUrl === '' || $wpUser === '' || $wpAppPass === '') {
  http_response_code(400); header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit;
}

$enc = Crypto::encrypt($wpAppPass, $appSecret);

$existing = $db->fetchOne('SELECT id FROM wp_sites WHERE site_id = :s LIMIT 1', [':s'=>$siteId]);
if ($existing) {
  $db->execute('UPDATE wp_sites SET base_url=:u, wp_username=:w, wp_app_password_enc=:p WHERE site_id=:s',
    [':u'=>$baseUrl,':w'=>$wpUser,':p'=>$enc,':s'=>$siteId]
  );
  $mode='updated';
} else {
  $db->execute('INSERT INTO wp_sites (site_id, base_url, wp_username, wp_app_password_enc) VALUES (:s,:u,:w,:p)',
    [':s'=>$siteId,':u'=>$baseUrl,':w'=>$wpUser,':p'=>$enc]
  );
  $mode='inserted';
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'mode'=>$mode,'site_id'=>$siteId,'base_url'=>$baseUrl], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
