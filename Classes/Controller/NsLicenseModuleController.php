<?php

namespace NITSAN\NsLicense\Controller;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Cache\CacheManager;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use NITSAN\NsLicense\Service\LicenseService;
use NITSAN\NsLicense\Service\ExtensionListService;
use NITSAN\NsLicense\Service\ExtensionArchiveService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Http\JsonResponse;
use NITSAN\NsLicense\Domain\Repository\NsLicenseRepository;
use TYPO3\CMS\Extensionmanager\Utility\FileHandlingUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService;
use TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException;

/***
 *
 * This file is part of the "[NITSAN] NS License" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2026
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

    protected int $typo3Version = 0;

    protected string $extensionBackupPath;
    
    /**
     * @var mixed|object|\Psr\Log\LoggerAwareInterface|CacheManager|(CacheManager&\Psr\Log\LoggerAwareInterface)|(CacheManager&\TYPO3\CMS\Core\SingletonInterface)|\TYPO3\CMS\Core\SingletonInterface|null
     */
    private mixed $cacheManager;


    /**
     * @param ModuleTemplateFactory $moduleTemplateFactory
     * @param RequestFactory $requestFactory
     * @param FileHandlingUtility $fileHandlingUtility
     * @param ExtensionManagementService $managementService
     * @param ContentObjectRenderer $contentObject
     */
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly RequestFactory $requestFactory,
        protected readonly FileHandlingUtility $fileHandlingUtility,
        protected readonly ExtensionManagementService $managementService,
        protected readonly ContentObjectRenderer $contentObject,
        protected readonly NsLicenseRepository $nsLicenseRepository,
        protected readonly LicenseService $licenseService,
        protected readonly ExtensionListService $extensionListService,
        protected readonly ExtensionArchiveService $extensionArchiveService,
        protected readonly DependencyOrderingService $dependencyOrderingService,
    ) {}

    /**
     * Initialize Action.
     */
    public function initializeAction(): void
    {
        // Call from Default ActionController
        parent::initializeAction();
        // Initial common properties
        // @extensionScannerIgnoreLine
        $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $this->siteRoot = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/';
        $this->isComposerMode = Environment::isComposerMode();
        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        $this->typo3Version = $versionInformation->getMajorVersion();
        $this->siteRoot = rtrim($this->siteRoot, '/') . '/';
    }


    public function listAction(): ResponseInterface
    {
        $extensions = $this->extensionListService->fetchExtensions();
        $view = $this->initializeModuleTemplate($this->request);
        $view->assign('activeTab', 'list');
        $view->assign('t3version', $this->typo3Version);
        if ($this->isComposerMode) {
            $view->assign('showUpdateButton', 1);
        }
        $view->assign('extensions', $extensions);
        return $view->renderResponse('NsLicenseModule/Index');
    }

    /**
     * Shop action - displays shop data
     * @return ResponseInterface
     */
    public function getShopDataAction(): ResponseInterface
    {
        $view = $this->initializeModuleTemplate($this->request);
        $shopData = $this->loadSyncData('shop');
        $view->assign('shopData', $shopData);
        return $view->renderResponse('NsLicenseModule/Shop');
    }

    /**
     * Services action - displays services data
     * @return ResponseInterface
     */
    public function getServicesDataAction(): ResponseInterface
    {
        $view = $this->initializeModuleTemplate($this->request);
        if ($this->isComposerMode) {
            $view->assign('showUpdateButton', 1);
        }
        $categories = $this->loadSyncData('services');
        $view->assign('servicesData', ['categories' => is_array($categories) ? $categories : []]);
        return $view->renderResponse('NsLicenseModule/Services');
    }

    /**
     * Load synchronized data from database
     * @param string $type 'shop', 'services', or 'extensions'
     * @return array
     */
    protected function loadSyncData(string $type): array
    {
        try {
            return $this->nsLicenseRepository->getSyncData($type);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function connectToServer($extKey = null, $reload = 0, $checkType = '')
    {
        $this->licenseService->connectToServer($extKey, $reload, $checkType);
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
            if (!isset($params['action'])) {
                return $this->redirect('list');
            }
           
            if (!is_null($updateStatus) && !$updateStatus['status']) {
                $this->addFlashMessage(LocalizationUtility::translate('errorMessage.license_expired', 'NsLicense'), 'Your annual License key is expired', ContextualFeedbackSeverity::ERROR);
                return $this->redirect('list');
            }
            // Let's take backup to /uploads/ns_license/
            $this->extensionArchiveService->getBackupToUploadFolder($extKey);
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
    protected function deactivationAction(): ResponseInterface
    {
        $params = $this->request->getArguments();
        $this->licenseService->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['extension']['license_key'] . '&deactivate=1');
        $this->nsLicenseRepository->deactivate($params['extension']['license_key'], $params['extension']['extension_key']);
        $extFolder = $this->extensionListService->getExtensionFolder($params['extension']['extension_key']);
        $this->licenseService->updateFiles($extFolder);
        $this->addFlashMessage(LocalizationUtility::translate('license-activation.deactivation', 'NsLicense'), 'EXT:' . $params['extension']['extension_key'], ContextualFeedbackSeverity::OK);
        return $this->redirect('list');
    }

    /**
     * action reactivation.
     */
    public function reactivationAction()
    {
        $params = $this->request->getArguments();
        $extFolder = $this->extensionListService->getExtensionFolder($params['extension']);
        $this->licenseService->updateRepairFiles($extFolder, $params['extension']);
        $this->addFlashMessage(LocalizationUtility::translate('license-activation.reactivation', 'NsLicense'), 'EXT:' . $params['extension'], ContextualFeedbackSeverity::OK);
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
                
            if (isset($params['extension']) && is_array($licenseData)) {
                if ($params['extension']['isUpdateAction'] && empty($licenseData['isUpdatable'])) {
                    $this->addFlashMessage(LocalizationUtility::translate('errorMessage.license_expired', 'NsLicense'), 'Your annual License key is expired', ContextualFeedbackSeverity::ERROR);
                    return $this->redirect('list');
                }
            }
            if (isset($params['action']) && is_array($licenseData)) {
                if ($params['action'] === 'activation' && isset($licenseData['isUpdatable']) && !$licenseData['isUpdatable']) {
                    $this->addFlashMessage(LocalizationUtility::translate('errorMessage.license_expired', 'NsLicense'), 'Your annual License key is expired', ContextualFeedbackSeverity::ERROR);
                    return $this->redirect('list');
                }
            }

            if (is_array($licenseData) && !empty($licenseData['status'])) {
                if (isset($_COOKIE['NsLicense']) && $_COOKIE['NsLicense'] != '') {
                    $disableExtensions = explode(',', $_COOKIE['NsLicense']);
                    $key = array_search($licenseData['extension_key'] ?? '', $disableExtensions);
                    if ($key) {
                        unset($disableExtensions[$key]);
                        $disableExtensions = implode(',', $disableExtensions);
                        setcookie('NsLicense', $disableExtensions, time() + 3600, '/', '', 0);
                    }
                }

                if (!empty($licenseData['existing'])) {
                    $extVersion = GeneralUtility::makeInstance(PackageManager::class, $this->dependencyOrderingService)->getPackage($licenseData['extension_key'])->getPackageMetaData()->getVersion();
                    $this->nsLicenseRepository->insertNewData(json_decode(json_encode($licenseData)), $extVersion);
                    $this->addFlashMessage('EXT:' . ($licenseData['extension_key'] ?? '') . LocalizationUtility::translate('license-activation.activated', 'NsLicense'), 'EXT:' . ($licenseData['extension_key'] ?? ''), ContextualFeedbackSeverity::OK);
                    return $this->redirect('list');
                }

                $isAvailable = $this->nsLicenseRepository->fetchData($licenseData['extension_key'] ?? '');
                if ($isAvailable && $params['overwrite'] == 1) {
                    $extensionDownloadUrl = $licenseData['extension_download_url'] ?? [];
                    if (!is_array($extensionDownloadUrl)) {
                        $extensionDownloadUrl = $extensionDownloadUrl ? (array)$extensionDownloadUrl : [];
                    }

                    $ltsext = end($extensionDownloadUrl);
                    $extKey = ($licenseData['extension_key'] ?? '') . '.zip';
                    $extKeyPath = $this->siteRoot . 'typo3temp/' . $extKey;
                    if (!$this->isComposerMode) {
                        $this->extensionArchiveService->downloadZipFile($ltsext, $licenseData['license_key'] ?? '', $extKeyPath, $licenseData['user_name'] ?? '', $licenseData['extension_key'] ?? '');
                    }
                    try {
                        if (!$this->isComposerMode) {
                            $extKey = str_replace('.zip', '', $extKey);
                            $this->extensionArchiveService->extractExtensionFromZipFile($extKeyPath, $extKey, ($params['overwrite'] ? true : false));
                            unlink($extKeyPath);
                        }

                        // Rename the static data dump file after update the extension for theme...
                        $extKeyVal = $licenseData['extension_key'] ?? '';
                        if (str_contains($extKeyVal, 'ns_') && $extKeyVal != 'ns_license' && $extKeyVal != 'ns_basetheme') {
                            if (str_contains($extKeyVal, 'ns_theme_')) {
                                // Check SQL import file, and rename it
                                $extFolder = $this->extensionListService->getExtensionFolder($extKeyVal);
                                if (file_exists($extFolder . 'ext_tables_static+adt.sql')) {
                                    @rename($extFolder . 'ext_tables_static+adt.sql', $extFolder . 'ext_tables_static+adt..sql');
                                }
                            }
                        }

                        // Let's flush all the cache to change the version number
                        $this->cacheManager->flushCaches();
                    } catch (\Exception $e) {
                        if (str_contains($e->getMessage(), 'Unable to open zip')) {
                            $this->addFlashMessage(LocalizationUtility::translate('errorMessage.error4', 'NsLicense', [$licenseData['extension_key'] ?? '', $this->typo3Version]), $licenseData['extension_key'] ?? '', ContextualFeedbackSeverity::ERROR);
                        } else {
                            $this->addFlashMessage(LocalizationUtility::translate('license-activation.overwrite_message', 'NsLicense'), $licenseData['extension_key'] ?? '', ContextualFeedbackSeverity::ERROR);
                        }
                        return $this->redirect('list');
                    }
                    $this->nsLicenseRepository->updateData(json_decode(json_encode($licenseData)), 1);
                } elseif (!$isAvailable) {
                    // OPTION 1. Repairing > Let's just repair, If the product already there in typo3conf/ext + needs repair
                    $extFolder = $this->extensionListService->getExtensionFolder($licenseData['extension_key'] ?? '');

                    // Check if Update Repair
                    if ($this->licenseService->updateRepairFiles($extFolder, $licenseData['extension_key'] ?? '')) {
                        $isRepair = 'Yes';
                    }

                    // OPTION 2. Overriding > Else let's continue to download extension
                    else {
                        $extKeyPath = '';
                        if (!$this->isComposerMode) {
                            $extensionDownloadUrl = $licenseData['extension_download_url'] ?? [];
                            if (!is_array($extensionDownloadUrl)) {
                                $extensionDownloadUrl = $extensionDownloadUrl ? (array)$extensionDownloadUrl : [];
                            }

                            $ltsext = end($extensionDownloadUrl);
                            $extKey = ($licenseData['extension_key'] ?? '') . '.zip';
                            $extKeyPath = $this->siteRoot . 'typo3temp/' . $extKey;
                            $this->extensionArchiveService->downloadZipFile($ltsext, $licenseData['license_key'] ?? '', $extKeyPath, $licenseData['user_name'] ?? '', $licenseData['extension_key'] ?? '');
                        }

                        try {
                            if (!$this->isComposerMode) {
                                $extKey = str_replace('.zip', '', $extKey);
                                $this->extensionArchiveService->extractExtensionFromZipFile($extKeyPath, $extKey, ($params['overwrite'] ? true : false));
                                unlink($extKeyPath);
                            }
                            // Let's flush all the cache to change the version number
                            $this->cacheManager->flushCaches();
                        } catch (\Exception $e) {
                            if (str_contains($e->getMessage(), 'Unable to open zip')) {
                                $this->addFlashMessage(LocalizationUtility::translate('errorMessage.error4', 'NsLicense', [$licenseData['extension_key'] ?? '', $this->typo3Version]), $licenseData['extension_key'] ?? '', ContextualFeedbackSeverity::ERROR);
                            } else {
                                $this->addFlashMessage(LocalizationUtility::translate('license-activation.overwrite_message', 'NsLicense'), $licenseData['extension_key'] ?? '', ContextualFeedbackSeverity::ERROR);
                            }
                            return $this->redirect('list');
                        }
                    }
                    if ($this->isComposerMode && empty($licenseData['extension_download_url'])) {
                        $this->addFlashMessage(LocalizationUtility::translate('errorMessage.error4', 'NsLicense', [$licenseData['extension_key'] ?? '', $this->typo3Version]), $licenseData['extension_key'] ?? '', ContextualFeedbackSeverity::ERROR);
                        return $this->redirect('list');
                    }
                    $this->nsLicenseRepository->insertNewData(json_decode(json_encode($licenseData)));
                } else {
                    $this->addFlashMessage(LocalizationUtility::translate('license-activation.overwrite_message', 'NsLicense'), 'EXT:' . ($licenseData['extension_key'] ?? ''), ContextualFeedbackSeverity::ERROR);
                    return $this->redirect('list');
                }

                // Is it from Update version?
                if ($fromWhere == 'fromUpdate') {
                    $this->addFlashMessage(LocalizationUtility::translate('license-activation.downloaded_successfully_from_update', 'NsLicense'), 'EXT:' . ($licenseData['extension_key'] ?? ''), ContextualFeedbackSeverity::OK);
                }

                // Seems from New license key registration
                else {
                    if ($isRepair == 'Yes') {
                        $this->addFlashMessage(LocalizationUtility::translate('license-activation.extension_repair', 'NsLicense'), 'EXT:' . ($licenseData['extension_key'] ?? ''), ContextualFeedbackSeverity::OK);
                    } else {
                        $messageKey = $this->isComposerMode
                            ? 'license-activation.activated_composer_success'
                            : 'license-activation.downloaded_successfully';
                        $this->addFlashMessage(LocalizationUtility::translate($messageKey, 'NsLicense'), 'EXT:' . ($licenseData['extension_key'] ?? ''), ContextualFeedbackSeverity::OK);
                    }
                }

                // Special code for EXT.ns_revolution_slider
                if (isset($params['extension_key']) && $params['extension_key'] == 'ns_revolution_slider') {

                    $versionOriginalId = $params['version'];
                    $this->extensionListService->getVersionFromEmconf($params['extension_key']);

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
                    if (is_array($folders) && !empty($folders)) {
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
                    $revsliderSourceFolder = $this->siteRoot . 'uploads/ns_license/ns_revolution_slider/' . $versionOriginalId . '/Resources/Public/vendor/wp/wp-content/uploads/';
                    $revsliderUploadFolder = $this->siteRoot . 'typo3conf/ext/ns_revolution_slider/Resources/Public/vendor/wp/wp-content/uploads/';
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
                }

                // Successfully redirect to license listing
                return $this->redirect('list');
            }
            $title = is_array($licenseData) ? ($licenseData['extKey'] ?? 'ERROR') : 'ERROR';
            $message = LocalizationUtility::translate('errorMessage.default', 'NsLicense');
            if (is_array($licenseData) && !empty($licenseData['error_code'])) {
                $license_type = $licenseData['license_type'] ?? '';
                $message = LocalizationUtility::translate('errorMessage.' . $licenseData['error_code'], 'NsLicense', [$license_type]);
            }
            $this->addFlashMessage($message, $title, ContextualFeedbackSeverity::ERROR);
            return $this->redirect('list');
        }

        // Successfully redirect to license listing
        return $this->redirect('list');
    }
   

    /**
     * Add domain action 
     * 
     * @param ServerRequestInterface $request
     * @return JsonResponse
     */
    public function addDomainAction(ServerRequestInterface $request): JsonResponse
    {
        $requestArguments = $request->getParsedBody();
        $extensionKey = $requestArguments['extension_key'] ?? '';
        $domain = $requestArguments['domain'] ?? '';
        $environment = $requestArguments['environment'] ?? 'production';
       
        // Validate inputs
        if (empty($extensionKey) || empty($domain) || empty($environment)) {
            return new JsonResponse([
                'success' => false,
                'message' => LocalizationUtility::translate('errorMessage.invalid_data', 'NsLicense', ['Invalid input data'])
            ], 400);
        }
        
        // Sanitize domain (remove http://, https://, trailing slashes)
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');
        
        // Validate environment
        if (!in_array($environment, ['production', 'staging', 'local'])) {
            return new JsonResponse([
                'success' => false,
                'message' => LocalizationUtility::translate('errorMessage.invalid_environment', 'NsLicense', ['Invalid environment'])
            ], 400);
        }
        
        try {
            // Get license key for the extension
            $licenseData = $this->nsLicenseRepository->fetchData($extensionKey);
            if (empty($licenseData) || empty($licenseData[0]['license_key'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => LocalizationUtility::translate('errorMessage.license_not_found', 'NsLicense', ['License key not found for this extension'])
                ], 400);
            }
            $licenseKey = $licenseData[0]['license_key'];
            // First, add domain to server using license key
            $serverResult = $this->licenseService->addDomainToServer($licenseKey, $domain, $extensionKey, $environment);
           
            if (!$serverResult || !isset($serverResult['success']) || !$serverResult['success']) {
                $errorMessage = $serverResult['message'] ?? 'Failed to add domain to server';
                return new JsonResponse([
                    'success' => false,
                    'message' => $errorMessage,
                    'error_code' => $serverResult['error_code'] ?? 'server_error'
                ]);
            }
            
            // Add domain to local database
            $result = $this->nsLicenseRepository->addDomain($extensionKey, $domain, $environment);
            
            if ($result) {
                $this->licenseService->fetchData('extensions');
                $message = LocalizationUtility::translate('license.domain.added_successfully', 'NsLicense', [$domain]);
                return new JsonResponse([
                    'success' => true,
                    'message' => $message
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => LocalizationUtility::translate('license.domain.already_exists', 'NsLicense', [$domain])
                ], 400);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => LocalizationUtility::translate('errorMessage.default', 'NsLicense') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete domain action
     *
     * @param ServerRequestInterface $request
     * @return JsonResponse
     */
    public function deleteDomainAction(ServerRequestInterface $request): JsonResponse
    {
        $requestArguments = $request->getParsedBody();
        $licenseKey = $requestArguments['license_key'] ?? '';
        $domain = $requestArguments['domain'] ?? '';
        $environment = $requestArguments['environment'] ?? 'production';

        if (empty($licenseKey) || empty($domain)) {
            return new JsonResponse([
                'success' => false,
                'message' => LocalizationUtility::translate('errorMessage.invalid_data', 'NsLicense', ['Invalid input data'])
            ], 400);
        }

        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');

        if (!in_array($environment, ['production', 'staging', 'local'])) {
            return new JsonResponse([
                'success' => false,
                'message' => LocalizationUtility::translate('errorMessage.invalid_environment', 'NsLicense', ['Invalid environment'])
            ], 400);
        }

        try {
            $licenseData = $this->nsLicenseRepository->fetchDataByLicenseKey($licenseKey);
            if (empty($licenseData)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => LocalizationUtility::translate('errorMessage.license_not_found', 'NsLicense', ['License key not found'])
                ], 400);
            }

            // Remove domain from API server first (POST)
            $serverResult = $this->licenseService->removeDomainFromServer($licenseKey, $domain, $environment);
            if (!$serverResult || !isset($serverResult['success']) || !$serverResult['success']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $serverResult['message'] ?? 'Failed to remove domain from server',
                    'error_code' => $serverResult['error_code'] ?? 'server_error'
                ], 400);
            }

            // Remove from local database by license key
            $result = $this->nsLicenseRepository->removeDomainByLicenseKey($licenseKey, $domain, $environment);

            if ($result) {
                $this->licenseService->fetchData('extensions');
                $message = LocalizationUtility::translate('license.domain.deleted_successfully', 'NsLicense', [$domain]);
                return new JsonResponse([
                    'success' => true,
                    'message' => $message
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'message' => LocalizationUtility::translate('license.domain.not_found', 'NsLicense', [])
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => LocalizationUtility::translate('errorMessage.default', 'NsLicense') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update (edit) domain action 
     *
     * @param ServerRequestInterface $request
     * @return JsonResponse
     */
    public function updateDomainAction(ServerRequestInterface $request): JsonResponse
    {
        $requestArguments = $request->getParsedBody();
        $licenseKey = $requestArguments['license_key'] ?? '';
        $oldDomain = $requestArguments['old_domain'] ?? '';
        $newDomain = $requestArguments['new_domain'] ?? '';
        $environment = $requestArguments['environment'] ?? 'production';

        if (empty($licenseKey) || empty($oldDomain) || empty($newDomain)) {
            return new JsonResponse([
                'success' => false,
                'message' => LocalizationUtility::translate('errorMessage.invalid_data', 'NsLicense', ['Invalid input data'])
            ], 400);
        }

        $oldDomain = preg_replace('#^https?://#', '', $oldDomain);
        $oldDomain = rtrim($oldDomain, '/');
        $newDomain = preg_replace('#^https?://#', '', $newDomain);
        $newDomain = rtrim($newDomain, '/');

        if (!in_array($environment, ['production', 'staging', 'local'])) {
            $environment = 'production';
        }

        try {
            $licenseData = $this->nsLicenseRepository->fetchDataByLicenseKey($licenseKey);
            if (empty($licenseData)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => LocalizationUtility::translate('errorMessage.license_not_found', 'NsLicense', ['License key not found'])
                ], 400);
            }

            $serverResult = $this->licenseService->updateDomainOnServer($licenseKey, $oldDomain, $newDomain, $environment);
            if (!$serverResult || !isset($serverResult['success']) || !$serverResult['success']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $serverResult['message'] ?? 'Failed to update domain on server',
                    'error_code' => $serverResult['error_code'] ?? 'server_error'
                ], 400);
            }

            $result = $this->nsLicenseRepository->updateDomainByLicenseKey($licenseKey, $oldDomain, $newDomain, $environment);

            if ($result) {
                $this->licenseService->fetchData('extensions');
                $message = LocalizationUtility::translate('license.domain.updated_successfully', 'NsLicense', [$oldDomain]);
                return new JsonResponse([
                    'success' => true,
                    'message' => $message
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'message' => LocalizationUtility::translate('license.domain.not_found', 'NsLicense', [])
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => LocalizationUtility::translate('errorMessage.default', 'NsLicense') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extend trial action
     * 
     * @return ResponseInterface
     */
    public function extendTrialAction(): ResponseInterface
    {
        $params = $this->request->getArguments();
        $extensionKey = $params['extension'] ?? '';
        // Validate inputs
        if (empty($extensionKey)) {
            $message = LocalizationUtility::translate('errorMessage.extension_key_required', 'NsLicense') ?? 'Extension key is required';
            $this->addFlashMessage(
                $message,
                $message,
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('list');
        }
        
        try {
            // Get license key for the extension
            $licenseData = $this->nsLicenseRepository->fetchData($extensionKey);

            if (empty($licenseData) || empty($licenseData[0]['license_key'])) {
                $message = LocalizationUtility::translate('errorMessage.license_not_found', 'NsLicense') ?? 'License key not found for this extension';
                $this->addFlashMessage(
                    $message,
                    $message,
                    ContextualFeedbackSeverity::ERROR
                );
                return $this->redirect('list');
            }
            $licenseKey = $licenseData[0]['license_key'];
            
            // Extend trial period using service
            $result = $this->licenseService->extendTrialPeriod($licenseKey);
            
            if ($result && isset($result['status']) && $result['status']) {
                $message = LocalizationUtility::translate('license.trial.extended_successfully', 'NsLicense') ?? 'Trial extended successfully by 30 days';
                $this->addFlashMessage(
                    $message,
                    $message,
                    ContextualFeedbackSeverity::OK
                );
            } else {
                $errorMessage = $result['message'] ?? (LocalizationUtility::translate('license.trial.extend_failed', 'NsLicense') ?? 'Failed to extend trial');
                $errorCode = $result['error_code'] ?? 'error';
                
                // Special handling for already extended error
                if ($errorCode === 'error5') {
                    $errorMessage = LocalizationUtility::translate('license.trial.already_extended', 'NsLicense') ?? 'Trial has already been extended';
                }
                
                $this->addFlashMessage(
                    $errorMessage,
                    $errorMessage,
                    ContextualFeedbackSeverity::ERROR
                );
            }
        } catch (\Exception $e) {
            $message = LocalizationUtility::translate('license.trial.extend_error_exception', 'NsLicense', [$e->getMessage()]) ?? 'Failed to extend trial: ' . $e->getMessage();
            $this->addFlashMessage(
                $message,
                $message,
                ContextualFeedbackSeverity::ERROR
            );
        }
        
        return $this->redirect('list');
    }

    /**
     * Fetch and update data from API based on type
     * Supports types: 'shop', 'services', 'extensions'
     * 
     * @param ServerRequestInterface $request
     * @return JsonResponse
     */
    public function fetchDataAction(ServerRequestInterface $request): JsonResponse
    {
        try {
            $params = $request->getParsedBody() ?? [];
            $type = $params['type'] ?? 'shop';
            
            // Validate type
            if (!in_array($type, ['shop', 'services', 'extensions'])) {
                $type = 'shop'; // Default to shop
            }
            
            $result = $this->licenseService->fetchData($type);
            
            // Check success based on type
            $isSuccess = false;
            $successMessageKey = 'fetchData.success.data_updated';
            
            if ($type === 'extensions') {
                $isSuccess = $result && isset($result['status']) && $result['status'] && isset($result['logs']);
                $successMessageKey = 'fetchData.success.extension_logs_updated';
            } elseif ($type === 'shop') {
                $isSuccess = $result && isset($result['sections']) && is_array($result['sections']);
                $successMessageKey = 'fetchData.success.shop_updated';
            } else {
                // services: https://t3planet.de/?type=997979 returns { "services": { "records": [...] } }; sanitized to categories with title, description, slug, tx_mask_pricing_text
                $isSuccess = $result && isset($result['categories']) && is_array($result['categories']);
                $successMessageKey = 'fetchData.success.services_updated';
            }
            
            if ($isSuccess) {
                $successMessage = LocalizationUtility::translate($successMessageKey, 'NsLicense')
                    ?? 'Data updated successfully';
                return new JsonResponse([
                    'success' => true,
                    'message' => $successMessage,
                    'data' => $result
                ]);
            } else {
                $errorCode = $result['error_code'] ?? 'error';
                if ($errorCode === 'no_license_keys') {
                    $errorMessage = LocalizationUtility::translate('fetchData.error.no_license_keys', 'NsLicense')
                        ?? 'No license keys found. Add a license first to fetch details';
                } else {
                    $errorMessage = !empty($result['message'])
                        ? $result['message']
                        : (LocalizationUtility::translate('fetchData.error.failed', 'NsLicense') ?? 'Failed to fetch data from API');
                }
                return new JsonResponse([
                    'success' => false,
                    'message' => $errorMessage,
                    'error_code' => $errorCode
                ]);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch extension logs by extension key
     * @param ServerRequestInterface $request
     */
    public function fetchExtensionLogsAction(): ResponseInterface
    {
        $filteredLogs = [];
        $params = $this->request->getParsedBody() ?? [];
        $licenseKey = $params['license_key'] ?? '';

        $view = $this->initializeModuleTemplate($this->request);
        if (empty($licenseKey)) {
            return $view->renderResponse('NsLicenseModule/Logs');
        }
        $extensionLogs = $this->loadSyncData('extensions');
        if (!empty($licenseKey) && is_array($extensionLogs)) {
            foreach ($extensionLogs as $log) {
                if (isset($log['license_key']) && $log['license_key'] === $licenseKey) {
                    $filteredLogs[] = $log;
                }
            }
        }
        $view->assign('logs', $filteredLogs);
        return $view->renderResponse('NsLicenseModule/Logs');
    }

    /**
     * Generates the action menu
     */
    protected function initializeModuleTemplate(
        ServerRequestInterface $request,
    ): ModuleTemplate {
        return $this->moduleTemplateFactory->create($request);
    }
}