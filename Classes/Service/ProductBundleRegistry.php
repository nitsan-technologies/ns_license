<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

/**
 * Maps licensed T3Planet products to their bundled foundation dependencies.
 *
 * Chatbot/search products (AC/AS) ship both shells:
 * - EXT:ns_t3cs via cs_download_url
 * - EXT:ns_t3af via af_download_url
 *
 * AI products (T3AI/T3AA) ship EXT:ns_t3af via af_download_url.
 */
final class ProductBundleRegistry
{
    /** @var list<string> */
    public const CHATBOT_SEARCH_PRODUCTS = ['ns_t3ac', 'ns_t3as'];

    /** @var list<string> */
    public const AI_FOUNDATION_DEPENDENT_PRODUCTS = [
        'ns_t3ai',
        'ns_t3aa',
        'ns_t3ac',
        'ns_t3as',
    ];

    public static function isChatbotSearchProduct(string $extensionKey): bool
    {
        return in_array($extensionKey, self::CHATBOT_SEARCH_PRODUCTS, true);
    }

    public static function isAiFoundationDependentProduct(string $extensionKey): bool
    {
        return in_array($extensionKey, self::AI_FOUNDATION_DEPENDENT_PRODUCTS, true);
    }

    /**
     * @return array{version:string,ltsVersion:string}
     */
    public static function resolveDownloadVersions(object $data, string $downloadField): array
    {
        $downloadUrl = $data->{$downloadField} ?? [];
        if (PHP_VERSION_ID >= 80000 && $downloadUrl !== []) {
            $downloadUrl = get_mangled_object_vars($downloadUrl);
        }
        if (!is_array($downloadUrl) || $downloadUrl === []) {
            return ['version' => '', 'ltsVersion' => ''];
        }

        end($downloadUrl);
        $ltsVersion = (string) key($downloadUrl);

        return [
            'version' => $ltsVersion,
            'ltsVersion' => $ltsVersion,
        ];
    }
}
