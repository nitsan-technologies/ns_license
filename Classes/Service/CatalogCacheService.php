<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * TYPO3 Cache Framework storage for catalog JSON (shop data).
 */
final class CatalogCacheService
{
    public const CACHE_NAME = 'ns_license_catalog';

    public const CACHE_TAG = 'ns_license_catalog';

    private const CACHE_IDENTIFIER = 'catalog_full';

    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly ComposerApiClient $composerApiClient,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getCatalog(bool $forceRefresh = false): array
    {
        if (!$forceRefresh) {
            $cached = $this->getFromCache();
            if ($cached !== null) {
                return $cached;
            }
        }

        $fresh = $this->fetchFromApi();
        if ($this->isValidCatalog($fresh)) {
            $this->storeInCache($fresh);
            return $fresh;
        }

        return $this->getFromCache() ?? [];
    }

    /**
     * Force fetch from API and update cache.
     *
     * @return array<string, mixed>
     */
    /**
     * @param array<string, mixed> $data
     */
    public function warmCache(array $data): void
    {
        if ($this->isValidCatalog($data)) {
            $this->storeInCache($data);
        }
    }

    public function refreshFromApi(): array
    {
        $this->flush();
        return $this->getCatalog(true);
    }

    public function flush(): void
    {
        try {
            $this->cacheManager->getCache(self::CACHE_NAME)->flushByTag(self::CACHE_TAG);
        } catch (NoSuchCacheException) {
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getFromCache(): ?array
    {
        try {
            $cache = $this->cacheManager->getCache(self::CACHE_NAME);
            $entry = $cache->get(self::CACHE_IDENTIFIER);
            if (!is_array($entry)) {
                return null;
            }

            return $entry;
        } catch (NoSuchCacheException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function storeInCache(array $data): void
    {
        try {
            $cache = $this->cacheManager->getCache(self::CACHE_NAME);
            $cache->set(self::CACHE_IDENTIFIER, $data, [self::CACHE_TAG], 86400);
        } catch (NoSuchCacheException) {
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchFromApi(): array
    {
        $url = rtrim($this->getApiBaseUrl(), '/') . '/GetShopAndServicesData.php?type=shop';

        try {
            $data = $this->composerApiClient->requestJsonArray($url, 'GET', []);
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getApiBaseUrl(): string
    {
        try {
            $configured = trim((string)$this->extensionConfiguration->get('ns_license', 'apiBaseUrl'));
            if ($configured !== '') {
                return rtrim($configured, '/') . '/';
            }
        } catch (\Throwable) {
        }

        return 'https://composer.thebetaspace.com/API/';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isValidCatalog(array $data): bool
    {
        if (isset($data['tabs']) && is_array($data['tabs'])) {
            return true;
        }

        return isset($data['sections']) && is_array($data['sections']);
    }
}
