<?php

namespace NITSAN\NsLicense;

use NITSAN\NsLicense\Controller\NsLicenseModuleController;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Setup
 */
class Setup
{
    public function executeOnSignal($extname = null)
    {
        if (strpos($extname, 'ns_') !== false && $extname != 'ns_license' && $extname != 'ns_basetheme') {
            $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            $this->nsLicenseModule = $this->objectManager->get(NsLicenseModuleController::class);
            $this->nsLicenseModule->connectToServer($extname);
        }
    }
}
