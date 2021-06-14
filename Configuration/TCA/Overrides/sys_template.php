<?php
defined('TYPO3_MODE') or die();

$_EXTKEY = 'ns_license';
// Add default include static TypoScript (for root page)
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    $_EXTKEY,
    'Configuration/TypoScript',
    '[NITSAN] License Module'
);
