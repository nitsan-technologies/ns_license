<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    // TYPO3 v14+ backend module menu (transparent — currentColor + accent)
    'submodule-nslicense-v14' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ns_license/Resources/Public/Icons/submodule-nslicense-v14.svg',
    ],
    // TYPO3 v12/v13 backend module menu (green badge tile)
    'submodule-nslicense-v12' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ns_license/Resources/Public/Icons/submodule-nslicense-v12.svg',
    ],
    'module-nitsan' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ns_license/Resources/Public/Icons/module-nitsan.svg',
    ],
];
