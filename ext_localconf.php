<?php

// TYPO3 Security Check
if (!defined('TYPO3')) {
    die('Access denied.');
}

$_EXTKEY = 'ns_license';
//Module Icon
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconIdentifiers = [
    'submodule-nslicense',
    'module-nitsan',
];
// Let's register module's icon
foreach ($iconIdentifiers as $identifier) {
    $iconRegistry->registerIcon(
        $identifier,
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:ns_license/Resources/Public/Icons/'.$identifier.'.svg']
    );
}
