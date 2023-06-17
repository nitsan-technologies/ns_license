<?php

namespace NITSAN\NsLicense;
/**
 * This Class called when any extension enable or Activated from
 * the Extension Manager.
 */
use NITSAN\NsLicense\Controller\NsLicenseModuleController;

/**
 * Setup
 */
class Setup
{
    public function __construct(
        protected readonly NsLicenseModuleController $nsLicenseModule
    ) {
    }

    public function executeOnSignal($extname = null)
    {
        if (is_object($extname)) {
            $extname = array_key_first($extname->getPackageKeys());
        }
        if (str_contains($extname, 'ns_theme_')   && $extname != 'ns_license' && $extname != 'ns_basetheme') {
            $this->nsLicenseModule->connectToServer($extname, 1);
        }
    }
}
