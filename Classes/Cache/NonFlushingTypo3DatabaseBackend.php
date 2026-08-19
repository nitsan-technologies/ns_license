<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Cache;

use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;

/**
 * Database cache backend that ignores TYPO3 cache flushes.
 *
 * All Licenses OTP sessions must survive "Flush caches" / flushCachesInGroup.
 * Entries still expire via defaultLifetime / collectGarbage, and remove() still works.
 *
 * Signatures are the overlap of TYPO3 12/13 (untyped $tag, optional :void) and
 * TYPO3 14 (flush(): void, flushByTag(string $tag): void). Do not type $tag as
 * string — that breaks v12/v13 parent compatibility.
 */
final class NonFlushingTypo3DatabaseBackend extends Typo3DatabaseBackend
{
    public function flush(): void
    {
        // Intentionally empty: do not truncate All Licenses sessions on cache flush.
    }

    /**
     * @param string $tag
     */
    public function flushByTag($tag): void
    {
        // Intentionally empty.
    }

    public function flushByTags(array $tags): void
    {
        // Intentionally empty.
    }
}
