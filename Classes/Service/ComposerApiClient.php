<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Http\Message\ResponseInterface;

/**
 * Thin HTTP client wrapper for Composer API calls.
 */
class ComposerApiClient
{
    private RequestFactory $requestFactory;

    public function __construct(?RequestFactory $requestFactory = null)
    {
        $this->requestFactory = $requestFactory ?? GeneralUtility::makeInstance(RequestFactory::class);
    }

    /**
     * Perform an HTTP request and return response.
     *
     * @param string $url
     * @param string $method
     * @param array<string,mixed> $options
     * @return ResponseInterface
     */
    public function request(string $url, string $method = 'GET', array $options = []): ResponseInterface
    {
        return $this->requestFactory->request($url, $method, $options);
    }

    /**
     * Convenience helper: perform request and decode JSON into associative array.
     *
     * @param string $url
     * @param string $method
     * @param array<string,mixed> $options
     * @return array<string,mixed>|null
     */
    public function requestJsonArray(string $url, string $method = 'GET', array $options = []): ?array
    {
        $response = $this->request($url, $method, $options);
        $decoded = json_decode($response->getBody()->getContents(), true);
        return is_array($decoded) ? $decoded : null;
    }

}

