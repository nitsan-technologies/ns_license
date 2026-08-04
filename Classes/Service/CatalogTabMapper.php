<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

/**
 * Maps legacy shop sections to catalog tabs (AI Universe, Extensions, Templates).
 */
final class CatalogTabMapper
{
    public const TAB_AI_UNIVERSE = 'ai-universe';

    public const TAB_EXTENSIONS = 'extensions';

    public const TAB_TEMPLATES = 'templates';

    /**
     * @return list<string>
     */
    public static function getAllowedTabs(): array
    {
        return [
            self::TAB_AI_UNIVERSE,
            self::TAB_EXTENSIONS,
            self::TAB_TEMPLATES,
        ];
    }

    public static function resolveTabForSection(string $sectionId, string $sectionTitle = ''): string
    {
        $id = strtolower(trim($sectionId));
        $title = strtolower(trim($sectionTitle));

        if ($id === self::TAB_AI_UNIVERSE || str_contains($title, 'ai universe')) {
            return self::TAB_AI_UNIVERSE;
        }

        if (
            $id === 'premium-templates'
            || (str_contains($title, 'template') && !str_contains($title, 'extension'))
        ) {
            return self::TAB_TEMPLATES;
        }

        return self::TAB_EXTENSIONS;
    }

    /**
     * Free when price is empty and downloads is not zero.
     *
     * @param array<string, mixed> $item
     */
    public static function isFreeItem(array $item): bool
    {
        $price = trim((string)($item['price'] ?? ''));
        // Display label "Free" is set after classification; treat it as empty for the rule.
        if (strcasecmp($price, 'Free') === 0) {
            $price = '';
        }
        $downloads = self::parseDownloadsCount($item['downloads'] ?? 0);

        return $price === '' && $downloads !== 0;
    }

    /**
     * @param array<string, mixed> $catalogData
     * @return array<string, array{title: string, items: list<array<string, mixed>>}>
     */
    public static function buildTabsFromCatalog(array $catalogData): array
    {
        if (isset($catalogData['tabs']) && is_array($catalogData['tabs'])) {
            return self::normalizeTabs($catalogData['tabs']);
        }

        $tabs = self::emptyTabs();

        $sections = $catalogData['sections'] ?? [];
        if (!is_array($sections)) {
            return $tabs;
        }

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionId = (string)($section['id'] ?? '');
            $sectionTitle = (string)($section['title'] ?? '');
            $tab = self::resolveTabForSection($sectionId, $sectionTitle);
            $items = $section['items'] ?? [];
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $item['isFree'] = self::isFreeItem($item);
                if ($item['isFree'] && trim((string)($item['price'] ?? '')) === '') {
                    $item['price'] = 'Free';
                }
                $tabs[$tab]['items'][] = $item;
            }
        }

        return $tabs;
    }

    /**
     * @param array<string, mixed> $tabs
     * @return array<string, array{title: string, items: list<array<string, mixed>>}>
     */
    private static function normalizeTabs(array $tabs): array
    {
        $normalized = self::emptyTabs();
        foreach (self::getAllowedTabs() as $tabKey) {
            $tab = $tabs[$tabKey] ?? null;
            if (!is_array($tab)) {
                continue;
            }
            $normalized[$tabKey]['title'] = trim((string)($tab['title'] ?? $normalized[$tabKey]['title']));
            $items = $tab['items'] ?? [];
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    // Prefer payload isFree when present; otherwise apply dual rule.
                    if (!array_key_exists('isFree', $item)) {
                        $item['isFree'] = self::isFreeItem($item);
                    } else {
                        $item['isFree'] = (bool)$item['isFree'];
                    }
                    $normalized[$tabKey]['items'][] = $item;
                }
            }
        }

        return $normalized;
    }

    private static function parseDownloadsCount(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }
        if (is_float($value)) {
            return max(0, (int) round($value));
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return 0;
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*k$/i', $raw, $m) === 1) {
            return (int) round(((float) $m[1]) * 1000);
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*m$/i', $raw, $m) === 1) {
            return (int) round(((float) $m[1]) * 1000000);
        }
        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        return max(0, (int) $raw);
    }

    /**
     * @return array<string, array{title: string, items: list<array<string, mixed>>}>
     */
    private static function emptyTabs(): array
    {
        return [
            self::TAB_AI_UNIVERSE => ['title' => 'AI Universe', 'items' => []],
            self::TAB_EXTENSIONS => ['title' => 'Extensions', 'items' => []],
            self::TAB_TEMPLATES => ['title' => 'Templates', 'items' => []],
        ];
    }
}
