<?php
declare(strict_types=1);

namespace WPSharedHostingMCP\Tools;

use WPSharedHostingMCP\DB;
use WPSharedHostingMCP\ToolRegistry;
use WPSharedHostingMCP\WPClient;

final class InsertSection
{
    public function __construct(private DB $db, private array $config) {}

    public function requiredScopes(): array { return ['pages.write']; }

    public function handle(array $principal, array $args): array
    {
        $siteId = (string)($args['site_id'] ?? '');
        $pageId = (int)($args['page_id'] ?? 0);
        $anchor = (string)($args['anchor_heading'] ?? '');
        $contentToInsert = (string)($args['content'] ?? '');

        if ($siteId === '' || $pageId <= 0 || $anchor === '' || $contentToInsert === '') {
            throw new \InvalidArgumentException('site_id, page_id, anchor_heading, content are required');
        }

        $site = ToolRegistry::loadSite($this->db, $this->config, $siteId);
        $wp = new WPClient($site['base_url'], $site['wp_username'], $site['wp_app_password']);

        $page = $wp->get('/wp-json/wp/v2/pages/' . $pageId);
        $current = (string)($page['content']['raw'] ?? $page['content']['rendered'] ?? '');

        $updated = $this->insertAfterHeading($current, $anchor, $contentToInsert);
        if ($updated === $current) throw new \RuntimeException('Anchor heading not found (or insertion not possible)');

        $res = $wp->put('/wp-json/wp/v2/pages/' . $pageId, ['content' => $updated]);

        $out = [
            'ok' => true,
            'page_id' => $res['id'] ?? $pageId,
            'link' => $res['link'] ?? null,
            'note' => 'Best-effort HTML insertion; for robust Gutenberg edits, use a WP-side endpoint.',
        ];

        ToolRegistry::log($this->db, $this->config, (int)$principal['api_key_id'], $siteId, 'insert_section', $args, $out, 'ok');
        return $out;
    }

    private function insertAfterHeading(string $html, string $headingText, string $insertHtml): string
    {
        $pattern = '/<(h[1-6])\b[^>]*>\s*' . preg_quote($headingText, '/') . '\s*<\/\1>/i';
        if (!preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) return $html;

        $start = $m[0][1];
        $len = strlen($m[0][0]);

        $before = substr($html, 0, $start + $len);
        $after = substr($html, $start + $len);

        return $before . "\n" . $insertHtml . "\n" . $after;
    }
}
