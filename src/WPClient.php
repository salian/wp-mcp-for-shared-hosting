<?php
declare(strict_types=1);

namespace WPSharedHostingMCP;

final class WPClient
{
    public function __construct(
        private string $baseUrl,
        private string $username,
        private string $appPassword
    ) {}

    public function post(string $path, array $payload): array
    {
        return $this->request('POST', $path, $payload);
    }

    public function put(string $path, array $payload): array
    {
        return $this->request('POST', $path, $payload, ['X-HTTP-Method-Override: PUT']);
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path, null);
    }

    private function request(string $method, string $path, ?array $payload, array $extraHeaders = []): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $ch = curl_init($url);
        if ($ch === false) throw new \RuntimeException('curl_init failed');

        $headers = array_merge(['Accept: application/json'], $extraHeaders);

        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $basic = base64_encode($this->username . ':' . $this->appPassword);
        $headers[] = 'Authorization: Basic ' . $basic;

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) throw new \RuntimeException('WP request failed: ' . $err);

        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('WP non-JSON response (HTTP ' . $code . '): ' . substr($resp, 0, 300));
        }

        if ($code >= 400) {
            $msg = $decoded['message'] ?? ('HTTP ' . $code);
            throw new \RuntimeException('WP error: ' . $msg);
        }

        return $decoded;
    }
}
