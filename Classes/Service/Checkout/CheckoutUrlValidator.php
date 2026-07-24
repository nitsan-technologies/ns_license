<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service\Checkout;

/**
 * Allowlist for Pabbly / t3planet.shop checkout URLs loaded in the backend iframe.
 *
 * Keep in sync with Configuration/ContentSecurityPolicies.php and get-license.js.
 */
final class CheckoutUrlValidator
{
    /** @var list<string> */
    private const ALLOWED_HOSTS = [
        'payments.pabbly.com',
        'pabbly.com',
        'pabbly.t3planet.de',
        't3planet.shop',
        'www.t3planet.shop',
    ];

    /** @var list<string> */
    private const ALLOWED_HOST_SUFFIXES = [
        '.t3planet.de',
        '.t3planet.shop',
        '.t3planet.com',
        '.pabbly.com',
    ];

    public function isAllowed(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        if (in_array($host, self::ALLOWED_HOSTS, true)) {
            return true;
        }

        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix) && $host !== ltrim($suffix, '.')) {
                return true;
            }
        }

        return false;
    }
}
