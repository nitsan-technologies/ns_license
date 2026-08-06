<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

/**
 * Maps ns_product_release_tags.json into catalog detail changelog entries.
 *
 * Client fallback used when detail changelog is empty (GetGitlabReleaseTags).
 * Intentionally preserves **FEATURE** markers (unlike the API/orders mappers)
 * so product-detail.js can classify changelog lines.
 *
 * @internal
 */
final class ReleaseNotesMapper
{
    private const MAX_ENTRIES = 5;

    /**
     * @return list<array{version: string, date: string, changes: list<string>}>
     */
    public static function fromReleaseTagsJson(mixed $json): array
    {
        if (is_array($json)) {
            $decoded = $json;
        } else {
            $raw = trim((string)$json);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [];
            }
        }

        $entries = [];
        foreach ($decoded as $version => $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $versionLabel = trim((string)$version);
            if ($versionLabel === '') {
                continue;
            }

            $notes = trim((string)($payload['release_notes'] ?? ''));
            $changes = self::splitReleaseNotes($notes);
            $entries[] = [
                'version' => $versionLabel,
                'date' => trim((string)($payload['release_date'] ?? '')),
                'changes' => $changes,
            ];
        }

        usort($entries, static function (array $a, array $b): int {
            return version_compare(
                ltrim($b['version'], 'vV'),
                ltrim($a['version'], 'vV')
            );
        });

        return array_slice($entries, 0, self::MAX_ENTRIES);
    }

    /**
     * @return list<string>
     */
    private static function splitReleaseNotes(string $notes): array
    {
        if ($notes === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $notes) ?: [];
        $changes = [];
        foreach ($lines as $line) {
            $line = trim($line);
            // Strip list bullets only; do not eat leading * from **FEATURE** markers.
            $line = (string)preg_replace('/^[-•]\s+|^(?!\*\*[A-Za-z]+\*\*)\*\s+/u', '', $line);
            if ($line === '') {
                continue;
            }
            $changes[] = $line;
        }

        return $changes !== [] ? $changes : [$notes];
    }
}
