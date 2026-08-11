<?php

use NITSAN\NsLicense\Controller\AllLicensesAjaxController;
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
    ],
    'catalog_product_detail' => [
        'path' => '/license/catalog-product-detail',
        'target' => NsLicenseModuleController::class . '::getCatalogProductDetailAction'
    ],
    'get_products' => [
        'path' => '/license/get-products',
        'target' => NsLicenseModuleController::class . '::getProductsAction'
    ],
    'start_trial' => [
        'path' => '/license/start-trial',
        'target' => NsLicenseModuleController::class . '::startTrialAction'
    ],
    'verify_trial_otp' => [
        'path' => '/license/verify-trial-otp',
        'target' => NsLicenseModuleController::class . '::verifyTrialOtpAction'
    ],
    'send_license_email_otp' => [
        'path' => '/license/send-license-email-otp',
        'target' => AllLicensesAjaxController::class . '::sendLicenseEmailOtpAction'
    ],
    'verify_license_email_otp' => [
        'path' => '/license/verify-license-email-otp',
        'target' => AllLicensesAjaxController::class . '::verifyLicenseEmailOtpAction'
    ],
    'get_licenses_by_email' => [
        'path' => '/license/get-licenses-by-email',
        'target' => AllLicensesAjaxController::class . '::getLicensesByEmailAction'
    ],
    'clear_all_licenses_session' => [
        'path' => '/license/clear-all-licenses-session',
        'target' => AllLicensesAjaxController::class . '::clearAllLicensesSessionAction'
    ],
    'prepare_checkout' => [
        'path' => '/license/prepare-checkout',
        'target' => NsLicenseModuleController::class . '::prepareCheckoutAction'
    ],
    'resolve_purchase_token' => [
        'path' => '/license/resolve-purchase-token',
        'target' => NsLicenseModuleController::class . '::resolvePurchaseTokenAction'
    ],
    'activate_license' => [
        'path' => '/license/activate-license',
        'target' => NsLicenseModuleController::class . '::activateLicenseAction'
    ],
];
