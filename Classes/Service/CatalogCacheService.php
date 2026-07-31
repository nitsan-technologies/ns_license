<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * TYPO3 Cache Framework storage for catalog JSON (shop data).
 *
 * Uses soft revalidation (ETag / If-None-Match) so customer instances can check
 * for newer catalog data often without re-downloading an unchanged payload.
 */
final class CatalogCacheService
{
    public const CACHE_NAME = 'ns_license_catalog';

    public const CACHE_TAG = 'ns_license_catalog';

    private const CACHE_IDENTIFIER = 'catalog_full';

    /** Serve cached payload without contacting the API. */
    private const SOFT_TTL_SECONDS = 3600;

    /** Absolute lifetime of a cache entry (also configured as defaultLifetime). */
    private const HARD_TTL_SECONDS = 86400;

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
            $entry = $this->getEntryFromCache();
            if ($entry !== null) {
                $age = time() - (int)($entry['fetchedAt'] ?? 0);
                if ($age >= 0 && $age < self::SOFT_TTL_SECONDS) {
                    return $entry['data'];
                }

                // Soft-revalidate with If-None-Match when possible.
                $revalidated = $this->fetchAndStore($entry['etag'] ?? '', false);
                if ($revalidated !== null) {
                    return $revalidated;
                }

                // Stale-ok: keep serving cached catalog if API is unreachable.
                if ($this->isValidCatalog($entry['data'])) {
                    return $entry['data'];
                }
            }
        }

        $fresh = $this->fetchAndStore('', $forceRefresh);
        if ($fresh !== null) {
            return $fresh;
        }

        $fallback = $this->getEntryFromCache();
        return $fallback['data'] ?? [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function warmCache(array $data, string $etag = '', int $syncedAt = 0): void
    {
        if ($this->isValidCatalog($data)) {
            $this->storeEntry($data, $etag, $syncedAt > 0 ? $syncedAt : time());
        }
    }

    public function refreshFromApi(): array
    {
        $entry = $this->getEntryFromCache();
        $etag = is_array($entry) ? (string)($entry['etag'] ?? '') : '';
        // Prefer conditional GET so unchanged catalogs return 304 (no heavy download).
        $fresh = $this->fetchAndStore($etag, false);
        if ($fresh !== null) {
            return $fresh;
        }

        return $entry['data'] ?? [];
    }

    public function flush(): void
    {
        try {
            $this->cacheManager->getCache(self::CACHE_NAME)->flushByTag(self::CACHE_TAG);
        } catch (NoSuchCacheException) {
        }
    }

    /**
     * Fetch a single full catalog product (keywords, features, FAQ, …) by extension key.
     *
     * @return array<string, mixed>|null
     */
    public function fetchProductDetail(string $extensionKey): ?array
    {
        $extensionKey = trim($extensionKey);
        if ($extensionKey === '') {
            return null;
        }

        $url = rtrim($this->getApiBaseUrl(), '/')
            . '/GetShopAndServicesData.php?type=shop&extensionKey=' . rawurlencode($extensionKey);

        try {
            $result = $this->composerApiClient->requestJsonResult($url, 'GET', []);
            $data = $result['data'];
            if (!is_array($data) || empty($data['status'])) {
                return null;
            }
            $item = $data['item'] ?? null;
            if (!is_array($item)) {
                return null;
            }

            return $this->enrichChangelogIfEmpty($item, $extensionKey);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Shop cache often stores changelog as []; fill from GetGitlabReleaseTags when empty.
     * Safe no-op once the API already enriches changelog on detail fetch.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function enrichChangelogIfEmpty(array $item, string $extensionKey): array
    {
        $existing = $item['changelog'] ?? null;
        if (is_array($existing) && $existing !== []) {
            return $item;
        }

        $key = trim((string)($item['extensionKey'] ?? $extensionKey));
        if ($key === '') {
            $item['changelog'] = is_array($existing) ? $existing : [];
            return $item;
        }

        $url = rtrim($this->getApiBaseUrl(), '/')
            . '/GetGitlabReleaseTags.php?getTags=1&extensionKey=' . rawurlencode($key);

        try {
            $result = $this->composerApiClient->requestJsonResult($url, 'GET', []);
            $raw = $result['data'];
            if (!is_array($raw)) {
                $item['changelog'] = is_array($existing) ? $existing : [];
                return $item;
            }
            $item['changelog'] = ReleaseNotesMapper::fromReleaseTagsJson($raw);
        } catch (\Throwable) {
            $item['changelog'] = is_array($existing) ? $existing : [];
        }

        return $item;
    }

    /**
     * @return array{data: array<string,mixed>, etag: string, syncedAt: int, fetchedAt: int}|null
     */
    private function getEntryFromCache(): ?array
    {
        try {
            $cache = $this->cacheManager->getCache(self::CACHE_NAME);
            $entry = $cache->get(self::CACHE_IDENTIFIER);
            if (!is_array($entry)) {
                return null;
            }

            // Legacy bare catalog array (pre-metadata wrapper).
            if ($this->isValidCatalog($entry) && !isset($entry['data'])) {
                return [
                    'data' => $entry,
                    'etag' => '',
                    'syncedAt' => 0,
                    'fetchedAt' => 0,
                ];
            }

            $data = $entry['data'] ?? null;
            if (!is_array($data) || !$this->isValidCatalog($data)) {
                return null;
            }

            return [
                'data' => $data,
                'etag' => (string)($entry['etag'] ?? ''),
                'syncedAt' => (int)($entry['syncedAt'] ?? 0),
                'fetchedAt' => (int)($entry['fetchedAt'] ?? 0),
            ];
        } catch (NoSuchCacheException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function storeEntry(array $data, string $etag, int $syncedAt): void
    {
        try {
            $cache = $this->cacheManager->getCache(self::CACHE_NAME);
            $cache->set(
                self::CACHE_IDENTIFIER,
                [
                    'data' => $data,
                    'etag' => $etag,
                    'syncedAt' => $syncedAt,
                    'fetchedAt' => time(),
                ],
                [self::CACHE_TAG],
                self::HARD_TTL_SECONDS
            );
        } catch (NoSuchCacheException) {
        }
    }

    /**
     * @return array<string, mixed>|null Catalog payload or null on failure
     */
    private function fetchAndStore(string $existingEtag, bool $forceFullBody): ?array
    {
        // Prefer slim list payload for local catalog cards; detail fields load on demand.
        $url = rtrim($this->getApiBaseUrl(), '/') . '/GetShopAndServicesData.php?type=shop&fields=list';

        $options = [];
        if (!$forceFullBody && $existingEtag !== '') {
            $options['headers'] = [
                'If-None-Match' => $existingEtag,
            ];
        }

        try {
            $result = $this->composerApiClient->requestJsonResult($url, 'GET', $options);
        } catch (\Throwable) {
            return null;
        }

        if ($result['notModified']) {
            $entry = $this->getEntryFromCache();
            if ($entry === null) {
                return null;
            }
            // Touch fetchedAt so soft TTL resets without rewriting catalog body.
            $etag = $result['etag'] !== '' ? $result['etag'] : $entry['etag'];
            $syncedAt = $result['syncedAt'] > 0 ? $result['syncedAt'] : $entry['syncedAt'];
            $this->storeEntry($entry['data'], $etag, $syncedAt > 0 ? $syncedAt : time());
            return $entry['data'];
        }

        if ($result['status'] !== 200 || !is_array($result['data']) || !$this->isValidCatalog($result['data'])) {
            return null;
        }

        $syncedAt = $result['syncedAt'] > 0 ? $result['syncedAt'] : time();
        $this->storeEntry($result['data'], $result['etag'], $syncedAt);
        return $result['data'];
    }

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
