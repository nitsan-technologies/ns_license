<?php
// TYPO3 Security Check
defined('TYPO3_MODE') or die();

$_EXTKEY = 'ns_license';

//Add Modules
if (TYPO3_MODE === 'BE') {
    if (version_compare(TYPO3_branch, '8.0', '>=')) {
        // Add module 'nitsan' after 'Web'
        if (!isset($GLOBALS['TBE_MODULES']['nitsan'])) {
            $temp_TBE_MODULES = [];
            foreach ($GLOBALS['TBE_MODULES'] as $key => $val) {
                if ($key == 'web') {
                    $temp_TBE_MODULES[$key] = $val;
                    $temp_TBE_MODULES['nitsan'] = '';
                } else {
                    $temp_TBE_MODULES[$key] = $val;
                }
            }
            $GLOBALS['TBE_MODULES'] = $temp_TBE_MODULES;
            $GLOBALS['TBE_MODULES']['_configuration']['nitsan'] = [
                'iconIdentifier' => 'module-nitsan',
                'labels' => 'LLL:EXT:ns_license/Resources/Private/Language/BackendModule.xlf',
                'name' => 'nitsan'
            ];
        }
        if (version_compare(TYPO3_branch, '10.0', '>=')) {
            $controller = \NITSAN\NsLicense\Controller\NsLicenseModuleController::class;
        } else {
            $controller = 'NsLicenseModule';
        }
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'NITSAN.NsLicense',
            'nitsan', // Make module a submodule of 'nitsan'
            'NsLicenseModule', // Submodule key
            '', // Position
            [
                $controller => 'list, update, activation, deactivation, checkUpdate',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:ns_license/Resources/Public/Icons/Extension.svg',
                'labels' => 'LLL:EXT:ns_license/Resources/Private/Language/locallang_licensemodule.xlf'
            ]
        );
    }
}
