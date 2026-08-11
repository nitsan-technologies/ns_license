<?php

// TYPO3 Security Check
if (!defined('TYPO3')) {
    die('Access denied.');
}

$_EXTKEY = 'ns_license';

// Register backend stylesheets
$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['ns_license'] = 'EXT:ns_license/Resources/Public/css/custom.css';

// Catalog JSON cache (AI Universe / Extensions / Templates tabs)
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ns_license_catalog'] ??= [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
    'options' => [
        'defaultLifetime' => 86400,
    ],
    'groups' => ['system'],
];

// All Licenses portfolio cache (email OTP session, 20 minutes)
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ns_license_all_licenses'] ??= [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
    'options' => [
        'defaultLifetime' => 1200,
    ],
    'groups' => ['system'],
];
