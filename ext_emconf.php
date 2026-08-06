<?php

$EM_CONF['ns_license'] = [
    'title' => 'T3Planet Shop',
    'description' => 'T3Planet Shop brings the entire T3Planet products - TYPO3 templates, extensions, and AI solutions - straight into your TYPO3 backend. Browse available products, purchase or start a 30-day free trial, and install with one click, all without leaving the TYPO3 CMS Backend. Once installed, it handles activation, renewals, domain transfers, and updates, so agencies and developers managing multiple T3Planet products never need a support ticket just to stay licensed and updated.',
    'category' => 'templates',
    'author' => 'Team T3Planet',
    'author_email' => 'info@t3planet.de',
    'author_company' => 'T3Planet',
    'state' => 'stable',
    'version' => '14.4.2',
    'constraints'
      => [
        'depends'
          => [
            'typo3' => '12.0.0-14.9.99',
            'extensionmanager' => '12.0.0-14.9.99',
          ],
      ],
    'autoload'
      => [
        'classmap'
        => [
            0 => 'Classes/',
        ],
      ],
    'uploadfolder' => false,
    'clearcacheonload' => false,
];
