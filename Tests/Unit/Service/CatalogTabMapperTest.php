<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Tests\Unit\Service;

use NITSAN\NsLicense\Service\CatalogTabMapper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class CatalogTabMapperTest extends UnitTestCase
{
    #[Test]
    public function buildTabsFromCatalogMapsLegacySections(): void
    {
        $catalog = [
            'sections' => [
                [
                    'id' => 'ai-universe',
                    'title' => 'AI Universe',
                    'items' => [
                        ['extensionKey' => 'ns_t3ai', 'name' => 'T3 AI'],
                    ],
                ],
                [
                    'id' => 'free-extensions',
                    'title' => 'Free Extensions',
                    'items' => [
                        ['extensionKey' => 'ns_free', 'name' => 'Free Ext', 'price' => 'Free'],
                    ],
                ],
                [
                    'id' => 'premium-templates',
                    'title' => 'Premium Templates',
                    'items' => [
                        ['extensionKey' => 'ns_t3karma', 'name' => 'T3Karma'],
                    ],
                ],
            ],
        ];

        $tabs = CatalogTabMapper::buildTabsFromCatalog($catalog);

        self::assertCount(1, $tabs[CatalogTabMapper::TAB_AI_UNIVERSE]['items']);
        self::assertSame('ns_t3ai', $tabs[CatalogTabMapper::TAB_AI_UNIVERSE]['items'][0]['extensionKey']);

        self::assertCount(1, $tabs[CatalogTabMapper::TAB_EXTENSIONS]['items']);
        self::assertTrue($tabs[CatalogTabMapper::TAB_EXTENSIONS]['items'][0]['isFree']);

        self::assertCount(1, $tabs[CatalogTabMapper::TAB_TEMPLATES]['items']);
        self::assertSame('ns_t3karma', $tabs[CatalogTabMapper::TAB_TEMPLATES]['items'][0]['extensionKey']);
    }
}
