<?php
declare(strict_types=1);

namespace WPSharedHostingMCP\Tools;

use WPSharedHostingMCP\DB;
use WPSharedHostingMCP\ToolRegistry;
use WPSharedHostingMCP\WPClient;

final class UpdatePage
{
    public function __construct(private DB $db, private array $config) {}

    public function requiredScopes(): array { return ['pages.write']; }

    public function handle(array $principal, array $args): array
    {
        $siteId = (string)($args['site_id'] ?? '');
        if ($siteId === '') throw new \InvalidArgumentException('site_id is required');

        $pageId = isset($args['page_id']) ? (int)$args['page_id'] : 0;
        $slug = isset($args['slug']) ? (string)$args['slug'] : '';
        if ($pageId <= 0 && $slug === '') throw new \InvalidArgumentException('page_id or slug is required');

        $site = ToolRegistry::loadSite($this->db, $this->config, $siteId);
        $wp = new WPClient($site['base_url'], $site['wp_username'], $site['wp_app_password']);

        if ($pageId <= 0 && $slug !== '') {
            $found = $wp->get('/wp-json/wp/v2/pages?slug=' . rawurlencode($slug));
            if (!isset($found[0]['id'])) throw new \RuntimeException('Page not found for slug');
            $pageId = (int)$found[0]['id'];
        }

        $payload = [];
        if (isset($args['title'])) $payload['title'] = (string)$args['title'];
        if (isset($args['content'])) $payload['content'] = (string)$args['content'];
        if (isset($args['status'])) {
            $st = (string)$args['status'];
            if (!in_array($st, ['draft','publish'], true)) throw new \InvalidArgumentException('invalid status');
            $payload['status'] = $st;
        }
        if (empty($payload)) throw new \InvalidArgumentException('No fields to update');

        $res = $wp->put('/wp-json/wp/v2/pages/' . $pageId, $payload);

        $out = [
            'ok' => true,
            'page_id' => $res['id'] ?? $pageId,
            'link' => $res['link'] ?? null,
            'status' => $res['status'] ?? null,
        ];

        ToolRegistry::log($this->db, $this->config, (int)$principal['api_key_id'], $siteId, 'update_page', $args, $out, 'ok');
        return $out;
    }
}
