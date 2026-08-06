<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service\Checkout;

/**
 * Normalizes catalog checkout URLs from GetProduct.php.
 *
 * Appends the per-product Pabbly RedirectTo custom field (e.g. cf_redirectto_xxxxx)
 * with an absolute backend return URL (token stripped).
 */
final class CheckoutUrlBuilder
{
    public function __construct(
        private readonly CheckoutUrlValidator $validator,
        private readonly CheckoutReturnUrlBuilder $returnUrlBuilder,
    ) {}

    /**
     * @param string $checkoutUrl Base allowlisted subscribe URL
     * @param string $returnUrl Absolute BE return URL (optional; built from module if empty)
     * @param string $redirectParam Pabbly custom field name, e.g. cf_redirectto_dw4dki
     */
    public function normalize(string $checkoutUrl, string $returnUrl = '', string $redirectParam = ''): string
    {
        $checkoutUrl = trim($checkoutUrl);
        if ($checkoutUrl === '' || !$this->validator->isAllowed($checkoutUrl)) {
            return '';
        }

        $returnUrl = $returnUrl !== ''
            ? $this->returnUrlBuilder->normalize($returnUrl)
            : $this->returnUrlBuilder->fromModule(['purchase_success' => '1']);

        $redirectParam = trim($redirectParam);
        if ($redirectParam === '' || $returnUrl === '') {
            return $checkoutUrl;
        }

        return $this->applyRedirectParam($checkoutUrl, $redirectParam, $returnUrl);
    }

    private function applyRedirectParam(string $checkoutUrl, string $redirectParam, string $returnUrl): string
    {
        $parts = parse_url($checkoutUrl);
        if (!is_array($parts)) {
            return $checkoutUrl;
        }

        $query = [];
        $queryString = (string) ($parts['query'] ?? '');
        if ($queryString !== '') {
            parse_str($queryString, $query);
        }

        foreach (array_keys($query) as $name) {
            if (str_starts_with(strtolower((string) $name), 'cf_redirectto')) {
                unset($query[$name]);
            }
        }
        $query[$redirectParam] = $returnUrl;
        $parts['query'] = http_build_query($query);

        return $this->composeUrl($parts);
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function composeUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $user = (string) ($parts['user'] ?? '');
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }
}
