<?php

use NITSAN\NsLicense\Controller\NsLicenseModuleController;
use TYPO3\CMS\Core\Information\Typo3Version;

$typo3MajorVersion = (new Typo3Version())->getMajorVersion();

// TYPO3 v14 renamed Admin Tools (tools) → System for systemMaintainer modules,
// and page-tree navigationComponent ID (Feature #107628 / Deprecation #103850).
$licenseModule = [
    'parent' => $typo3MajorVersion >= 14 ? 'system' : 'tools',
    'position' => [
        'before' => $typo3MajorVersion >= 14
            ? 'extensionmanager'
            : 'tools_ExtensionmanagerExtensionmanager',
    ],
    'access' => 'systemMaintainer',
    'path' => '/module/nitsan/NsLicense',
    'labels' => 'LLL:EXT:ns_license/Resources/Private/Language/locallang_licensemodule.xlf',
    'extensionName' => 'NsLicense',
    'inheritNavigationComponentFromMainModule' => false,
    'navigationComponent' => $typo3MajorVersion >= 13
        ? '@typo3/backend/tree/page-tree-element'
        : '@typo3/backend/page-tree/page-tree-element',
    'controllerActions' => [
        NsLicenseModuleController::class => [
            'list', 'update', 'activation', 'deactivation', 'reactivation', 'extendTrial', 'getCatalogData', 'fetchExtensionLogs',
        ],
    ],
];
if ($typo3MajorVersion >= 14) {
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
        'position' => ['after' => $typo3MajorVersion >= 14 ? 'content' : 'web'],
    ];
}

return $module;
