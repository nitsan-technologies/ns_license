<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Tests\Unit\Service\Checkout;

use NITSAN\NsLicense\Service\Checkout\CheckoutReturnUrlBuilder;
use NITSAN\NsLicense\Service\Checkout\CheckoutUrlBuilder;
use NITSAN\NsLicense\Service\Checkout\CheckoutUrlValidator;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Routing\UriBuilder;

final class CheckoutUrlBuilderTest extends TestCase
{
    public function testRejectsNonAllowlistedUrl(): void
    {
        $builder = $this->createBuilder();
        self::assertSame(
            '',
            $builder->normalize('https://evil.example/pay', 'https://example.com/typo3', 'cf_redirectto_abc'),
        );
    }

    public function testPassThroughWhenRedirectParamMissing(): void
    {
        $url = 'https://t3planet.shop/subscribe/69e33e613d0c724d2b06ef87/AS-1';
        $builder = $this->createBuilder();
        self::assertSame($url, $builder->normalize($url, 'https://example.com/typo3/module', ''));
    }

    public function testAppendsRedirectParam(): void
    {
        $input = 'https://t3planet.shop/subscribe/plan/AS-1';
        $returnUrl = 'https://example.com/typo3/module/nitsan/NsLicense?purchase_success=1';
        $builder = $this->createBuilder();
        $result = $builder->normalize($input, $returnUrl, 'cf_redirectto_abc');

        self::assertStringStartsWith('https://t3planet.shop/subscribe/plan/AS-1?', $result);
        self::assertStringContainsString('cf_redirectto_abc=', $result);
        self::assertStringContainsString(rawurlencode($returnUrl), $result);
    }

    public function testReplacesExistingCfRedirectToParams(): void
    {
        $input = 'https://t3planet.shop/subscribe/plan/AS-1?cf_redirectto_old=OLD&other=1';
        $returnUrl = 'https://example.com/typo3/module/nitsan/NsLicense';
        $builder = $this->createBuilder();
        $result = $builder->normalize($input, $returnUrl, 'cf_redirectto_new');

        self::assertStringContainsString('cf_redirectto_new=', $result);
        self::assertStringContainsString('other=1', $result);
        self::assertStringNotContainsString('cf_redirectto_old=', $result);
        self::assertStringNotContainsString('OLD', $result);
    }

    private function createBuilder(): CheckoutUrlBuilder
    {
        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->method('buildUriFromRoute')->willReturn(
            'https://example.com/typo3/module/nitsan/NsLicense?token=secret&purchase_success=1'
        );

        return new CheckoutUrlBuilder(
            new CheckoutUrlValidator(),
            new CheckoutReturnUrlBuilder($uriBuilder),
        );
    }
}
