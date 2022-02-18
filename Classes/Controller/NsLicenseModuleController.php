<?php

namespace NITSAN\NsLicense\Controller;

use NITSAN\NsLicense\Domain\Repository\NsLicenseRepository;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException;
use TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService;
use TYPO3\CMS\Extensionmanager\Utility\FileHandlingUtility;

/***
 *
 * This file is part of the "[NITSAN] NS Bas" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020
 *
 ***/

/**
 * NsLicenseModuleController.
 */
class NsLicenseModuleController extends ActionController
{
    /**
     * NsLicenseRepository.
     *
     * @var NsLicenseRepository
     */
    protected $nsLicenseRepository = null;

    protected $contentObject = null;

    protected $siteRoot = null;

    protected $isComposerMode = false;

    protected $composerSiteRoot = false;

    protected $installUtility = null;

    public function injectNsLicenseRepository(NsLicenseRepository $nsLicenseRepository)
    {
        $this->nsLicenseRepository = $nsLicenseRepository;
    }

    /**
     * Initializes this object.
     *
     * @return void
     */
    public function initializeObject()
    {
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->contentObject = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer');
        $this->installUtility = GeneralUtility::makeInstance(\TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class);
        $this->managementService = GeneralUtility::makeInstance(ExtensionManagementService::class);
        $this->fileHandlingUtility = GeneralUtility::makeInstance(FileHandlingUtility::class);
    }

    /**
     * Initialize Action.
     *
     * @return void
     */
    public function initializeAction()
    {
        parent::initializeAction();
        $this->packageManager = GeneralUtility::makeInstance(PackageManager::class);
        $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        if (version_compare(TYPO3_branch, '9.0', '>')) {
            $this->siteRoot = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/';
            $this->composerSiteRoot = \TYPO3\CMS\Core\Core\Environment::getProjectPath() . '/';
            $this->isComposerMode = Environment::isComposerMode();
        } else {
            $this->siteRoot = PATH_site;
            $this->isComposerMode = \TYPO3\CMS\Core\Core\Bootstrap::usesComposerClassLoading();
            if ($this->isComposerMode) {
                $commonEnd = explode('/', GeneralUtility::getIndpEnv('TYPO3_DOCUMENT_ROOT'));
                unset($commonEnd[count($commonEnd) - 1]);
                $this->composerSiteRoot = implode('/', $commonEnd) . '/';
            }
        }
    }

    /**
     * action list.
     *
     * @return void
     */
    public function listAction()
    {
        $extensions = $this->nsLicenseRepository->fetchData();
        foreach ($extensions as $key => $extension) {
            if ($extension['is_life_time'] != 1) {
                $extensions[$key]['days'] = (int) floor((($extension['expiration_date'] - time()) + 86400) / 86400);
            }
        }
        
        if (version_compare(TYPO3_branch, '11', '>=')) {
            $this->view->assign('modalAttr','data-bs-');
        } else {
            $this->view->assign('modalAttr','data-');
        }

        $this->view->assign('extensions', $extensions);
    }

    /**
     * action list.
     *
     * @return void
     */
    public function checkUpdateAction()
    {
        $params = $this->request->getArguments();
        if (isset($params['extKey'])) {
            $this->connectToServer($params['extKey'], 1);
            $extData = $this->nsLicenseRepository->fetchData($params['extKey']);
            if (version_compare($extData[0]['version'], $extData[0]['lts_version'], '==')) {
                $message = LocalizationUtility::translate('license.key.up_to_date', 'NsLicense');
            } else {
                $message = LocalizationUtility::translate('license.key.update', 'NsLicense');
            }
            $this->addFlashMessage($message, $params['extKey'], \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
        }
        $this->redirect('list');
    }

    /**
     * action list.
     *
     * @return void
     */
    public function connectToServer($extKey = null, $reload = 0)
    {
        $this->initializeAction();
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        if (!isset($_COOKIE['serverConnectionTime']) || $reload) {
            setcookie('serverConnectionTime', 1, time() + 60 * 60 * 24 * 14);
            $nsLicenseRepository = $this->objectManager->get(\NITSAN\NsLicense\Domain\Repository\NsLicenseRepository::class);
            if ($extKey) {
                $extData = $nsLicenseRepository->fetchData($extKey);
                if (!empty($extData)) {
                    $licenseData = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $extData[0]['license_key']);
                    if ($licenseData->status) {
                        $nsLicenseRepository->updateData($licenseData);

                        return true;
                    } elseif (!$licenseData->status) {
                        $disableExtensions[] = $extKey;
                        $extFolder = $this->siteRoot . '/typo3conf/ext/' . $extKey . '/';
                        $this->updateFiles($extFolder, $extKey);

                        return false;
                    }
                } else {
                    $licenseData = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_key=' . $extKey);
                    if ($licenseData->status) {
                        return false;
                    } else {
                        return true;
                    }
                }
            } else {
                $activePackages = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Package\PackageManager::class)->getActivePackages();
                $allExtensions = [];
                foreach ($activePackages as $key => $value) {
                    $exp_key = explode('_theme', $key);
                    if ($exp_key[0] == 'ns') {
                        if ($key != 'ns_basetheme' && $key != 'ns_license') {
                            $allExtensions[] = $key;
                        }
                    }
                }
                if (count($allExtensions) > 0) {
                    foreach ($allExtensions as $extension) {
                        $extData = $nsLicenseRepository->fetchData($extension);
                        if (empty($extData)) {
                            $licenseData = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_key=' . $extension);
                            if ($licenseData->status && !is_null($licenseData)) {
                                $disableExtensions[] = $extension;
                                $extFolder = $this->siteRoot . '/typo3conf/ext/' . $extension . '/';
                                $this->updateFiles($extFolder, $extension);
                            }
                        } else {
                            $licenseData = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $extData[0]['license_key']);
                            if (!is_null($licenseData)) {
                                if ($licenseData->status) {
                                    $nsLicenseRepository->updateData($licenseData);
                                } elseif (!$licenseData->status) {
                                    $disableExtensions[] = $extension;
                                    $extFolder = $this->siteRoot . '/typo3conf/ext/' . $extension . '/';
                                    $this->updateFiles($extFolder, $extension);
                                }
                            }
                        }
                    }
                    if ($disableExtensions != '') {
                        $disableExtensions = implode(',', $disableExtensions);
                        setcookie('NsLicense', $disableExtensions, time() + 3600, '/', '', 0);
                    } else {
                        setcookie('NsLicense', '', time() + 3600, '/', '', 0);
                    }
                }
            }
        } else {
            return true;
        }
    }

    /**
     * updateFiles.
     *
     * @return void
     */
    public function updateFiles($extFolder, $extension)
    {
        if (file_exists($extFolder . 'ext_tables.php')) {
            rename($extFolder . 'ext_tables.php', $extFolder . 'ext_tables..php');
        }
        if (file_exists($extFolder . 'Configuration/TCA/Overrides/sys_template.php')) {
            rename($extFolder . 'Configuration/TCA/Overrides/sys_template.php', $extFolder . 'Configuration/TCA/Overrides/sys_template..php');
        }
        if (file_exists($extFolder . 'Configuration')) {
            rename($extFolder . 'Configuration', $extFolder . 'Configuration.');
        }
        if (file_exists($extFolder . 'Resources')) {
            rename($extFolder . 'Resources', $extFolder . 'Resources.');
        }
        try {
            $this->unloadExtension($extension);
        } catch (\Exception $e) {
            $this->addFlashMessage($e->getMessage(), $extension, \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
        }
    }

    /**
     * updateRepairFiles.
     *
     * @return void
     */
    public function updateRepairFiles($extFolder, $extension)
    {
        if (file_exists($extFolder . 'ext_tables..php')) {
            rename($extFolder . 'ext_tables..php', $extFolder . 'ext_tables.php');
        }
        if (file_exists($extFolder . 'Configuration./TCA/Overrides/sys_template..php')) {
            rename($extFolder . 'Configuration./TCA/Overrides/sys_template..php', $extFolder . 'Configuration/TCA/Overrides/sys_template.php');
        }
        if (file_exists($extFolder . 'Configuration.')) {
            rename($extFolder . 'Configuration.', $extFolder . 'Configuration');
        }
        if (file_exists($extFolder . 'Resources.')) {
            rename($extFolder . 'Resources.', $extFolder . 'Resources');
        }
        try {
            $this->loadExtension($extension);
        } catch (\Exception $e) {
            $this->addFlashMessage($e->getMessage(), $extension, \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
        }
    }

    /**
     * Wrapper function for unloading extensions.
     *
     * @param string $extensionKey
     */
    protected function unloadExtension($extensionKey)
    {
        $this->packageManager->deactivatePackage($extensionKey);
        $this->cacheManager->flushCachesInGroup('system');
    }

     /**
     * Wrapper function for loading extensions.
     *
     * @param string $extensionKey
     */
    protected function loadExtension($extensionKey)
    {
        $this->packageManager->activatePackage($extensionKey);
        $this->cacheManager->flushCachesInGroup('system');
    }

    /**
     * action list.
     *
     * @return void
     */
    public function updateAction()
    {
        $params = $this->request->getArguments();
        $extKey = $params['extension']['extension_key'];
        if (isset($params['extension']['license_key']) && $params['extension']['license_key'] != '') {
            $updateStatus = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['extension']['license_key'] . '&ns_updates=1');
            if (!is_null($updateStatus) && !$updateStatus->status) {
                $this->addFlashMessage(LocalizationUtility::translate('errorMessage.license_expired', 'NsLicense'), 'Your annual License key is expired', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                $this->redirect('list');
            }

            $souceFolder = $this->siteRoot . 'typo3conf/ext/' . $extKey;
            if (is_dir($souceFolder)) {
                $uploadFolder = $this->siteRoot . 'uploads/ns_license/' . $extKey . '/' . $params['extension']['version'];
                try {
                    GeneralUtility::rmdir($uploadFolder, true);
                    GeneralUtility::mkdir_deep($uploadFolder);
                    GeneralUtility::copyDirectory($souceFolder, $uploadFolder);
                } catch (\Exception $e) {
                    $this->addFlashMessage($e->getMessage(), 'Extension not updated', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                    $this->redirect('list');
                }
            }
            $params['extension']['license'] = $params['extension']['license_key'];
            $params['extension']['overwrite'] = true;
            $params['extension']['isUpdateAction'] = true;
            $this->downloadExtension($params['extension'], 'fromUpdate');
        } else {
            $this->addFlashMessage(LocalizationUtility::translate('errorMessage.license_not_entered', 'NsLicense'), 'ERROR', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
        }
        $this->redirect('list');
    }

    /**
     * action activation.
     *
     * @return void
     */
    public function activationAction()
    {
        $params = $this->request->getArguments();
        if (isset($params['license']) && $params['license'] != '') {
            $params['activation'] = true;
            $this->downloadExtension($params);
        } else {
            $this->addFlashMessage(LocalizationUtility::translate('errorMessage.license_not_entered', 'NsLicense'), 'ERROR', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
        }
        // return true;
        $this->redirect('list');
    }

    /**
     * action activation.
     *
     * @return void
     */
    public function DeactivationAction()
    {
        $params = $this->request->getArguments();
        $licenseData = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['extension']['license_key'] . '&deactivate=1');
        $this->nsLicenseRepository->deactivate($params['extension']['license_key'], $params['extension']['extension_key']);
        $extFolder = $this->siteRoot . '/typo3conf/ext/' . $params['extension']['extension_key'] . '/';
        $this->updateFiles($extFolder, $params['extension']['extension_key']);
        $this->addFlashMessage(LocalizationUtility::translate('license-activation.deactivation', 'NsLicense'), 'EXT: ' . $params['extension']['extension_key'], \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
        $this->redirect('list');
    }

    /**
     * action activation.
     *
     * @param array $params
     *
     * @return void
     */
    public function downloadExtension($params = null, $fromWhere = null)
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        if (isset($params['license']) && $params['license'] != '') {
            if ($params['activation']) {
                $licenseData = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['license'] . '&activation=1');
            } else {
                $licenseData = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['license']);
            }
            if ($params['extension']['isUpdateAction'] && !$licenseData->isUpdatable) {
                $this->addFlashMessage(LocalizationUtility::translate('errorMessage.license_expired', 'NsLicense'), 'Your annual License key is expired', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                $this->redirect('list');
            }
            if ($licenseData->status) {
                if ($_COOKIE['NsLicense'] != '') {
                    $disableExtensions = explode(',', $_COOKIE['NsLicense']);
                    $key = array_search($licenseData->extension_key, $disableExtensions);
                    if ($key) {
                        unset($disableExtensions[$key]);
                        $disableExtensions = implode(',', $disableExtensions);
                        setcookie('NsLicense', $disableExtensions, time() + 3600, '/', '', 0);
                    }
                }
                if ($licenseData->existing) {
                    $extVersion = GeneralUtility::makeInstance(PackageManager::class)->getPackage($licenseData->extension_key)->getPackageMetaData()->getVersion();
                    $this->nsLicenseRepository->insertNewData($licenseData, $extVersion);
                    $this->addFlashMessage('EXT:' . $licenseData->extension_key . LocalizationUtility::translate('license-activation.activated', 'NsLicense'), 'EXT:' . $licenseData->extension_key, \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
                    $this->redirect('list');
                }
                $isAvailable = $this->nsLicenseRepository->fetchData($licenseData->extension_key);

                if ($isAvailable && $params['overwrite'] == 1) {
                    $ltsext = end($licenseData->extension_download_url);
                    $extKey = $licenseData->extension_key . '.zip';
                    $extKeyPath = $this->siteRoot . 'typo3temp/' . $extKey;
                    $this->downloadZipFile($ltsext, $licenseData->license_key, $extKeyPath, $licenseData->user_name);
                    try {
                        if ($this->isComposerMode) {
                            $zipService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Service\Archive\ZipService::class);
                            $extensionDir = $this->composerSiteRoot . 'extensions/' . $licenseData->extension_key;
                            if ($zipService->verify($extKeyPath)) {
                                if (!is_dir($extensionDir)) {
                                    GeneralUtility::mkdir_deep($extensionDir);
                                } else {
                                    GeneralUtility::rmdir($extensionDir, true);
                                    GeneralUtility::mkdir_deep($extensionDir);
                                }
                                $zipService->extract($extKeyPath, $extensionDir);
                            }
                        } else {
                            if (version_compare(TYPO3_branch, '11.0', '>')) {
                                $extKey = str_replace('.zip', '', $extKey);
                                $this->extractExtensionFromZipFile($extKeyPath, $extKey, ($params['overwrite'] ? true : false));
                            } else {
                                $this->uploadExtension = $objectManager->get(\TYPO3\CMS\Extensionmanager\Controller\UploadExtensionFileController::class);
                                $this->uploadExtension->extractExtensionFromFile($extKeyPath, $extKey, ($params['overwrite'] ? true : false));
                            }
                        }
                        unlink($extKeyPath);
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Unable to open zip') !== false) {
                            $this->addFlashMessage(LocalizationUtility::translate('errorMessage.error4', 'NsLicense'), $licenseData->extension_key, \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                        } else {
                            $this->addFlashMessage(LocalizationUtility::translate('license-activation.overwrite_message', 'NsLicense'), $licenseData->extension_key, \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                        }
                        $this->redirect('list');
                    }
                    $this->nsLicenseRepository->updateData($licenseData, 1);
                    
                } elseif (!$isAvailable) {
                    
                    // OPTION 1. Repairing > Let's just repair, If the product already there in typo3conf/ext + needs repair
                    if ($this->isComposerMode) {
                        $extFolder = $this->composerSiteRoot . 'extensions/' . $licenseData->extension_key . '/';
                    }
                    else {
                        $extFolder = $this->siteRoot . '/typo3conf/ext/' . $licenseData->extension_key . '/';
                    }
                    if (
                        (file_exists($extFolder . 'ext_tables..php')) ||  
                        (file_exists($extFolder . 'Configuration./TCA/Overrides/sys_template..php')) ||
                        (file_exists($extFolder . 'Configuration.')) || 
                        (file_exists($extFolder . 'Resources.'))
                        ) {
                        $isRepair = 'Yes';
                        $this->updateRepairFiles($extFolder, $licenseData->extension_key);
                    }

                    // OPTION 2. Overriding > Else let's continue to download extension
                    else {
                        $ltsext = end($licenseData->extension_download_url);
                        $extKey = $licenseData->extension_key . '.zip';
                        $extKeyPath = $this->siteRoot . 'typo3temp/' . $extKey;
                        $this->downloadZipFile($ltsext, $licenseData->license_key, $extKeyPath, $licenseData->user_name);
                        try {
                            if ($this->isComposerMode) {
                                $zipService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Service\Archive\ZipService::class);
                                $extensionDir = $this->composerSiteRoot . 'extensions/' . $licenseData->extension_key;
                                if ($zipService->verify($extKeyPath)) {
                                    if (!is_dir($extensionDir)) {
                                        GeneralUtility::mkdir_deep($extensionDir);
                                    } else {
                                        GeneralUtility::rmdir($extensionDir, true);
                                        GeneralUtility::mkdir_deep($extensionDir);
                                    }
                                    $zipService->extract($extKeyPath, $extensionDir);
                                }
                            } else {
                                if (version_compare(TYPO3_branch, '11.0', '>')) {
                                    $extKey = str_replace('.zip', '', $extKey);
                                    $this->extractExtensionFromZipFile($extKeyPath, $extKey, ($params['overwrite'] ? true : false));
                                } else {
                                    $this->uploadExtension = $objectManager->get(\TYPO3\CMS\Extensionmanager\Controller\UploadExtensionFileController::class);
                                    $this->uploadExtension->extractExtensionFromFile($extKeyPath, $extKey, ($params['overwrite'] ? true : false));
                                }
                            }
                            unlink($extKeyPath);
                        } catch (\Exception $e) {
                            if (strpos($e->getMessage(), 'Unable to open zip') !== false) {
                                $this->addFlashMessage(LocalizationUtility::translate('errorMessage.error4', 'NsLicense'), $licenseData->extension_key, \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                            } else {
                                $this->addFlashMessage(LocalizationUtility::translate('license-activation.overwrite_message', 'NsLicense'), $licenseData->extension_key, \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                            }
                            $this->redirect('list');
                        }
                    }
                    $this->nsLicenseRepository->insertNewData($licenseData);
                } else {
                    $this->addFlashMessage(LocalizationUtility::translate('license-activation.overwrite_message', 'NsLicense'), 'EXT:' . $licenseData->extension_key, \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                    $this->redirect('list');
                }

                // Is it from Update version?
                if($fromWhere == 'fromUpdate') {
                    $this->addFlashMessage(LocalizationUtility::translate('license-activation.downloaded_successfully_from_update', 'NsLicense'), 'EXT:' . $licenseData->extension_key, \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
                }

                // Seems from New license key registration
                else {
                    if($isRepair == 'Yes') {
                        $this->addFlashMessage(LocalizationUtility::translate('license-activation.extension_repair', 'NsLicense'), 'EXT:' . $licenseData->extension_key, \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
                    }
                    else {
                        $this->addFlashMessage(LocalizationUtility::translate('license-activation.downloaded_successfully', 'NsLicense'), 'EXT:' . $licenseData->extension_key, \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
                    }
                }
                if ($params['extension_key'] == 'ns_revolution_slider') {
                    $rsInstallUtility = GeneralUtility::makeInstance(\NITSAN\NsRevolutionSlider\Slots\InstallUtility::class);
                    $rsInstallUtility->schemaUpdate();
                    $pluginsFolder = $this->siteRoot . 'uploads/ns_license/ns_revolution_slider/' . $params['version'] . '/vendor/wp/wp-content/plugins/';
                    $mainPluginsUploadFolder = $this->siteRoot . 'typo3conf/ext/ns_revolution_slider/vendor/wp/wp-content/plugins/';
                    $folders = GeneralUtility::get_dirs($pluginsFolder);
                    if ($folders) {
                        try {
                            foreach ($folders as $folder) {
                                if ($folder !== 'revslider') {
                                    $pluginsSouceFolder = $pluginsFolder . $folder . '/';
                                    $pluginsUploadFolder = $mainPluginsUploadFolder . $folder . '/';

                                    GeneralUtility::rmdir($pluginsUploadFolder, true);
                                    GeneralUtility::mkdir_deep($pluginsUploadFolder);
                                    GeneralUtility::copyDirectory($pluginsSouceFolder, $pluginsUploadFolder);
                                }
                            }
                        } catch (\Exception $e) {
                            $this->addFlashMessage($e->getMessage(), 'Extension not updated', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                            $this->redirect('list');
                        }
                    }

                    $revsliderSouceFolder = $this->siteRoot . 'uploads/ns_license/ns_revolution_slider/' . $params['version'] . '/vendor/wp/wp-content/uploads/';
                    $revsliderUploadFolder = $this->siteRoot . 'typo3conf/ext/ns_revolution_slider/vendor/wp/wp-content/uploads/';
                    try {
                        GeneralUtility::rmdir($revsliderUploadFolder, true);
                        GeneralUtility::mkdir_deep($revsliderUploadFolder);
                        GeneralUtility::copyDirectory($revsliderSouceFolder, $revsliderUploadFolder);
                    } catch (\Exception $e) {
                        $this->addFlashMessage($e->getMessage(), 'Extension not updated', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                        $this->redirect('list');
                    }
                }

                // Successfully redirect to license listing
                $this->redirect('list');
            } else {
                $title = 'ERROR';
                if ($licenseData->extKey) {
                    $title = $licenseData->extKey;
                }
                $message = LocalizationUtility::translate('errorMessage.default', 'NsLicense');
                if ($licenseData->error_code) {
                    $message = LocalizationUtility::translate('errorMessage.' . $licenseData->error_code, 'NsLicense', [$licenseData->license_type]);
                }
                $this->addFlashMessage($message, $title, \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                $this->redirect('list');
            }
        }
        // Successfully redirect to license listing
        $this->addFlashMessage(LocalizationUtility::translate('errorMessage.default', 'NsLicense'), $params['license'], \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
        $this->redirect('list');
    }

    /**
     * Extracts a given zip file and installs the extension.
     *
     * @param string $uploadedFile Path to uploaded file
     * @param bool   $overwrite    Overwrite existing extension if TRUE
     *
     * @throws ExtensionManagerException
     */
    protected function extractExtensionFromZipFile(string $uploadedFile, string $extensionKey, bool $overwrite = false): string
    {
        $isExtensionAvailable = $this->managementService->isAvailable($extensionKey);
        if (!$overwrite && $isExtensionAvailable) {
            throw new ExtensionManagerException('Extension is already available and overwriting is disabled.', 1342864311);
        }
        if ($isExtensionAvailable) {
            $this->copyExtensionFolderToTempFolder($extensionKey);
        }
        $this->removeFromOriginalPath = true;
        $this->fileHandlingUtility->unzipExtensionFromFile($uploadedFile, $extensionKey);

        return $extensionKey;
    }

    /**
     * Copies current extension folder to typo3temp directory as backup.
     *
     * @param string $extensionKey
     *
     * @throws \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException
     */
    protected function copyExtensionFolderToTempFolder($extensionKey)
    {
        $this->extensionBackupPath = Environment::getVarPath() . '/transient/' . $extensionKey . substr(sha1($extensionKey . microtime()), 0, 7) . '/';
        GeneralUtility::mkdir($this->extensionBackupPath);
        GeneralUtility::copyDirectory(
            $this->fileHandlingUtility->getExtensionDir($extensionKey),
            $this->extensionBackupPath
        );
    }

    /**
     * fetchLicense.
     *
     * @param string $license
     *
     * @return array|null
     **/
    public function fetchLicense($license)
    {
        $url = 'https://composer.t3terminal.com/API/GetComposerDetails.php?' . $license;
        $curl = curl_init();
        curl_setopt_array($curl, [
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
        ]);
        $response = curl_exec($curl);
        if (!$response) {
            echo 'Error :- ' . curl_error($curl);
        }
        curl_close($curl);

        return json_decode($response);
    }

    /**
     * downloadZipFile.
     *
     * @param string $extensionDownloadUrl
     * @param string $license
     * @param string $extKeyPath
     * @param string $userName
     *
     * @return void
     */
    public function downloadZipFile($extensionDownloadUrl, $license, $extKeyPath, $userName)
    {
        $authorization = 'Basic ' . base64_encode($userName . ':' . $license);
        $curl = curl_init();
        curl_setopt_array($curl, [
          CURLOPT_URL => $extensionDownloadUrl,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authorization,
          ],
        ]);
        $response = curl_exec($curl);
        if (!$response) {
            echo 'Error :- ' . curl_error($curl);
        }
        curl_close($curl);
        file_put_contents($extKeyPath, $response);
    }
}
