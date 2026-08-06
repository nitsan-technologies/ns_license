<?php

return [
    'dependencies' => ['core', 'backend'],
    'tags' => [
        'backend.form',
    ],
    'imports' => [
        '@nitsan/ns-license/main.js' => 'EXT:ns_license/Resources/Public/JavaScript/main.js',
        '@nitsan/ns-license/filter.js' => 'EXT:ns_license/Resources/Public/JavaScript/filter.js',
        '@nitsan/ns-license/domains.js' => 'EXT:ns_license/Resources/Public/JavaScript/domains.js',
        '@nitsan/ns-license/get-license.js' => 'EXT:ns_license/Resources/Public/JavaScript/get-license.js',
        '@nitsan/ns-license/product-detail.js' => 'EXT:ns_license/Resources/Public/JavaScript/product-detail.js',
    ],
];
