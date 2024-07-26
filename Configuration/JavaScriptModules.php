<?php
return [
    'dependencies' => ['core', 'backend'],
    'tags' => [
        'backend.form',
    ],
    'imports' => [
        '@nitsan/ns-license/main.js' => 'EXT:ns_license/Resources/Public/JavaScript/main.js',
    ]
];
