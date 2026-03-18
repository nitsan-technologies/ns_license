<?php

use NITSAN\NsLicense\Controller\NsLicenseModuleController;

return [
    'add_domain' => [
        'path' => '/license/add-domain',
        'target' => NsLicenseModuleController::class . '::addDomainAction'
    ],
    'delete_domain' => [
        'path' => '/license/delete-domain',
        'target' => NsLicenseModuleController::class . '::deleteDomainAction'
    ],
    'update_domain' => [
        'path' => '/license/update-domain',
        'target' => NsLicenseModuleController::class . '::updateDomainAction'
    ],
    'fetch_data' => [
        'path' => '/license/fetch-data',
        'target' => NsLicenseModuleController::class . '::fetchDataAction'
    ]
];
