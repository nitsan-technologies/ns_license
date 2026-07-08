<?php

use NITSAN\NsLicense\Controller\NsLicenseModuleController;
use TYPO3\CMS\Core\Information\Typo3Version;

$isV14OrHigher = (new Typo3Version())->getMajorVersion() >= 14;

$licenseModule = [
    'parent' => 'tools',
    'position' => ['before' => 'tools_ExtensionmanagerExtensionmanager'],
    'access' => 'systemMaintainer',
    'path' => '/module/nitsan/NsLicense',
    'labels' => 'LLL:EXT:ns_license/Resources/Private/Language/locallang_licensemodule.xlf',
    'extensionName' => 'NsLicense',
    'inheritNavigationComponent' => false,
    'controllerActions' => [
        NsLicenseModuleController::class => [
            'list', 'update', 'activation', 'deactivation', 'reactivation', 'extendTrial', 'getServicesData', 'getShopData', 'fetchExtensionLogs',
        ],
    ],
];
if ($isV14OrHigher) {
    $licenseModule['icon'] = 'EXT:ns_license/Resources/Public/Icons/submodule-nslicense-v14.svg';
} else {
    $licenseModule['iconIdentifier'] = 'submodule-nslicense-v12';
}

$module = [
    'nitsan_nslicensemodule' => $licenseModule,
];

if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('ns_basetheme')) {
    $module['nitsan_module'] = [
        'labels' => 'LLL:EXT:ns_license/Resources/Private/Language/BackendModule.xlf',
        'iconIdentifier' => 'module-nitsan',
        'position' => ['after' => 'web'],
    ];
}

return $module;
