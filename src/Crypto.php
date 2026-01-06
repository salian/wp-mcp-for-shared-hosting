<?php
declare(strict_types=1);

namespace WPSharedHostingMCP;

final class Crypto
{
    public static function encrypt(string $plaintext, string $secret): string
    {
        $key = hash('sha256', $secret, true);
        $iv = random_bytes(12);
        $cipher = 'aes-256-gcm';

        $tag = '';
        $ct = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) throw new \RuntimeException('Encryption failed');

        return base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(string $b64, string $secret): string
    {
        $raw = base64_decode($b64, true);
        if ($raw is false || strlen($raw) < 28) throw new \RuntimeException('Invalid ciphertext');

        $key = hash('sha256', $secret, true);
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct = substr($raw, 28);

        $cipher = 'aes-256-gcm';
        $pt = openssl_decrypt($ct, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) throw new \RuntimeException('Decryption failed');

        return $pt;
    }

    public static function hashApiKey(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }
}
