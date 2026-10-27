<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

/**
 * Maps licensed T3Planet products to their bundled foundation dependencies.
 *
 * Chatbot/search products (AC/AS) ship EXT:ns_t3cs via cs_download_url.
 * EXT:ns_t3af is free via Packagist / TER / composer (not zip-bundled).
 */
final class ProductBundleRegistry
{
    /** @var list<string> */
    public const CHATBOT_SEARCH_PRODUCTS = ['ns_t3ac', 'ns_t3as'];

    public static function isChatbotSearchProduct(string $extensionKey): bool
    {
        return in_array($extensionKey, self::CHATBOT_SEARCH_PRODUCTS, true);
    }

    /**
     * Normalize a download-url map from JSON (object or array) without PHP 8 TypeErrors.
     *
     * @return array<string, mixed>
     */
    public static function toDownloadUrlMap(mixed $downloadUrl): array
    {
        if (is_object($downloadUrl)) {
            return get_mangled_object_vars($downloadUrl);
        }

        return is_array($downloadUrl) ? $downloadUrl : [];
    }

    /**
     * @return array{version:string,ltsVersion:string}
     */
    public static function resolveDownloadVersions(object $data, string $downloadField): array
    {
        $downloadUrl = self::toDownloadUrlMap($data->{$downloadField} ?? []);
        if ($downloadUrl === []) {
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
