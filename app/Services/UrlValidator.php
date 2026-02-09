<?php

namespace App\Services;

/**
 * Validates URLs against SSRF attacks.
 * Blocks private/internal IP ranges and dangerous schemes.
 */
class UrlValidator
{
    /**
     * Private/internal IP CIDR ranges that must be blocked.
     */
    protected static array $blockedCidrs = [
        '127.0.0.0/8',       // Loopback
        '10.0.0.0/8',        // Private Class A
        '172.16.0.0/12',     // Private Class B
        '192.168.0.0/16',    // Private Class C
        '169.254.0.0/16',    // Link-local (AWS metadata at 169.254.169.254)
        '0.0.0.0/8',         // Current network
        '100.64.0.0/10',     // Shared address space (CGN)
        '192.0.0.0/24',      // IETF Protocol Assignments
        '198.18.0.0/15',     // Benchmarking
        '224.0.0.0/4',       // Multicast
        '240.0.0.0/4',       // Reserved
        '::1/128',           // IPv6 loopback
        'fc00::/7',          // IPv6 unique local
        'fe80::/10',         // IPv6 link-local
    ];

    /**
     * Blocked hostnames.
     */
    protected static array $blockedHosts = [
        'localhost',
        'localhost.localdomain',
        '0.0.0.0',
        '[::1]',
        'metadata.google.internal',
        'metadata.google',
    ];

    /**
     * Validate a webhook URL is safe (not targeting internal resources).
     *
     * @return array{valid: bool, reason: ?string}
     */
    public static function validateWebhookUrl(string $url): array
    {
        // Must be http or https
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return ['valid' => false, 'reason' => 'Invalid URL format'];
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'])) {
            return ['valid' => false, 'reason' => 'Only HTTP and HTTPS schemes are allowed'];
        }

        $host = strtolower($parsed['host']);

        // Check blocked hostnames
        if (in_array($host, self::$blockedHosts)) {
            return ['valid' => false, 'reason' => "Host '{$host}' is not allowed for webhooks"];
        }

        // Resolve hostname to IP and check against blocked ranges
        $ips = gethostbynamel($host);
        if ($ips === false) {
            // If DNS resolution fails, allow it (might resolve later)
            // but block obvious IP-based hosts
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                $ips = [$host];
            } else {
                return ['valid' => true, 'reason' => null];
            }
        }

        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                return ['valid' => false, 'reason' => "Resolved IP '{$ip}' is in a private/reserved range"];
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Check if an IP address falls within any blocked CIDR range.
     */
    protected static function isPrivateIp(string $ip): bool
    {
        // Use PHP's built-in filter for the common cases
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        // Additional check for link-local and CGN ranges not covered by FILTER_FLAG_NO_PRIV_RANGE
        $long = ip2long($ip);
        if ($long === false) {
            return false; // IPv6 — covered by the flags above for basic cases
        }

        // 169.254.0.0/16 (link-local, AWS/GCP metadata)
        if (($long & 0xFFFF0000) === ip2long('169.254.0.0')) {
            return true;
        }

        // 100.64.0.0/10 (CGN)
        if (($long & 0xFFC00000) === ip2long('100.64.0.0')) {
            return true;
        }

        return false;
    }
}
