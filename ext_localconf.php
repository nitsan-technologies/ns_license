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

// All Licenses portfolio cache (email OTP session, 7 days; not flushed with system caches)
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ns_license_all_licenses'] ??= [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \NITSAN\NsLicense\Cache\NonFlushingTypo3DatabaseBackend::class,
    'options' => [
        'defaultLifetime' => 604800,
    ],
    'groups' => [],
];
