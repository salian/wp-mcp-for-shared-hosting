<?php
declare(strict_types=1);

namespace WPSharedHostingMCP;

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/ToolRegistry.php';
require_once __DIR__ . '/WPClient.php';
require_once __DIR__ . '/Crypto.php';

final class MCPServer
{
    private array $config;
    private DB $db;
    private Auth $auth;
    private ToolRegistry $tools;

    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->db = new DB($this->config['db']);
        $this->auth = new Auth($this->db, $this->config);
        $this->tools = new ToolRegistry($this->db, $this->config);
    }

    public function handle(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

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

        $raw = file_get_contents('php://input') ?: '';
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
}
