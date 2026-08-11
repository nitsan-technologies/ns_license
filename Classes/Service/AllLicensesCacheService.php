<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;

/**
 * Short-lived TYPO3 cache for All Licenses portfolio payloads after email OTP.
 */
final class AllLicensesCacheService
{
    public const CACHE_NAME = 'ns_license_all_licenses';

    /** Absolute lifetime of a cache entry (20 minutes). */
    public const TTL_SECONDS = 1200;

    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {}

    /**
     * @return array{email:string,verificationToken:string,fetchedAt:int,data:array<string,mixed>}|null
     */
    public function get(int $beUserId, string $email): ?array
    {
        if ($beUserId <= 0 || trim($email) === '') {
            return null;
        }

        try {
            $cache = $this->cacheManager->getCache(self::CACHE_NAME);
        } catch (NoSuchCacheException) {
            return null;
        }

        try {
            $entry = $cache->get($this->buildIdentifier($beUserId, $email));
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($entry)) {
            return null;
        }

        $fetchedAt = (int) ($entry['fetchedAt'] ?? 0);
        if ($fetchedAt <= 0 || (time() - $fetchedAt) >= self::TTL_SECONDS) {
            $this->remove($beUserId, $email);
            return null;
        }

        $token = trim((string) ($entry['verificationToken'] ?? ''));
        $cachedEmail = trim((string) ($entry['email'] ?? ''));
        $data = $entry['data'] ?? null;
        if ($token === '' || $cachedEmail === '' || !is_array($data)) {
            return null;
        }

        return [
            'email' => $cachedEmail,
            'verificationToken' => $token,
            'fetchedAt' => $fetchedAt,
            'data' => $data,
        ];
    }

    /**
     * Return any non-expired portfolio entry for this backend user (any email).
     *
     * @return array{email:string,verificationToken:string,fetchedAt:int,data:array<string,mixed>}|null
     */
    public function getForBackendUser(int $beUserId): ?array
    {
        if ($beUserId <= 0) {
            return null;
        }

        try {
            $cache = $this->cacheManager->getCache(self::CACHE_NAME);
        } catch (NoSuchCacheException) {
            return null;
        }

        try {
            $pointer = $cache->get($this->buildUserPointerIdentifier($beUserId));
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($pointer)) {
            return null;
        }

        $email = trim((string) ($pointer['email'] ?? ''));
        if ($email === '') {
            return null;
        }

        return $this->get($beUserId, $email);
    }

    /**
     * @param array<string,mixed> $apiPayload
     */
    public function set(int $beUserId, string $email, string $verificationToken, array $apiPayload): void
    {
        if ($beUserId <= 0 || trim($email) === '' || trim($verificationToken) === '') {
            return;
        }

        try {
            $cache = $this->cacheManager->getCache(self::CACHE_NAME);
        } catch (NoSuchCacheException) {
            return;
        }

        $entry = [
            'email' => trim($email),
            'verificationToken' => trim($verificationToken),
            'fetchedAt' => time(),
            'data' => $apiPayload,
        ];

        try {
            $cache->set(
                $this->buildIdentifier($beUserId, $email),
                $entry,
                [],
                self::TTL_SECONDS
            );
            $cache->set(
                $this->buildUserPointerIdentifier($beUserId),
                ['email' => trim($email)],
                [],
                self::TTL_SECONDS
            );
        } catch (\Throwable) {
            // Cache table may be missing until DB compare/create; OTP still succeeds.
        }
    }

    public function remove(int $beUserId, string $email = ''): void
    {
        if ($beUserId <= 0) {
            return;
        }

        try {
            $cache = $this->cacheManager->getCache(self::CACHE_NAME);
        } catch (NoSuchCacheException) {
            return;
        }

        try {
            if ($email !== '') {
                $cache->remove($this->buildIdentifier($beUserId, $email));
            } else {
                $pointer = $cache->get($this->buildUserPointerIdentifier($beUserId));
                if (is_array($pointer)) {
                    $cachedEmail = trim((string) ($pointer['email'] ?? ''));
                    if ($cachedEmail !== '') {
                        $cache->remove($this->buildIdentifier($beUserId, $cachedEmail));
                    }
                }
            }

            $cache->remove($this->buildUserPointerIdentifier($beUserId));
        } catch (\Throwable) {
            // Ignore missing cache tables during cleanup.
        }
    }

    private function buildIdentifier(int $beUserId, string $email): string
    {
        return 'all_licenses_' . $beUserId . '_' . sha1(strtolower(trim($email)));
    }

    private function buildUserPointerIdentifier(int $beUserId): string
    {
        return 'all_licenses_user_' . $beUserId;
    }
}
