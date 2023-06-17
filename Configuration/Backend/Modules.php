<?php

use NITSAN\NsLicense\Controller\NsLicenseModuleController;

$module = [
    'nitsan_nslicensemodule' => [
        'parent' => 'nitsan_module',
        'position' => ['after' => 'top'],
        'access' => 'user,group',
        'path' => '/module/nitsan/NsLicense',
        'iconIdentifier' => 'submodule-nslicense',
        'labels' => 'LLL:EXT:ns_license/Resources/Private/Language/locallang_licensemodule.xlf',
        'extensionName' => 'NsLicense',
        'inheritNavigationComponent' => false,
        'controllerActions' => [
            NsLicenseModuleController::class => [
                'list', 'update', 'activation', 'deactivation', 'reactivation', 'checkUpdate',
            ],
        ],
    ],
];

if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('ns_basetheme')) {
    $module['nitsan_module'] = [
        'labels' => 'LLL:EXT:ns_license/Resources/Private/Language/BackendModule.xlf',
        'iconIdentifier' => 'module-nitsan',
        'position' => ['after' => 'web'],
    ];
}

return $module;
