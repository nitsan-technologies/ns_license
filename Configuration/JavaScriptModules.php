<?php

return [
    'dependencies' => ['core', 'backend'],
    'tags' => [
        'backend.form',
    ],
    'imports' => [
        '@nitsan/ns-license/main.js' => 'EXT:ns_license/Resources/Public/JavaScript/main.js',
        '@nitsan/ns-license/main-v14.js' => 'EXT:ns_license/Resources/Public/JavaScript/main-v14.js',
        '@nitsan/ns-license/filter.js' => 'EXT:ns_license/Resources/Public/JavaScript/filter.js',
        '@nitsan/ns-license/domains.js' => 'EXT:ns_license/Resources/Public/JavaScript/domains.js',
    ],
];
