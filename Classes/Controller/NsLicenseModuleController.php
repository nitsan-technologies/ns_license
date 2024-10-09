<?php

namespace NITSAN\NsLicense\Controller;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Cache\CacheManager;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extensionmanager\Utility\InstallUtility;
use NITSAN\NsLicense\Domain\Repository\NsLicenseRepository;
use TYPO3\CMS\Extensionmanager\Utility\FileHandlingUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService;
use TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException;
use NITSAN\NsLicense\Service\LicenseService;

/***
 *
 * This file is part of the "[NITSAN] NS License" Extension for TYPO3 CMS.
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
    protected $siteRoot;

    protected $isComposerMode = false;

    protected $composerSiteRoot = false;

    /**
     * @var int
     */
    protected int $typo3Version = 0;
    /**
     * @var mixed|object|\Psr\Log\LoggerAwareInterface|PackageManager|(PackageManager&\Psr\Log\LoggerAwareInterface)|(PackageManager&\TYPO3\CMS\Core\SingletonInterface)|\TYPO3\CMS\Core\SingletonInterface|null
     */
    private mixed $packageManager;
    /**
     * @var mixed|object|\Psr\Log\LoggerAwareInterface|CacheManager|(CacheManager&\Psr\Log\LoggerAwareInterface)|(CacheManager&\TYPO3\CMS\Core\SingletonInterface)|\TYPO3\CMS\Core\SingletonInterface|null
     */
    private mixed $cacheManager;

    protected string $extensionBackupPath;

    /**
     * @param ModuleTemplateFactory $moduleTemplateFactory
     * @param IconFactory $iconFactory
     * @param PageRenderer $pageRenderer
     * @param RequestFactory $requestFactory
     * @param FileHandlingUtility $fileHandlingUtility
     * @param ExtensionManagementService $managementService
     * @param InstallUtility $installUtility
     * @param ContentObjectRenderer $contentObject
     */
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly IconFactory $iconFactory,
        protected readonly PageRenderer $pageRenderer,
        protected readonly RequestFactory $requestFactory,
        protected readonly FileHandlingUtility $fileHandlingUtility,
        protected readonly ExtensionManagementService $managementService,
        protected readonly InstallUtility $installUtility,
        protected readonly ContentObjectRenderer $contentObject,
        protected readonly NsLicenseRepository $nsLicenseRepository,
        protected readonly LicenseService $licenseService,
    ) {
    }

    /**
     * Initialize Action.
     */
    public function initializeAction() : void
    {
        // Call from Default ActionController
        parent::initializeAction();

        // Initial common properties
        // @extensionScannerIgnoreLine
        $this->packageManager = GeneralUtility::makeInstance(PackageManager::class);
        $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);

        $this->siteRoot = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/';
        $this->composerSiteRoot = \TYPO3\CMS\Core\Core\Environment::getProjectPath() . '/';
        $this->isComposerMode = Environment::isComposerMode();

        //TYPO3 version
        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        $this->typo3Version = $versionInformation->getMajorVersion();
        // $this->typo3Version = 12;

        // Compulsory add "/" at the end
        $this->siteRoot = rtrim($this->siteRoot, '/') . '/';
    }

    /**
     * action list.
     */
    public function listAction(): ResponseInterface
    {
        // Let's flush all the cache to change the version number
        $this->cacheManager->flushCaches();

        $extensions = $this->nsLicenseRepository->fetchData();
        foreach ($extensions as $key => $extension) {
            if ($extension['is_life_time'] != 1) {
                $extensions[$key]['days'] = (int)floor((($extension['expiration_date'] - time()) + 86400) / 86400);
            }
            if (!empty($extension['domains'])) {
                $extensions[$key]['domains'] = str_replace(',', ' | ', $extensions[$key]['domains']);
            }

            // Get latest version from extension ext_emconf.php
            $extensions[$key]['version'] = $this->getVersionFromEmconf($extensions[$key]['extension_key']);
            if (version_compare($extensions[$key]['lts_version'], $extensions[$key]['version'], '>')) {
                $extensions[$key]['isUpdateAvail'] = true;
            }
            // Check if required repair
            $extFolder = $this->licenseService->getExtensionFolder($extensions[$key]['extension_key']);
            $extensions[$key]['isRepareRequired'] = $this->checkRepairFiles($extFolder, $extensions[$key]['extension_key']);
        }
        $view = $this->initializeModuleTemplate($this->request);
        $showUpdateButton = '1';
        if ($this->isComposerMode) {
            $showUpdateButton = '0';
        }
        $view->assign('showUpdateButton', $showUpdateButton);
        $view->assign('extensions', $extensions);
        return $view->renderResponse('NsLicenseModule/List');
    }

    public function connectToServer($extKey = null, $reload = 0, $checkType = '')
    {
        $this->licenseService->connectToServer($extKey, $reload, $checkType);
    }

    /**
     * action list.
     */
    public function checkUpdateAction(): ResponseInterface
    {
        $params = $this->request->getArguments();
        if (isset($params['extKey'])) {
            // Let's flush all the cache to change the version number
            $this->cacheManager->flushCaches();

            // Try to validate license key with system
            $this->licenseService->connectToServer($params['extKey'], 1);

            // Fetch latest LTS version from APIs
            $extData = $this->nsLicenseRepository->fetchData($params['extKey']);

            // Finally compare version with ext_emconf + latest available version
            $versionId = $this->getVersionFromEmconf($params['extKey']);
            if (version_compare($versionId, $extData[0]['lts_version'], '==')) {
                $severity = ContextualFeedbackSeverity::OK;
                $message = LocalizationUtility::translate('license.key.up_to_date', 'NsLicense');
            } else {
                $message = LocalizationUtility::translate('license.key.update', 'NsLicense');
                $severity = ContextualFeedbackSeverity::OK;
                if ($this->isComposerMode) {
                    $message = LocalizationUtility::translate('license.key.update.composer', 'NsLicense');
                    $severity = ContextualFeedbackSeverity::INFO;
                }
                
            }
            $this->addFlashMessage($message, $params['extKey'], $severity);
        }
        return $this->redirect('list');
    }


    /**
     * checkRepairFiles.
     */
    public function checkRepairFiles($extFolder, $extension)
    {
        $isRepair = false;
        if (file_exists($extFolder . 'ext_tables..php')) {
            $isRepair = true;
        }
        if (file_exists($extFolder . 'Configuration./TCA/Overrides/sys_template..php')) {
            $isRepair = true;
        }
        if (file_exists($extFolder . 'Configuration.')) {
            $isRepair = true;
        }
        if (file_exists($extFolder . 'Resources.')) {
            $isRepair = true;
        }

        return $isRepair;
    }

    /**
     * action list.
     */
    public function updateAction(): ResponseInterface
    {
        $params = $this->request->getArguments();
        $extKey = $params['extension']['extension_key'];
        if (isset($params['extension']['license_key']) && $params['extension']['license_key'] != '') {
            $updateStatus = $this->licenseService->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['extension']['license_key'] . '&ns_updates=1&typo3_version=' . $this->typo3Version);
            if (isset($params['action'])) {
                return $this->redirect('list');
            }
            if (!is_null($updateStatus) && !$updateStatus->status) {
                $this->addFlashMessage(LocalizationUtility::translate('errorMessage.license_expired', 'NsLicense'), 'Your annual License key is expired', ContextualFeedbackSeverity::ERROR);
                return $this->redirect('list');
            }

            // Let's take backup to /uploads/ns_license/
            $this->getBackupToUploadFolder($extKey);

            $params['extension']['license'] = $params['extension']['license_key'];
            $params['extension']['overwrite'] = true;
            $params['extension']['isUpdateAction'] = true;
            $this->downloadExtension($params['extension'], 'fromUpdate');
        } else {
            $this->addFlashMessage(LocalizationUtility::translate('errorMessage.license_not_entered', 'NsLicense'), 'ERROR', ContextualFeedbackSeverity::ERROR);
        }
        return $this->redirect('list');
    }

    /**
     * action activation.
     */
    /**
     * action activation.
     */
    public function activationAction(): ResponseInterface
    {
        $params = $this->request->getArguments();
        if (isset($params['license']) && $params['license'] != '') {
            $params['activation'] = true;
            $this->downloadExtension($params);
        } else {
            $this->addFlashMessage(LocalizationUtility::translate('errorMessage.license_not_entered', 'NsLicense'), 'ERROR', ContextualFeedbackSeverity::ERROR);
        }
        return $this->redirect('list');
    }

    /**
     * action deactivation.
     */
    public function deactivationAction(): ResponseInterface
    {
        $params = $this->request->getArguments();
        $licenseData = $this->licenseService->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['extension']['license_key'] . '&deactivate=1');
        $this->nsLicenseRepository->deactivate($params['extension']['license_key'], $params['extension']['extension_key']);
        $extFolder = $this->licenseService->getExtensionFolder($params['extension']['extension_key']);
        $this->licenseService->updateFiles($extFolder, $params['extension']['extension_key']);
        $this->addFlashMessage(LocalizationUtility::translate('license-activation.deactivation', 'NsLicense'), 'EXT:' . $params['extension']['extension_key'], ContextualFeedbackSeverity::OK);
        return $this->redirect('list');
    }

    /**
     * action reactivation.
     */
    public function ReactivationAction()
    {
        $params = $this->request->getArguments();
        $extFolder = $this->licenseService->getExtensionFolder($params['extension']['extension_key']);
        $this->licenseService->updateRepairFiles($extFolder, $params['extension']['extension_key']);
        $this->addFlashMessage(LocalizationUtility::translate('license-activation.reactivation', 'NsLicense'), 'EXT:' . $params['extension']['extension_key'], ContextualFeedbackSeverity::OK);
        return $this->redirect('list');
    }

    /**
     * action activation.
     *
     * @param array $params
     */
    public function downloadExtension($params = null, $fromWhere = null)
    {
        $isRepair = '';
        if (isset($params['license']) && $params['license'] != '') {
            if (isset($params['activation']) && $params['activation']) {
                $licenseData = $this->licenseService->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['license'] . '&activation=1&typo3_version=' . $this->typo3Version);
            } else {
                $licenseData = $this->licenseService->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['license'] . '&typo3_version=' . $this->typo3Version);
            }
            if (isset($params['extension'])) {
                if ($params['extension']['isUpdateAction'] && !$licenseData->isUpdatable) {
                    $this->addFlashMessage(LocalizationUtility::translate('errorMessage.license_expired', 'NsLicense'), 'Your annual License key is expired', ContextualFeedbackSeverity::ERROR);
                    return $this->redirect('list');
                }
            }
            if ($licenseData && $licenseData->status) {
                if (isset($_COOKIE['NsLicense']) && $_COOKIE['NsLicense'] != '') {
                    $disableExtensions = explode(',', $_COOKIE['NsLicense']);
                    $key = array_search($licenseData->extension_key, $disableExtensions);
                    if ($key) {
                        unset($disableExtensions[$key]);
                        $disableExtensions = implode(',', $disableExtensions);
                        setcookie('NsLicense', $disableExtensions, time() + 3600, '/', '', 0);
                    }
                }
                if (isset($licenseData->existing) && $licenseData->existing) {
                    $extVersion = GeneralUtility::makeInstance(PackageManager::class)->getPackage($licenseData->extension_key)->getPackageMetaData()->getVersion();
                    $this->nsLicenseRepository->insertNewData($licenseData, $extVersion);
                    $this->addFlashMessage('EXT:' . $licenseData->extension_key . LocalizationUtility::translate('license-activation.activated', 'NsLicense'), 'EXT:' . $licenseData->extension_key, ContextualFeedbackSeverity::OK);
                    return $this->redirect('list');
                }
                $isAvailable = $this->nsLicenseRepository->fetchData($licenseData->extension_key);
                if ($isAvailable && $params['overwrite'] == 1) {
                    $extensionDownloadUrl = $licenseData->extension_download_url;
                    if (PHP_VERSION > 8) {
                        if ($extensionDownloadUrl) {
                            $extensionDownloadUrl = get_mangled_object_vars($licenseData->extension_download_url);
                        }
                    }

                    $ltsext = end($extensionDownloadUrl);
                    $extKey = $licenseData->extension_key . '.zip';
                    $extKeyPath = $this->siteRoot . 'typo3temp/' . $extKey;
                    if (!$this->isComposerMode) {
                        $this->downloadZipFile($ltsext, $licenseData->license_key, $extKeyPath, $licenseData->user_name, $licenseData->extension_key);
                    }
                    try {
                        if (!$this->isComposerMode) {
                            $extKey = str_replace('.zip', '', $extKey);
                            $this->extractExtensionFromZipFile($extKeyPath, $extKey, ($params['overwrite'] ? true : false));
                            unlink($extKeyPath);
                        }

                        // Rename the static data dump file after update the extension for theme...
                        if (str_contains($licenseData->extension_key, 'ns_')   && $licenseData->extension_key != 'ns_license' && $licenseData->extension_key != 'ns_basetheme') {
                            if (str_contains($licenseData->extension_key, 'ns_theme_')) {
                                // Check SQL import file, and rename it
                                $extFolder = $this->licenseService->getExtensionFolder($licenseData->extension_key);
                                if (file_exists($extFolder . 'ext_tables_static+adt.sql')) {
                                    @rename($extFolder . 'ext_tables_static+adt.sql', $extFolder . 'ext_tables_static+adt..sql');
                                }
                            }
                        }

                        // Let's flush all the cache to change the version number
                        $this->cacheManager->flushCaches();
                    } catch (\Exception $e) {
                        if (str_contains($e->getMessage(), 'Unable to open zip')) {
                            $this->addFlashMessage(LocalizationUtility::translate('errorMessage.error4', 'NsLicense', [$licenseData->extension_key, $this->typo3Version]), $licenseData->extension_key, ContextualFeedbackSeverity::ERROR);
                        } else {
                            $this->addFlashMessage(LocalizationUtility::translate('license-activation.overwrite_message', 'NsLicense'), $licenseData->extension_key, ContextualFeedbackSeverity::ERROR);
                        }
                        return $this->redirect('list');
                    }
                    $this->nsLicenseRepository->updateData($licenseData, 1);
                } elseif (!$isAvailable) {
                    // OPTION 1. Repairing > Let's just repair, If the product already there in typo3conf/ext + needs repair
                    $extFolder = $this->licenseService->getExtensionFolder($licenseData->extension_key);

                    // Check if Update Repair
                    if ($this->licenseService->updateRepairFiles($extFolder, $licenseData->extension_key)) {
                        $isRepair = 'Yes';
                    }

                    // OPTION 2. Overriding > Else let's continue to download extension
                    else {
                        $extKeyPath = '';
                        if (!$this->isComposerMode) {
                            $extensionDownloadUrl = $licenseData->extension_download_url;
                            if (PHP_VERSION > 8) {
                                if ($extensionDownloadUrl) {
                                    $extensionDownloadUrl = get_mangled_object_vars($licenseData->extension_download_url);
                                }
                            }

                            $ltsext = end($extensionDownloadUrl);
                            $extKey = $licenseData->extension_key . '.zip';
                            $extKeyPath = $this->siteRoot . 'typo3temp/' . $extKey;
                            $this->downloadZipFile($ltsext, $licenseData->license_key, $extKeyPath, $licenseData->user_name, $licenseData->extension_key);
                        }

                        try {
                            if (!$this->isComposerMode) {
                                $extKey = str_replace('.zip', '', $extKey);
                                $this->extractExtensionFromZipFile($extKeyPath, $extKey, ($params['overwrite'] ? true : false));
                                unlink($extKeyPath);
                            }
                            // Let's flush all the cache to change the version number
                            $this->cacheManager->flushCaches();
                        } catch (\Exception $e) {
                            if (str_contains($e->getMessage(), 'Unable to open zip')) {
                                $this->addFlashMessage(LocalizationUtility::translate('errorMessage.error4', 'NsLicense', [$licenseData->extension_key, $this->typo3Version]), $licenseData->extension_key, ContextualFeedbackSeverity::ERROR);
                            } else {
                                $this->addFlashMessage(LocalizationUtility::translate('license-activation.overwrite_message', 'NsLicense'), $licenseData->extension_key, ContextualFeedbackSeverity::ERROR);
                            }
                            return $this->redirect('list');
                        }
                    }
                    if ($this->isComposerMode && empty($licenseData->extension_download_url)) {
                        $this->addFlashMessage(LocalizationUtility::translate('errorMessage.error4', 'NsLicense', [$licenseData->extension_key, $this->typo3Version]), $licenseData->extension_key, ContextualFeedbackSeverity::ERROR);
                        return $this->redirect('list');
                    }
                    $this->nsLicenseRepository->insertNewData($licenseData);
                } else {
                    $this->addFlashMessage(LocalizationUtility::translate('license-activation.overwrite_message', 'NsLicense'), 'EXT:' . $licenseData->extension_key, ContextualFeedbackSeverity::ERROR);
                    return $this->redirect('list');
                }

                // Is it from Update version?
                if ($fromWhere == 'fromUpdate') {
                    $this->addFlashMessage(LocalizationUtility::translate('license-activation.downloaded_successfully_from_update', 'NsLicense'), 'EXT:' . $licenseData->extension_key, ContextualFeedbackSeverity::OK);
                }

                // Seems from New license key registration
                else {
                    if ($isRepair == 'Yes') {
                        $this->addFlashMessage(LocalizationUtility::translate('license-activation.extension_repair', 'NsLicense'), 'EXT:' . $licenseData->extension_key, ContextualFeedbackSeverity::OK);
                    } else {
                        $this->addFlashMessage(LocalizationUtility::translate('license-activation.downloaded_successfully', 'NsLicense'), 'EXT:' . $licenseData->extension_key, ContextualFeedbackSeverity::OK);
                    }
                }

                // Special code for EXT.ns_revolution_slider
                if (isset($params['extension_key']) && $params['extension_key'] == 'ns_revolution_slider') {

                    $versionOriginalId = $params['version'];
                    $this->getVersionFromEmconf($params['extension_key']);

                    // Setup Plugin
                    $pluginsFolder = $this->siteRoot . 'uploads/ns_license/ns_revolution_slider/' . $versionOriginalId . '/vendor/wp/wp-content/plugins/';
                    $mainPluginsUploadFolder = $this->siteRoot . 'typo3conf/ext/ns_revolution_slider/Resources/Public/vendor/wp/wp-content/plugins/';
                    if (Environment::isComposerMode()) {
                        $mainPluginsUploadFolder = Environment::getProjectPath() . '/vendor/nitsan/ns-revolution-slider/Resources/Public/vendor/wp/wp-content/plugins/';
                    }

                    //Check if old structure is available while migrating the extension from <=11 to 12.x
                    if (file_exists($this->siteRoot . 'typo3conf/ext/ns_revolution_slider/vendor/')) {
                        $mainPluginsUploadFolder = $this->siteRoot . 'typo3conf/ext/ns_revolution_slider/vendor/wp/wp-content/plugins/';
                        if (Environment::isComposerMode()) {
                            $mainPluginsUploadFolder = Environment::getProjectPath() . '/vendor/nitsan/ns-revolution-slider/vendor/wp/wp-content/plugins/';
                        }
                    }

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
                            $this->addFlashMessage($e->getMessage(), 'Extension not updated', ContextualFeedbackSeverity::ERROR);
                            return $this->redirect('list');
                        }
                    }

                    // Setup Main Uploads
                    $revsliderSourceFolder = $this->siteRoot . 'uploads/ns_license/ns_revolution_slider/' . $versionOriginalId . '/vendor/wp/wp-content/uploads/';
                    $revsliderUploadFolder = $this->siteRoot . 'typo3conf/ext/ns_revolution_slider/Resource/Public/vendor/wp/wp-content/uploads/';
                    if (Environment::isComposerMode()) {
                        $revsliderUploadFolder = Environment::getProjectPath() . '/vendor/nitsan/ns-revolution-slider/Resources/Public/vendor/wp/wp-content/uploads/';
                    }

                    //Check if old structure is available while migrating the extension from <=11 to 12.x
                    if (file_exists($this->siteRoot . 'typo3conf/ext/ns_revolution_slider/vendor/')) {
                        $revsliderUploadFolder = $this->siteRoot . 'typo3conf/ext/ns_revolution_slider/vendor/wp/wp-content/uploads/';
                        if (Environment::isComposerMode()) {
                            $revsliderUploadFolder = Environment::getProjectPath() . '/vendor/nitsan/ns-revolution-slider/vendor/wp/wp-content/uploads/';
                        }
                    }
                    try {
                        GeneralUtility::rmdir($revsliderUploadFolder, true);
                        GeneralUtility::mkdir_deep($revsliderUploadFolder);
                        GeneralUtility::copyDirectory($revsliderSourceFolder, $revsliderUploadFolder);
                    } catch (\Exception $e) {
                        $this->addFlashMessage($e->getMessage(), 'Extension not updated', ContextualFeedbackSeverity::ERROR);
                        return $this->redirect('list');
                    }

                    // Update Path in Database (If Composer Mode)
                    if (Environment::isComposerMode()) {
                        $this->nsLicenseRepository->updateSchema();
                    }
                    //$rsInstallUtility = GeneralUtility::makeInstance(\NITSAN\NsRevolutionSlider\Slots\InstallUtility::class);
                    //$rsInstallUtility->schemaUpdate();
                }

                // Successfully redirect to license listing
                return $this->redirect('list');
            }
            $title = $licenseData->extKey ?? 'ERROR';
            $message = LocalizationUtility::translate('errorMessage.default', 'NsLicense');
            if ($licenseData->error_code) {
                $license_type = $licenseData->license_type ?? '';
                $message = LocalizationUtility::translate('errorMessage.' . $licenseData->error_code, 'NsLicense', [$license_type]);
            }
            $this->addFlashMessage($message, $title, ContextualFeedbackSeverity::ERROR);
            return $this->redirect('list');
        }

        // Successfully redirect to license listing
        return $this->redirect('list');
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
     * downloadZipFile.
     *
     * @param string $extensionDownloadUrl
     * @param string $license
     * @param string $extKeyPath
     * @param string $userName
     */
    public function downloadZipFile($extensionDownloadUrl, $license, $extKeyPath, $userName, $extKey)
    {
        $authorization = 'Basic ' . base64_encode($userName . ':' . $license);
        try {
            $response = $this->requestFactory->request(
                $extensionDownloadUrl,
                'POST',
                ['headers' => ['Authorization' => $authorization]]
            );

            $rawResponse = $response->getBody()->getContents();
            file_put_contents($extKeyPath, $rawResponse);

            // Let's take backup to /uploads/ns_license/
            $this->getBackupToUploadFolder($extKey);
        } catch (\Throwable $e) {
            $this->addFlashMessage($e->getMessage(), 'Your server has an issue connecting with our license system; Please get in touch with your server administrator with the below error message.', ContextualFeedbackSeverity::ERROR);
            // Let's only redirect if we are at TYPO3 backend module (ignore at Login)
            $params = $this->request->getArguments();
            if (isset($params['action'])) {
                return $this->redirect('list');
            }
        }
    }

    /**
     * getVersionFromEmconf.
     *
     * @param string $extKey
     */
    public function getVersionFromEmconf($extKey)
    {
        $versionId = '';
        // Let's grab mannualy the Version Id
        $extFolder = $this->licenseService->getExtensionFolder($extKey);
        if (is_file($extFolder . 'ext_emconf.php')) {
            include $extFolder . 'ext_emconf.php';
            $arrEmConf = (isset($EM_CONF[$extKey])) ? $EM_CONF[$extKey] : $EM_CONF[null];
            $versionId = $arrEmConf['version'];
        }
        return $versionId;
    }

    /**
     * getBackupToUploadFolder.
     *
     * @param string $extKey
     */
    public function getBackupToUploadFolder($extKey)
    {
        $souceFolder = $this->licenseService->getExtensionFolder($extKey);
        if (is_dir($souceFolder)) {
            $versionId = $this->getVersionFromEmconf($extKey);
            $uploadFolder = $this->siteRoot . 'uploads/ns_license/' . $extKey . '/' . $versionId . '/';
            try {
                GeneralUtility::rmdir($uploadFolder, true);
                GeneralUtility::mkdir_deep($uploadFolder);
                GeneralUtility::copyDirectory($souceFolder, $uploadFolder);
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Generates the action menu
     */
    protected function initializeModuleTemplate(
        ServerRequestInterface $request
    ): ModuleTemplate {
        return $this->moduleTemplateFactory->create($request);
    }
}
