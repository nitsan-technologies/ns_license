<?php

$EM_CONF['ns_license'] = [
    'title' => 'License Manager',
    'description' => 'Manage licenses of your TYPO3 templates and extensions purchased from T3Planet. Includes activation and license validation. Documentation available at https://docs.t3planet.com/en/latest/License/LicenseActivation/Index.html',
    'category' => 'templates',
    'author' => 'Team T3Planet',
    'author_email' => 'info@t3planet.de',
    'author_company' => 'T3Planet',
    'state' => 'stable',
    'version' => '14.2.2',
    'constraints'
      => [
        'depends'
          => [
            'typo3' => '12.0.0-14.1.1',
            'extensionmanager' => '12.0.0-14.1.1',
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