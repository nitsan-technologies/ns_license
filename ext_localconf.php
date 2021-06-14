<?php

// TYPO3 Security Check
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}
$_EXTKEY = 'ns_license';
if (TYPO3_MODE === 'BE' && version_compare(TYPO3_branch, '8.0', '>') && version_compare(TYPO3_branch, '9.0', '<')) {
    $class = 'TYPO3\\CMS\\Extbase\\SignalSlot\\Dispatcher';
    $dispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($class);
    $dispatcher->connect(
        'TYPO3\\CMS\\Extensionmanager\\Service\\ExtensionManagementService',
        'hasInstalledExtensions',
        'NITSAN\\NsLicense\\Setup',
        'executeOnSignal'
    );
} elseif (TYPO3_MODE === 'BE' && version_compare(TYPO3_branch, '9.0', '>')) {
    $signalSlotDispatcher = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
    $signalSlotDispatcher->connect(
        \TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class,
        'afterExtensionInstall',
        \NITSAN\NsLicense\Setup::class,
        'executeOnSignal'
    );
}
//Module Icon
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconRegistry->registerIcon(
    'module-nitsan',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    ['source' => 'EXT:ns_license/Resources/Public/Icons/module-nitsan.svg']
);

// Register hook on successful BE user login
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauthgroup.php']['backendUserLogin'][] =
    \NITSAN\NsLicense\Hooks\BackendUserLogin::class . '->dispatch';
