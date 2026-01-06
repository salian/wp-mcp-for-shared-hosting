<?php
declare(strict_types=1);

/**
 * WordPress site registration helper (one-time / optional).
 *
 * Purpose:
 * - Encrypt a WordPress Application Password using app_secret
 * - Insert (or update) a wp_sites row
 *
 * Security:
 * - Requires Authorization: Bearer <MCP_API_KEY>
 * - Can be disabled permanently by setting 'site_helper_disabled' => true in config/config.php
 *
 * Notes:
 * - WordPress Application Passwords are preferred over storing admin credentials.
 * - After registering sites, you should delete this file: public/site_helper.php
 */

require_once __DIR__ . '/../src/MCPServer.php'; // loads dependencies via MCPServer

use WPSharedHostingMCP\DB;
use WPSharedHostingMCP\Auth;
use WPSharedHostingMCP\Crypto;

$repoRoot = dirname(__DIR__);
$configPath = $repoRoot . '/config/config.php';
if (!file_exists($configPath)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Missing config/config.php. Run /install.php first or create config/config.php.";
  exit;
}

$config = require $configPath;
if (!is_array($config)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Invalid config/config.php";
  exit;
}

if (!empty($config['site_helper_disabled'])) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Site helper is disabled. Remove this file if you do not need it.";
  exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function bearerToken(): ?string {
  $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
  if (!is_string($h) || $h === '') return null;
  if (stripos($h, 'Bearer ') !== 0) return null;
  return trim(substr($h, 7));
}

function clientIp(): string {
  return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

$db = new DB($config['db'] ?? []);
$auth = new Auth($db, $config);

$principal = $auth->authenticate(bearerToken(), clientIp());
if ($principal === null) {
  http_response_code(401);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Unauthorized (missing/invalid MCP API key)";
  exit;
}

$appSecret = (string)($config['app_secret'] ?? '');
if ($appSecret === '') {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "app_secret not configured";
  exit;
}

// JSON mode (API)
$isJson = (($_SERVER['CONTENT_TYPE'] ?? '') !== '' && str_starts_with((string)$_SERVER['CONTENT_TYPE'], 'application/json'));
if ($isJson) {
  $raw = file_get_contents('php://input') ?: '';
  $req = json_decode($raw, true);
  if (!is_array($req)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
  }
  $siteId = trim((string)($req['site_id'] ?? ''));
  $baseUrl = trim((string)($req['base_url'] ?? ''));
  $wpUser = trim((string)($req['wp_username'] ?? ''));
  $wpAppPass = (string)($req['wp_app_password'] ?? '');

  try {
    $out = upsertSite($db, $appSecret, $siteId, $baseUrl, $wpUser, $wpAppPass);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'failed', 'message' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }
  exit;
}

// HTML form mode
$errors = [];
$success = false;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $siteId = trim((string)($_POST['site_id'] ?? ''));
  $baseUrl = trim((string)($_POST['base_url'] ?? ''));
  $wpUser = trim((string)($_POST['wp_username'] ?? ''));
  $wpAppPass = (string)($_POST['wp_app_password'] ?? '');

  try {
    $result = upsertSite($db, $appSecret, $siteId, $baseUrl, $wpUser, $wpAppPass);
    $success = true;
  } catch (Throwable $e) {
    $errors[] = $e->getMessage();
  }
}

function upsertSite(DB $db, string $appSecret, string $siteId, string $baseUrl, string $wpUser, string $wpAppPass): array {
  if ($siteId === '') throw new RuntimeException("site_id is required");
  if ($baseUrl === '') throw new RuntimeException("base_url is required");
  if (!preg_match('#^https?://#i', $baseUrl)) throw new RuntimeException("base_url must start with http:// or https://");
  if ($wpUser === '') throw new RuntimeException("wp_username is required");
  if ($wpAppPass === '') throw new RuntimeException("wp_app_password is required");

  $enc = Crypto::encrypt($wpAppPass, $appSecret);

  $existing = $db->fetchOne('SELECT id FROM wp_sites WHERE site_id = :s LIMIT 1', [':s' => $siteId]);
  if ($existing) {
    $db->execute(
      'UPDATE wp_sites SET base_url=:u, wp_username=:w, wp_app_password_enc=:p WHERE site_id=:s',
      [':u' => $baseUrl, ':w' => $wpUser, ':p' => $enc, ':s' => $siteId]
    );
    $mode = 'updated';
  } else {
    $db->execute(
      'INSERT INTO wp_sites (site_id, base_url, wp_username, wp_app_password_enc) VALUES (:s,:u,:w,:p)',
      [':s' => $siteId, ':u' => $baseUrl, ':w' => $wpUser, ':p' => $enc]
    );
    $mode = 'inserted';
  }

  return ['mode' => $mode, 'site_id' => $siteId, 'base_url' => $baseUrl];
}

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register WordPress site</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:900px;margin:40px auto;padding:0 16px;line-height:1.4}
    code,pre{background:#f5f5f5;padding:2px 6px;border-radius:6px}
    input{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px}
    label{display:block;margin-top:14px;font-weight:600}
    button{margin-top:18px;padding:10px 14px;border:0;border-radius:10px;background:#111;color:#fff;cursor:pointer}
    .box{border:1px solid #ddd;border-radius:12px;padding:16px;margin-top:18px}
    .err{color:#b00020}
    .ok{color:#0b6b2b}
  </style>
</head>
<body>
  <h1>Register WordPress site</h1>
  <p><strong>Auth required:</strong> send <code>Authorization: Bearer &lt;MCP_API_KEY&gt;</code> header.</p>

  <?php if ($errors): ?>
    <div class="box err">
      <strong>Errors</strong>
      <ul>
        <?php foreach ($errors as $e): ?><li><?php echo h($e); ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="box ok">
      <h2>Saved ✅</h2>
      <pre><?php echo h(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
      <p>Now delete this file: <code>public/site_helper.php</code> (recommended)</p>
    </div>
  <?php endif; ?>

  <form method="post" class="box">
    <label>site_id</label>
    <input name="site_id" value="<?php echo h($_POST['site_id'] ?? 'example-main'); ?>" required>

    <label>base_url</label>
    <input name="base_url" value="<?php echo h($_POST['base_url'] ?? 'https://example.com'); ?>" required>

    <label>wp_username</label>
    <input name="wp_username" value="<?php echo h($_POST['wp_username'] ?? 'wp_api_user'); ?>" required>

    <label>wp_app_password (plaintext)</label>
    <input name="wp_app_password" type="password" value="" required>

    <button type="submit">Save site</button>
  </form>

  <div class="box">
    <h2>JSON API mode</h2>
    <p>Send JSON with <code>Content-Type: application/json</code>:</p>
    <pre>{
  "site_id": "example-main",
  "base_url": "https://example.com",
  "wp_username": "wp_api_user",
  "wp_app_password": "xxxx xxxx xxxx xxxx xxxx xxxx"
}</pre>
  </div>
</body>
</html>
