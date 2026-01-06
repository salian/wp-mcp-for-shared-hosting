<?php
declare(strict_types=1);

namespace WPSharedHostingMCP\Tools;

use WPSharedHostingMCP\DB;
use WPSharedHostingMCP\ToolRegistry;

final class AddMenuItem
{
    public function __construct(private DB $db, private array $config) {}

    public function requiredScopes(): array { return ['menus.write']; }

    public function handle(array $principal, array $args): array
    {
        $siteId = (string)($args['site_id'] ?? '');
        $location = (string)($args['menu_location'] ?? '');
        $label = (string)($args['label'] ?? '');
        $url = isset($args['url']) ? (string)$args['url'] : '';

        if ($siteId === '' || $location === '' || $label === '') {
            throw new \InvalidArgumentException('site_id, menu_location, label are required');
        }

        $out = [
            'ok' => false,
            'error' => 'menu_not_supported',
            'message' => 'Menu editing typically requires a WP-side endpoint or a menus REST plugin. This skeleton does not implement menu writes yet.',
            'suggestion' => [
                'option_a' => 'Install a WP menus REST plugin and wire it here.',
                'option_b' => 'Add a minimal WP plugin endpoint (recommended) and call it here.',
                'menu_location' => $location,
                'label' => $label,
                'url' => $url,
            ],
        ];

        ToolRegistry::log($this->db, $this->config, (int)$principal['api_key_id'], $siteId, 'add_menu_item', $args, $out, 'error');
        return $out;
    }
}
