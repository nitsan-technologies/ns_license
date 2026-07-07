<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Information\Typo3Version;

$isV14OrHigher = (new Typo3Version())->getMajorVersion() >= 14;

return [
    'submodule-nslicense' => [
        'provider' => SvgIconProvider::class,
        'source' => $isV14OrHigher
            ? 'EXT:ns_license/Resources/Public/Icons/submodule-nslicense-v14.svg'
            : 'EXT:ns_license/Resources/Public/Icons/submodule-nslicense-v12.svg',
    ],
    'module-nitsan' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ns_license/Resources/Public/Icons/module-nitsan.svg',
    ],
];
