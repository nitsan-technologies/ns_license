<?php

namespace NITSAN\NsLicense\Hooks;

use NITSAN\NsLicense\Controller\NsLicenseModuleController;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 *
 *
 */
class BackendUserLogin
{
    public function dispatch(array $backendUser)
    {
        $this->nsLicenseModule = GeneralUtility::makeInstance(NsLicenseModuleController::class);
        $this->nsLicenseModule->connectToServer();
    }
}
