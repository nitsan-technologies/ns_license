<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use TYPO3\CMS\Core\Type\Map;

/**
 * Backend CSP: allow framing T3Planet / Pabbly checkout in Get New License Buy modal,
 * YouTube privacy-enhanced embeds on product detail (loaded only after user click),
 * Supademo demos in the catalog demo modal,
 * and catalog/product images from T3Planet CDNs.
 *
 * Hosts must stay in sync with {@see \NITSAN\NsLicense\Service\Checkout\CheckoutUrlValidator}.
 */
return Map::fromEntries([
    Scope::backend(),
    new MutationCollection(
        new Mutation(
            MutationMode::Extend,
            Directive::FrameSrc,
            new UriValue('https://t3planet.shop'),
            new UriValue('*.t3planet.shop'),
            new UriValue('https://t3planet.de'),
            new UriValue('*.t3planet.de'),
            new UriValue('https://t3planet.com'),
            new UriValue('*.t3planet.com'),
            new UriValue('https://payments.pabbly.com'),
            new UriValue('*.pabbly.com'),
            new UriValue('https://pabbly.t3planet.de'),
            new UriValue('https://www.youtube-nocookie.com'),
            new UriValue('https://app.supademo.com'),
            new UriValue('*.supademo.com'),
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::ConnectSrc,
            new UriValue('https://t3planet.shop'),
            new UriValue('*.t3planet.shop'),
            new UriValue('https://t3planet.de'),
            new UriValue('*.t3planet.de'),
            new UriValue('https://t3planet.com'),
            new UriValue('*.t3planet.com'),
            new UriValue('https://payments.pabbly.com'),
            new UriValue('*.pabbly.com'),
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::ImgSrc,
            new UriValue('https://t3planet.shop'),
            new UriValue('*.t3planet.shop'),
            new UriValue('https://t3planet.de'),
            new UriValue('*.t3planet.de'),
            new UriValue('https://t3planet.com'),
            new UriValue('*.t3planet.com'),
        ),
    ),
]);
