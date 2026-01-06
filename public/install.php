<?php
declare(strict_types=1);

/**
 * One-time installer
 *
 * - Writes config/config.php
 * - Initializes DB schema (sql/schema.sql)
 * - Creates initial MCP API key + signing secret (shown once)
 *
 * Safety:
 * - If config/config.php exists and has 'installed' => true, this script disables itself.
 * - Attempts to self-delete after successful install (best-effort).
 */

$repoRoot = dirname(__DIR__);
$configPath = $repoRoot . '/config/config.php';
$schemaPath = $repoRoot . '/sql/schema.sql';
$cryptoPath = $repoRoot . '/src/Crypto.php';
if (file_exists($cryptoPath)) { require_once $cryptoPath; }

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (file_exists($configPath)) {
    $cfg = @include $configPath;
    if (is_array($cfg) && !empty($cfg['installed'])) {
        http_response_code(403);
        echo "<h1>Installer disabled</h1>";
        echo "<p>Installation has already been completed. Delete <code>public/install.php</code> if you want.</p>";
        exit;
    }
}

$errors = [];
$success = false;
$apiKeyPlain = null;
$signingSecretPlain = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim((string)($_POST['db_host'] ?? 'localhost'));
    $dbName = trim((string)($_POST['db_name'] ?? ''));
    $dbUser = trim((string)($_POST['db_user'] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $appSecret = trim((string)($_POST['app_secret'] ?? ''));

    if ($dbName === '' || $dbUser === '') $errors[] = "Database name and user are required.";
    if ($appSecret === '' || strlen($appSecret) < 24) $errors[] = "app_secret is required (min 24 chars).";

    if (!$errors) {
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            if (!file_exists($schemaPath)) throw new RuntimeException("schema.sql not found");
            $sql = file_get_contents($schemaPath);
            if ($sql === false) throw new RuntimeException("Failed reading schema.sql");

            foreach (explode(";", $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) continue;
                $pdo->exec($stmt);
            }

            $apiKeyPlain = bin2hex(random_bytes(24));
            $signingSecretPlain = bin2hex(random_bytes(24));
            $hash = hash('sha256', $apiKeyPlain);
            $scopes = json_encode(["pages.read","pages.write","menus.write"], JSON_UNESCAPED_SLASHES);

            $signingSecretEnc = null;
            if (class_exists("WPSharedHostingMCP\\Crypto")) {
                $signingSecretEnc = WPSharedHostingMCP\Crypto::encrypt($signingSecretPlain, $appSecret);
            } else {
                $signingSecretEnc = $signingSecretPlain;
            }

            $st = $pdo->prepare("INSERT INTO mcp_api_keys (key_hash, name, scopes_json, signing_secret_enc, status) VALUES (:h, :n, :s, :ss, 'active')");
            $st->execute([
                ':h' => $hash,
                ':n' => 'installer-created',
                ':s' => $scopes,
                ':ss' => $signingSecretEnc,
            ]);

            $php = "<?php\n";
            $php .= "return [\n";
            $php .= "  'installed' => true,\n";
            $php .= "  'db' => [\n";
            $php .= "    'dsn' => " . var_export($dsn, true) . ",\n";
            $php .= "    'user' => " . var_export($dbUser, true) . ",\n";
            $php .= "    'pass' => " . var_export($dbPass, true) . ",\n";
            $php .= "    'options' => [\n";
            $php .= "      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
            $php .= "      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
            $php .= "    ],\n";
            $php .= "  ],\n";
            $php .= "  'app_secret' => " . var_export($appSecret, true) . ",\n";
            $php .= "  'maintenance_mode' => false,\n";
            $php .= "  'require_https' => true,\n";
            $php .= "  'require_signed_requests' => true,\n";
            $php .= "  'signature_max_skew_seconds' => 300,\n";
            $php .= "  'rate_limit_per_minute' => 60,\n";
            $php .= "  'ip_allowlist' => [],\n";
            $php .= "  'log_db' => true,\n";
            $php .= "  'log_sensitive_inputs' => false,\n";
            $php .= "  'site_helper_disabled' => false,\n";
            $php .= "];\n";

            if (!is_dir($repoRoot . '/config')) mkdir($repoRoot . '/config', 0755, true);
            file_put_contents($configPath, $php);

            $success = true;

            // Best-effort self-delete
            @unlink(__FILE__);
        } catch (Throwable $e) {
            $errors[] = "Install failed: " . $e->getMessage();
        }
    }
}

function gen_secret() { return bin2hex(random_bytes(24)); }

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Installer</title>
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
  <h1>Install wp-mcp-for-shared-hosting</h1>

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
      <h2>Installed ✅</h2>
      <p><strong>Your MCP API key (save this now):</strong></p>
      <pre><?php echo h($apiKeyPlain); ?></pre>
      <p><strong>Your signing secret (save this now):</strong></p>
      <pre><?php echo h($signingSecretPlain ?? ''); ?></pre>
      <p>Signed requests are required. See <code>docs/CLIENTS.md</code> for signed curl examples.</p>
      <p>Delete any remaining installer file: <code>public/install.php</code></p>
    </div>
  <?php else: ?>
    <form method="post" class="box">
      <h2>Database</h2>
      <label>DB host</label>
      <input name="db_host" value="<?php echo h($_POST['db_host'] ?? 'localhost'); ?>">
      <label>DB name</label>
      <input name="db_name" value="<?php echo h($_POST['db_name'] ?? ''); ?>" required>
      <label>DB user</label>
      <input name="db_user" value="<?php echo h($_POST['db_user'] ?? ''); ?>" required>
      <label>DB password</label>
      <input name="db_pass" type="password" value="<?php echo h($_POST['db_pass'] ?? ''); ?>">

      <h2 style="margin-top:20px;">App secret</h2>
      <label>app_secret (min 24 chars)</label>
      <input name="app_secret" value="<?php echo h($_POST['app_secret'] ?? ''); ?>" placeholder="Click Generate below or paste your own">
      <p><button type="button" onclick="document.querySelector('input[name=app_secret]').value='<?php echo h(gen_secret()); ?>'">Generate secret</button></p>

      <button type="submit">Install</button>
    </form>
  <?php endif; ?>
</body>
</html>
