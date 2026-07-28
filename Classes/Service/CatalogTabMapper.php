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
                if ($sectionId === 'free-extensions' || ($item['price'] ?? '') === 'Free') {
                    $item['isFree'] = true;
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
                    if (is_array($item)) {
                        $normalized[$tabKey]['items'][] = $item;
                    }
                }
            }
        }

        return $normalized;
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
