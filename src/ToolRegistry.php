<?php
declare(strict_types=1);

namespace WPSharedHostingMCP;

require_once __DIR__ . '/Tools/CreatePage.php';
require_once __DIR__ . '/Tools/UpdatePage.php';
require_once __DIR__ . '/Tools/InsertSection.php';
require_once __DIR__ . '/Tools/AddMenuItem.php';
require_once __DIR__ . '/Tools/GetPage.php';

final class ToolRegistry
{
    private array $handlers = [];

    public function __construct(private DB $db, private array $config)
    {
        $this->handlers = [
            'create_page' => new Tools\CreatePage($db, $config),
            'update_page' => new Tools\UpdatePage($db, $config),
            'insert_section' => new Tools\InsertSection($db, $config),
            'add_menu_item' => new Tools\AddMenuItem($db, $config),
        ];
    }

    public function listTools(array $principal): array
    {
        return [
            'tools' => [
                [
                    'name' => 'create_page',
                    'description' => 'Create a WordPress page',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'site_id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                            'content' => ['type' => 'string'],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'publish']],
                        ],
                        'required' => ['site_id', 'title'],
                    ],
                ],
                [
                    'name' => 'update_page',
                    'description' => 'Update a WordPress page',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'site_id' => ['type' => 'string'],
                            'page_id' => ['type' => 'integer'],
                            'slug' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'content' => ['type' => 'string'],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'publish']],
                        ],
                        'required' => ['site_id'],
                    ],
                ],
                [
                    'name' => 'insert_section',
                    'description' => 'Insert content after a heading (best-effort)',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'site_id' => ['type' => 'string'],
                            'page_id' => ['type' => 'integer'],
                            'anchor_heading' => ['type' => 'string'],
                            'content' => ['type' => 'string'],
                        ],
                        'required' => ['site_id', 'page_id', 'anchor_heading', 'content'],
                    ],
                ],
                [
                    'name' => 'get_page',
                    'description' => 'Get a WordPress page by slug',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'site_id' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                        ],
                        'required' => ['site_id', 'slug'],
                    ],
                ],
                [
                    'name' => 'add_menu_item',
                    'description' => 'Add a menu item (site-specific; may require WP-side endpoint)',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'site_id' => ['type' => 'string'],
                            'menu_location' => ['type' => 'string'],
                            'label' => ['type' => 'string'],
                            'url' => ['type' => 'string'],
                        ],
                        'required' => ['site_id', 'menu_location', 'label'],
                    ],
                ],
            ],
        ];
    }

    public function callTool(array $principal, string $name, array $args): array
    {
        if (!isset($this->handlers[$name])) throw new \InvalidArgumentException('Unknown tool: ' . $name);
        $handler = $this->handlers[$name];

        $required = $handler->requiredScopes();
        foreach ($required as $scope) {
            if (!in_array($scope, $principal['scopes'] ?? [], true)) {
                throw new \RuntimeException('Missing scope: ' . $scope);
            }
        }

        return $handler->handle($principal, $args);
    }

    public static function loadSite(DB $db, array $config, string $siteId): array
    {
        $row = $db->fetchOne('SELECT * FROM wp_sites WHERE site_id = :s LIMIT 1', [':s' => $siteId]);
        if ($row === null) throw new \RuntimeException('Unknown site_id');

        $secret = (string)($config['app_secret'] ?? '');
        if ($secret === '') throw new \RuntimeException('app_secret not configured');

        $enc = (string)($row['wp_app_password_enc'] ?? '');
        $appPass = Crypto::decrypt($enc, $secret);

        return [
            'site_id' => (string)$row['site_id'],
            'base_url' => (string)$row['base_url'],
            'wp_username' => (string)$row['wp_username'],
            'wp_app_password' => $appPass,
        ];
    }

    public static function log(DB $db, array $config, int $apiKeyId, ?string $siteId, string $tool, array $input, array $result, string $status): void
    {
        if (!(bool)($config['log_db'] ?? true)) return;

        $logSensitive = (bool)($config['log_sensitive_inputs'] ?? false);
        if (!$logSensitive) {
            foreach (['wp_app_password', 'wp_app_password_enc', 'app_password'] as $k) {
                if (isset($input[$k])) $input[$k] = '[redacted]';
            }
        }

        $db->execute(
            'INSERT INTO mcp_logs (api_key_id, site_id, tool_name, input_json, result_json, status, created_at)
             VALUES (:k, :s, :t, :i, :r, :st, NOW())',
            [
                ':k' => $apiKeyId,
                ':s' => $siteId,
                ':t' => $tool,
                ':i' => json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':r' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':st' => $status,
            ]
        );
    }
}
