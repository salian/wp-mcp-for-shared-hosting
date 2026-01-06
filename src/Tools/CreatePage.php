<?php
declare(strict_types=1);

namespace WPSharedHostingMCP\Tools;

use WPSharedHostingMCP\DB;
use WPSharedHostingMCP\ToolRegistry;
use WPSharedHostingMCP\WPClient;

final class CreatePage
{
    public function __construct(private DB $db, private array $config) {}

    public function requiredScopes(): array { return ['pages.write']; }

    public function handle(array $principal, array $args): array
    {
        $siteId = (string)($args['site_id'] ?? '');
        $title = (string)($args['title'] ?? '');
        $slug = isset($args['slug']) ? (string)$args['slug'] : '';
        $content = isset($args['content']) ? (string)$args['content'] : '';
        $status = isset($args['status']) ? (string)$args['status'] : 'draft';

        if ($siteId === '' || $title === '') throw new \InvalidArgumentException('site_id and title are required');
        if (!in_array($status, ['draft','publish'], true)) throw new \InvalidArgumentException('invalid status');

        $site = ToolRegistry::loadSite($this->db, $this->config, $siteId);
        $wp = new WPClient($site['base_url'], $site['wp_username'], $site['wp_app_password']);

        $payload = ['title' => $title, 'status' => $status];
        if ($slug !== '') $payload['slug'] = $slug;
        if ($content !== '') $payload['content'] = $content;

        $res = $wp->post('/wp-json/wp/v2/pages', $payload);

        $out = [
            'ok' => true,
            'page_id' => $res['id'] ?? null,
            'link' => $res['link'] ?? null,
            'status' => $res['status'] ?? null,
        ];

        ToolRegistry::log($this->db, $this->config, (int)$principal['api_key_id'], $siteId, 'create_page', $args, $out, 'ok');
        return $out;
    }
}
