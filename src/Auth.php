<?php
declare(strict_types=1);

namespace WPSharedHostingMCP;

final class Auth
{
    public function __construct(private DB $db, private array $config) {}

    public function authenticate(?string $apiKey, string $clientIp): ?array
    {
        if ($apiKey === null || $apiKey === '') return null;

        $allow = $this->config['ip_allowlist'] ?? [];
        if (is_array($allow) && count($allow) > 0) {
            if (!$this->ipAllowed($clientIp, $allow)) return null;
        }

        $hash = Crypto::hashApiKey($apiKey);
        $row = $this->db->fetchOne(
            'SELECT id, name, scopes_json, status FROM mcp_api_keys WHERE key_hash = :h LIMIT 1',
            [':h' => $hash]
        );
        if ($row === null) return null;
        if (($row['status'] ?? '') !== 'active') return null;

        $scopes = [];
        $sj = $row['scopes_json'] ?? '[]';
        $decoded = is_string($sj) ? json_decode($sj, true) : null;
        if (is_array($decoded)) $scopes = $decoded;

        return [
            'api_key_id' => (int)$row['id'],
            'name' => (string)($row['name'] ?? 'api-key'),
            'scopes' => $scopes,
        ];
    }

    private function ipAllowed(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if (!is_string($cidr)) continue;
            if ($this->cidrMatch($ip, $cidr)) return true;
        }
        return false;
    }

    private function cidrMatch(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) return $ip === $cidr;
        [$subnet, $maskBits] = explode('/', $cidr, 2);
        $maskBits = (int)$maskBits;
        $ipLong = ip2long($ip);
        $subLong = ip2long($subnet);
        if ($ipLong === false || $subLong === false) return false;
        $mask = -1 << (32 - $maskBits);
        return (($ipLong & $mask) === ($subLong & $mask));
    }
}
