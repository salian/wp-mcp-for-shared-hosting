<?php
declare(strict_types=1);

namespace WPSharedHostingMCP;

final class RateLimiter
{
    public function __construct(private DB $db, private array $config) {}

    public function allow(int $apiKeyId): bool
    {
        $limit = (int)($this->config['rate_limit_per_minute'] ?? 60);
        if ($limit <= 0) return true;

        $windowStart = gmdate('Y-m-d H:i:00');

        $this->db->execute(
            'INSERT INTO mcp_rate_limits (api_key_id, window_start, count)
             VALUES (:k, :w, 1)
             ON DUPLICATE KEY UPDATE count = count + 1',
            [':k' => $apiKeyId, ':w' => $windowStart]
        );

        $row = $this->db->fetchOne(
            'SELECT count FROM mcp_rate_limits WHERE api_key_id = :k AND window_start = :w',
            [':k' => $apiKeyId, ':w' => $windowStart]
        );

        $count = (int)($row['count'] ?? 0);
        return $count <= $limit;
    }

    public function limit(): int
    {
        return (int)($this->config['rate_limit_per_minute'] ?? 60);
    }
}
