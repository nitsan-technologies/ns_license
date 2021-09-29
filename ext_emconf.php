<?php

// Provide detailed information and depenencies of EXT:ns_license
$EM_CONF['ns_license'] = [
    'title' => '[NITSAN] License Management',
    'description' => 'Manage License(s) of your purchased T3Terminal\'s premium TYPO3 products. Know more at documentation.',
    'category' => 'templates',
    'author' => 'Team NITSAN',
    'author_email' => 'sanjay@nitsan.in',
    'author_company' => 'NITSAN Technologies Pvt Ltd',
    'state' => 'stable',
    'version' => '1.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '8.0.0-10.9.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'classmap' => ['Classes/']
    ]
];
