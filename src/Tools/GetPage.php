<?php
declare(strict_types=1);

namespace WPSharedHostingMCP\Tools;

use WPSharedHostingMCP\DB;
use WPSharedHostingMCP\ToolRegistry;
use WPSharedHostingMCP\WPClient;

final class GetPage
{
    public function __construct(private DB $db, private array $config) {}

    public function requiredScopes(): array { return ['pages.read']; }

    public function handle(array $principal, array $args): array
    {
        $siteId = (string)($args['site_id'] ?? '');
        $slug = (string)($args['slug'] ?? '');
        if ($siteId === '' || $slug === '') throw new \InvalidArgumentException('site_id and slug are required');

        $site = ToolRegistry::loadSite($this->db, $this->config, $siteId);
        $wp = new WPClient($site['base_url'], $site['wp_username'], $site['wp_app_password']);

        $found = $wp->get('/wp-json/wp/v2/pages?slug=' . rawurlencode($slug));
        if (!is_array($found) || !isset($found[0])) {
            $out = ['ok' => true, 'found' => false];
            ToolRegistry::log($this->db, $this->config, (int)$principal['api_key_id'], $siteId, 'get_page', $args, $out, 'ok');
            return $out;
        }
        $p = $found[0];

        $out = [
            'ok' => true,
            'found' => true,
            'page_id' => $p['id'] ?? null,
            'title' => $p['title']['rendered'] ?? null,
            'status' => $p['status'] ?? null,
            'link' => $p['link'] ?? null,
        ];

        ToolRegistry::log($this->db, $this->config, (int)$principal['api_key_id'], $siteId, 'get_page', $args, $out, 'ok');
        return $out;
    }
}
