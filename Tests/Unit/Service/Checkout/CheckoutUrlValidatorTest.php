<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Tests\Unit\Service\Checkout;

use NITSAN\NsLicense\Service\Checkout\CheckoutUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckoutUrlValidatorTest extends TestCase
{
    private CheckoutUrlValidator $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new CheckoutUrlValidator();
    }

    #[DataProvider('allowedUrlsProvider')]
    public function testAllowsTrustedCheckoutHosts(string $url): void
    {
        self::assertTrue($this->subject->isAllowed($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedUrlsProvider(): iterable
    {
        yield 'pabbly payments' => ['https://payments.pabbly.com/checkout/abc'];
        yield 't3planet shop subscribe' => ['https://t3planet.shop/subscribe/69e33e613d0c724d2b06ef87/AS-1'];
        yield 'pabbly subdomain' => ['https://pabbly.t3planet.de/checkout/pro'];
        yield 't3planet de subdomain' => ['https://pay.t3planet.de/order/1'];
    }

    #[DataProvider('deniedUrlsProvider')]
    public function testRejectsUntrustedCheckoutHosts(string $url): void
    {
        self::assertFalse($this->subject->isAllowed($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function deniedUrlsProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'http' => ['http://payments.pabbly.com/checkout'];
        yield 'foreign host' => ['https://evil.example/phish'];
        yield 'javascript' => ['javascript:alert(1)'];
    }
}
