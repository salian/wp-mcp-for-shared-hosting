<?php
declare(strict_types=1);

namespace WPSharedHostingMCP;

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/ToolRegistry.php';
require_once __DIR__ . '/WPClient.php';
require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/RateLimiter.php';

final class MCPServer
{
    private array $config;
    private DB $db;
    private Auth $auth;
    private ToolRegistry $tools;
    private RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->db = new DB($this->config['db']);
        $this->auth = new Auth($this->db, $this->config);
        $this->tools = new ToolRegistry($this->db, $this->config);
        $this->rateLimiter = new RateLimiter($this->db, $this->config);
    }

    public function handle(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ((bool)($this->config['maintenance_mode'] ?? false)) {
            $this->json(['ok' => false, 'error' => 'maintenance_mode'], 503);
            return;
        }

        if ((bool)($this->config['require_https'] ?? true) && !$this->isHttpsRequest()) {
            $this->json(['ok' => false, 'error' => 'https_required'], 403);
            return;
        }

        if ($method === 'GET' && ($path === '/mcp' || $path === '/')) {
            $this->json([
                'ok' => true,
                'name' => 'wp-mcp-for-shared-hosting',
                'time' => gmdate('c'),
            ]);
            return;
        }

        if ($method !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Method not allowed'], 405);
            return;
        }

        if (!($path === '/mcp' || $path === '/' || str_ends_with($path, '/mcp'))) {
            $this->json(['ok' => false, 'error' => 'Not found'], 404);
            return;
        }

        $apiKey = $this->getBearerToken();
        $principal = $this->auth->authenticate($apiKey, $this->clientIp());
        if ($principal === null) {
            $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        // Rate limiting (per API key per minute)
        if (!$this->rateLimiter->allow((int)$principal['api_key_id'])) {
            $this->json(['ok' => false, 'error' => 'rate_limited', 'limit_per_minute' => $this->rateLimiter->limit()], 429);
            return;
        }

        $raw = file_get_contents('php://input') ?: '';

        // Replay protection (timestamp + HMAC signature)
        if ((bool)($this->config['require_signed_requests'] ?? true)) {
            if (!$this->verifySignature($principal, $raw)) {
                $this->json(['ok' => false, 'error' => 'invalid_signature'], 401);
                return;
            }
        }
        $req = json_decode($raw, true);
        if (!is_array($req)) {
            $this->json(['ok' => false, 'error' => 'Invalid JSON'], 400);
            return;
        }

        $methodName = (string)($req['method'] ?? '');
        $id = $req['id'] ?? null;
        $params = is_array($req['params'] ?? null) ? $req['params'] : [];

        try {
            if ($methodName === 'tools/list') {
                $result = $this->tools->listTools($principal);
                $this->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
                return;
            }

            if ($methodName === 'tools/call') {
                $toolName = (string)($params['name'] ?? '');
                $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
                $result = $this->tools->callTool($principal, $toolName, $args);
                $this->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
                return;
            }

            if ($methodName === 'initialize') {
                $result = [
                    'serverInfo' => [
                        'name' => 'wp-mcp-for-shared-hosting',
                        'version' => '0.1.0',
                    ],
                    'capabilities' => [
                        'tools' => true,
                    ],
                ];
                $this->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
                return;
            }

            $this->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => [
                'code' => -32601,
                'message' => 'Method not found',
            ]], 404);
        } catch (\Throwable $e) {
            $this->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => [
                'code' => -32000,
                'message' => 'Server error',
                'data' => ['detail' => $e->getMessage()],
            ]], 500);
        }
    }

    private function loadConfig(): array
    {
        $path = __DIR__ . '/../config/config.php';
        if (!file_exists($path)) {
            $path = __DIR__ . '/../config/config.example.php';
        }
        $cfg = require $path;
        if (!is_array($cfg)) {
            throw new \RuntimeException('Config file must return an array');
        }
        return $cfg;
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function getBearerToken(): ?string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($h) || $h === '') return null;
        if (stripos($h, 'Bearer ') !== 0) return null;
        return trim(substr($h, 7));
    }

    private function clientIp(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private function isHttpsRequest(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') return true;

        $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (is_string($xfp) && strtolower($xfp) === 'https') return true;

        $port = (string)($_SERVER['SERVER_PORT'] ?? '');
        if ($port === '443') return true;

        return false;
    }

    private function verifySignature(array $principal, string $rawBody): bool
    {
        $ts = $_SERVER['HTTP_X_MCP_TIMESTAMP'] ?? '';
        $sig = $_SERVER['HTTP_X_MCP_SIGNATURE'] ?? '';

        if (!is_string($ts) || $ts === '' || !is_string($sig) || $sig === '') return false;
        if (!ctype_digit($ts)) return false;

        $t = (int)$ts;
        $skew = (int)($this->config['signature_max_skew_seconds'] ?? 300);
        if ($skew <= 0) $skew = 300;

        $now = time();
        if (abs($now - $t) > $skew) return false;

        $secret = $principal['signing_secret'] ?? null;
        if (!is_string($secret) || $secret === '') return false;

        $bodyHash = hash('sha256', $rawBody);
        $msg = $ts . "\n" . $bodyHash;
        $expected = hash_hmac('sha256', $msg, $secret);

        return hash_equals($expected, strtolower(trim($sig)));
    }
}
