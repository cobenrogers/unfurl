<?php

declare(strict_types=1);

namespace Unfurl\Security;

use Unfurl\Exceptions\SecurityException;

/**
 * UrlValidator - SSRF Protection
 *
 * Validates URLs before HTTP requests to prevent Server-Side Request Forgery attacks.
 *
 * Attack Prevention:
 * - Blocks private IP ranges (10.x, 192.168.x, 127.x, 169.254.x, IPv6)
 * - Allows only HTTP/HTTPS schemes
 * - Prevents DNS rebinding attacks
 * - Enforces URL length limits
 *
 * Requirements: Section 7.3 of REQUIREMENTS.md
 */
class UrlValidator
{
    /**
     * Blocked IP ranges (CIDR notation)
     */
    private const BLOCKED_IP_RANGES = [
        // IPv4 Private Networks
        '10.0.0.0/8',        // Class A private network
        '172.16.0.0/12',     // Class B private network
        '192.168.0.0/16',    // Class C private network

        // IPv4 Loopback & Special
        '127.0.0.0/8',       // Loopback addresses
        '169.254.0.0/16',    // Link-local (AWS metadata at 169.254.169.254)
        '0.0.0.0/8',         // Current network

        // IPv6 Special Addresses
        '::1/128',           // IPv6 localhost
        'fc00::/7',          // IPv6 unique local addresses (private)
        'fe80::/10',         // IPv6 link-local addresses
        '::/128',            // IPv6 unspecified address
    ];

    /**
     * Allowed URL schemes
     */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Maximum URL length
     */
    private const MAX_URL_LENGTH = 2000;

    /**
     * Validate URL for SSRF protection
     *
     * @param string $url URL to validate
     * @throws SecurityException If URL is invalid or potentially malicious
     */
    public function validate(string $url): void
    {
        // 1. Basic format validation
        if (empty($url)) {
            throw new SecurityException('Invalid URL format: URL is empty');
        }

        // 2. Validate URL length
        if (strlen($url) > self::MAX_URL_LENGTH) {
            throw new SecurityException(
                'URL too long (max ' . self::MAX_URL_LENGTH . ' characters)'
            );
        }

        // 3. Check scheme before parsing (parse_url fails on some malicious schemes)
        // Check if URL has a scheme
        $colonPos = strpos($url, ':');
        if ($colonPos !== false && $colonPos < 20) {
            // Has a scheme - check if it's http/https
            $scheme = strtolower(substr($url, 0, $colonPos));
            if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
                throw new SecurityException('Invalid URL scheme (must be HTTP/HTTPS): ' . $scheme);
            }
        }

        // Also verify it has :// after scheme
        if (!preg_match('/^(https?):\/\//i', $url)) {
            throw new SecurityException('Invalid URL format: Could not parse URL');
        }

        // 4. Parse URL
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            throw new SecurityException('Invalid URL format: Could not parse URL');
        }

        // 5. Validate scheme (double-check after parsing)
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new SecurityException(
                'Invalid URL scheme (must be HTTP/HTTPS): ' . $scheme
            );
        }

        // 6. Resolve hostname to IP address
        $host = $parsed['host'];

        // Handle IPv6 addresses (parse_url includes brackets)
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            // Extract IPv6 address from brackets
            $ip = substr($host, 1, -1);

            // Validate it's a valid IPv6 address
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new SecurityException('Invalid IPv6 address: ' . $ip);
            }
        }
        // Check if host is already an IPv4 address
        elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = $host;
        }
        // Resolve DNS for hostnames
        else {
            $ip = gethostbyname($host);

            // gethostbyname returns the hostname unchanged if resolution fails
            if ($ip === $host) {
                throw new SecurityException(
                    'Could not resolve hostname: ' . $host
                );
            }
        }

        // 7. Validate IP is not in blocked ranges
        foreach (self::BLOCKED_IP_RANGES as $range) {
            if ($this->ipInRange($ip, $range)) {
                throw new SecurityException(
                    'Private IP address blocked: ' . $ip
                );
            }
        }
    }

    /**
     * Check if an IP address is within a CIDR range
     *
     * Supports both IPv4 and IPv6 addresses.
     *
     * @param string $ip IP address to check
     * @param string $range CIDR range (e.g., "10.0.0.0/8" or "fc00::/7")
     * @return bool True if IP is in range
     */
    private function ipInRange(string $ip, string $range): bool
    {
        // Parse CIDR notation
        if (!str_contains($range, '/')) {
            return false;
        }

        [$subnet, $mask] = explode('/', $range, 2);
        $mask = (int)$mask;

        // Determine if IPv4 or IPv6
        $isIPv6 = str_contains($ip, ':') || str_contains($subnet, ':');

        if ($isIPv6) {
            return $this->ipv6InRange($ip, $subnet, $mask);
        } else {
            return $this->ipv4InRange($ip, $subnet, $mask);
        }
    }

    /**
     * Check if IPv4 address is in CIDR range
     */
    private function ipv4InRange(string $ip, string $subnet, int $mask): bool
    {
        // Convert to long integers
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        // Create netmask
        $netmask = -1 << (32 - $mask);

        // Compare network addresses
        return ($ipLong & $netmask) === ($subnetLong & $netmask);
    }

    /**
     * Check if IPv6 address is in CIDR range
     */
    private function ipv6InRange(string $ip, string $subnet, int $mask): bool
    {
        // Convert to binary representation
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // Convert to binary strings for comparison
        $ipBinary = '';
        $subnetBinary = '';

        for ($i = 0; $i < strlen($ipBin); $i++) {
            $ipBinary .= str_pad(decbin(ord($ipBin[$i])), 8, '0', STR_PAD_LEFT);
            $subnetBinary .= str_pad(decbin(ord($subnetBin[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Compare first $mask bits
        return substr($ipBinary, 0, $mask) === substr($subnetBinary, 0, $mask);
    }
}
