<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service\Checkout;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;

/**
 * Builds absolute backend return URLs for post-checkout landing.
 * Strips TYPO3 route tokens so the landing page relies on the active BE session.
 */
final class CheckoutReturnUrlBuilder
{
    public const MODULE_ROUTE = 'nitsan_nslicensemodule';

    public function __construct(
        private readonly UriBuilder $uriBuilder,
    ) {}

    /**
     * @param array<string, mixed> $parameters
     */
    public function fromModule(array $parameters = []): string
    {
        return $this->stripBackendRouteToken(
            (string) $this->uriBuilder->buildUriFromRoute(
                self::MODULE_ROUTE,
                $parameters,
                UriBuilder::ABSOLUTE_URL,
            ),
        );
    }

    public function normalize(string $returnUrl): string
    {
        $returnUrl = trim($returnUrl);
        if ($returnUrl === '') {
            return '';
        }

        if ($this->isAbsoluteUrl($returnUrl)) {
            return $this->stripBackendRouteToken($returnUrl);
        }

        $path = str_starts_with($returnUrl, '/') ? $returnUrl : '/' . $returnUrl;

        return $this->stripBackendRouteToken($this->absolutePath($path));
    }

    private function absolutePath(string $path): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof ServerRequestInterface) {
            $uri = $request->getUri();
            $host = $uri->getHost();
            if ($host !== '') {
                $scheme = $uri->getScheme() !== '' ? $uri->getScheme() : 'https';

                return $this->composeUrl($scheme, $host, $uri->getPort(), $path, '');
            }
        }

        return $path;
    }

    private function stripBackendRouteToken(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $queryString = (string) ($parts['query'] ?? '');
        if ($queryString === '') {
            return $url;
        }

        parse_str($queryString, $query);
        if (!array_key_exists('token', $query)) {
            return $url;
        }

        unset($query['token']);
        $parts['query'] = $query === [] ? null : http_build_query($query);

        return $this->composeUrlFromParts($parts);
    }

    private function isAbsoluteUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    private function composeUrl(string $scheme, string $host, ?int $port, string $path, string $query): string
    {
        $portSuffix = $port !== null && !in_array($port, [80, 443], true) ? ':' . $port : '';
        $querySuffix = $query !== '' ? '?' . $query : '';

        return $scheme . '://' . $host . $portSuffix . $path . $querySuffix;
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function composeUrlFromParts(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $user = (string) ($parts['user'] ?? '');
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) && $parts['query'] !== ''
            ? '?' . $parts['query']
            : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }
}
