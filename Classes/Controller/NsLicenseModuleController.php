<?php

namespace NITSAN\NsLicense\Controller;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Cache\CacheManager;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use NITSAN\NsLicense\Service\LicenseService;
use NITSAN\NsLicense\Service\CatalogCacheService;
use NITSAN\NsLicense\Service\CatalogTabMapper;
use NITSAN\NsLicense\Service\ExtensionListService;
use NITSAN\NsLicense\Service\ExtensionArchiveService;
use NITSAN\NsLicense\Service\ProductBundleRegistry;
use NITSAN\NsLicense\Service\Checkout\CheckoutUrlBuilder;
use NITSAN\NsLicense\Service\Checkout\CheckoutReturnUrlBuilder;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use NITSAN\NsLicense\Domain\Repository\NsLicenseRepository;

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

    protected int $typo3Version = 0;
    
    /**
     * @var mixed|object|\Psr\Log\LoggerAwareInterface|CacheManager|(CacheManager&\Psr\Log\LoggerAwareInterface)|(CacheManager&\TYPO3\CMS\Core\SingletonInterface)|\TYPO3\CMS\Core\SingletonInterface|null
     */
    private mixed $cacheManager;


    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly RequestFactory $requestFactory,
        protected readonly NsLicenseRepository $nsLicenseRepository,
        protected readonly LicenseService $licenseService,
        protected readonly ExtensionListService $extensionListService,
        protected readonly ExtensionArchiveService $extensionArchiveService,
        protected readonly DependencyOrderingService $dependencyOrderingService,
        protected readonly CheckoutUrlBuilder $checkoutUrlBuilder,
        protected readonly CheckoutReturnUrlBuilder $checkoutReturnUrlBuilder,
        protected readonly CatalogCacheService $catalogCacheService,
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

        $query = $this->request->getQueryParams();
        $purchaseSuccess = !empty($query['purchase_success']);
        // Opaque encrypted token from composer API (base64url charset).
        $purchaseToken = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($query['purchase_token'] ?? '')) ?? '';
        $view->assign('purchaseSuccess', $purchaseSuccess);
        $view->assign('purchaseToken', $purchaseToken);
        if ($purchaseSuccess && $purchaseToken === '') {
            $this->addFlashMessage(
                LocalizationUtility::translate('license-get-new.buy.success.flash', 'NsLicense')
                    ?: 'Payment received. Check your email for the license key, then Activate it on this page.',
                LocalizationUtility::translate('license-get-new.buy.success.title', 'NsLicense')
                    ?: 'Purchase successful',
                ContextualFeedbackSeverity::OK,
            );
        }

        return $view->renderResponse('NsLicenseModule/Index');
    }

    /**
     * Catalog tab AJAX action — returns HTML for AI Universe / Extensions / Templates.
     */
    public function getCatalogDataAction(): ResponseInterface
    {
        $tab = (string)($this->request->getArgument('tab') ?? CatalogTabMapper::TAB_AI_UNIVERSE);
        if (!in_array($tab, CatalogTabMapper::getAllowedTabs(), true)) {
            $tab = CatalogTabMapper::TAB_AI_UNIVERSE;
        }

        $catalog = $this->catalogCacheService->getCatalog();
        $tabs = CatalogTabMapper::buildTabsFromCatalog($catalog);
        $tabData = $tabs[$tab] ?? ['title' => '', 'items' => []];
        $items = is_array($tabData['items'] ?? null) ? $tabData['items'] : [];

        $heroItem = null;
        $premiumItems = [];
        $freeItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $isFree = CatalogTabMapper::isFreeItem($item);
            $item['isFree'] = $isFree;

            if ($heroItem === null && !$isFree && $this->isMostPopularBadge((string)($item['badge'] ?? ''))) {
                $heroItem = $item;
                continue;
            }
            if ($isFree) {
                $freeItems[] = $item;
            } else {
                $premiumItems[] = $item;
            }
        }

        if (is_array($heroItem)) {
            $heroItem = $this->enrichHeroFromDetail($heroItem);
        }

        $itemsByKey = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = trim((string)($item['extensionKey'] ?? ''));
            if ($key !== '') {
                $itemsByKey[$key] = $item;
            }
        }

        // Return a clean HTML fragment for AJAX (not a full ModuleTemplate shell).
        $privatePath = ExtensionManagementUtility::extPath('ns_license') . 'Resources/Private/';
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        if (method_exists($view, 'setRequest')) {
            $view->setRequest($this->request);
        }
        $view->setTemplateRootPaths([$privatePath . 'Templates/']);
        $view->setPartialRootPaths([$privatePath . 'Partials/']);
        $view->setLayoutRootPaths([$privatePath . 'Layouts/']);
        $view->setTemplatePathAndFilename($privatePath . 'Templates/NsLicenseModule/Catalog.html');
        $view->assignMultiple([
            'catalogTab' => $tab,
            'catalogData' => $tabData,
            'catalogPremiumItems' => $premiumItems,
            'catalogFreeItems' => $freeItems,
            'catalogHeroItem' => $heroItem,
            'catalogItemsJson' => json_encode($itemsByKey, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            't3version' => $this->typo3Version,
        ]);

        return new HtmlResponse($view->render());
    }

    private function isMostPopularBadge(string $badge): bool
    {
        $normalized = strtolower(trim($badge));
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'most popular')
            || str_contains($normalized, 'beliebteste');
    }

    /**
     * Catalog list payloads often omit detail fields (features, detailImage).
     * Pull them from product detail for the hero banner.
     *
     * @param array<string, mixed> $heroItem
     * @return array<string, mixed>
     */
    private function enrichHeroFromDetail(array $heroItem): array
    {
        $extensionKey = trim((string)($heroItem['extensionKey'] ?? ''));
        if ($extensionKey === '') {
            return $heroItem;
        }

        $needsFeatures = !is_array($heroItem['features'] ?? null) || $heroItem['features'] === [];
        $needsDetailImage = trim((string)($heroItem['detailImage'] ?? '')) === '';

        if (!$needsFeatures && !$needsDetailImage) {
            if (is_array($heroItem['features'] ?? null)) {
                $heroItem['features'] = $this->normalizeFeatureLabels($heroItem['features']);
            }
            return $heroItem;
        }

        $detail = $this->catalogCacheService->fetchProductDetail($extensionKey);
        if (!is_array($detail)) {
            if (is_array($heroItem['features'] ?? null) && $heroItem['features'] !== []) {
                $heroItem['features'] = $this->normalizeFeatureLabels($heroItem['features']);
            }
            return $heroItem;
        }

        if ($needsFeatures) {
            $features = $detail['features'] ?? null;
            if (is_array($features) && $features !== []) {
                $heroItem['features'] = $this->normalizeFeatureLabels($features);
            }
        } elseif (is_array($heroItem['features'] ?? null)) {
            $heroItem['features'] = $this->normalizeFeatureLabels($heroItem['features']);
        }

        if ($needsDetailImage) {
            $detailImage = trim((string)($detail['detailImage'] ?? ''));
            if ($detailImage !== '') {
                $heroItem['detailImage'] = $detailImage;
            }
        }

        return $heroItem;
    }

    /**
     * @param list<mixed>|array<int|string, mixed> $features
     * @return list<string>
     */
    private function normalizeFeatureLabels(array $features): array
    {
        $labels = [];
        foreach ($features as $feature) {
            if (is_string($feature) || is_numeric($feature)) {
                $label = trim((string)$feature);
            } elseif (is_array($feature)) {
                $label = trim((string)(
                    $feature['label']
                    ?? $feature['title']
                    ?? $feature['name']
                    ?? $feature['text']
                    ?? ''
                ));
            } else {
                continue;
            }
            if ($label !== '') {
                $labels[] = $label;
            }
            if (count($labels) >= 5) {
                break;
            }
        }

        return $labels;
    }

    /**
     * Load synchronized data from database (e.g. extension access logs).
     *
     * @return array<int|string, mixed>
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
            $updateStatus = $this->licenseService->fetchLicense(
                'domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['extension']['license_key'] . '&ns_updates=1&typo3_version=' . $this->typo3Version,
                $extKey
            );
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
        $this->licenseService->fetchLicense(
            'domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['extension']['license_key'] . '&deactivate=1',
            $params['extension']['extension_key'] ?? null
        );
        $this->nsLicenseRepository->deactivate($params['extension']['license_key'], $params['extension']['extension_key']);
        $extFolder = $this->extensionListService->getExtensionFolder($params['extension']['extension_key']);
        $this->licenseService->updateFiles($extFolder, $params['extension']['extension_key']);
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
     * When true, downloadExtension returns a result array instead of redirecting (AJAX activate).
     */
    protected bool $returnActivationAsArray = false;

    /**
     * End activation with either a JSON-friendly result array or flash + redirect.
     *
     * @return array{success:bool, message:string, title?:string}|ResponseInterface
     */
    protected function finishActivation(
        string $message,
        string $title = '',
        ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::OK
    ): array|ResponseInterface {
        if ($this->returnActivationAsArray) {
            return [
                'success' => $severity === ContextualFeedbackSeverity::OK,
                'message' => $message,
                'title' => $title,
            ];
        }
        $this->addFlashMessage(
            $message,
            $title !== '' ? $title : ($severity === ContextualFeedbackSeverity::OK ? 'OK' : 'ERROR'),
            $severity
        );

        return $this->redirect('list');
    }

    /**
     * action activation.
     *
     * @param array $params
     * @return array{success:bool, message:string, title?:string}|ResponseInterface|null
     */
    public function downloadExtension($params = null, $fromWhere = null)
    {
        $isRepair = '';
        if (isset($params['license']) && $params['license'] != '') {
            if (isset($params['activation']) && $params['activation']) {
                $licenseData = $this->licenseService->fetchLicense(
                    'domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['license'] . '&activation=1&typo3_version=' . $this->typo3Version,
                    $params['extension']['extension_key'] ?? null
                );
            } else {
                $licenseData = $this->licenseService->fetchLicense(
                    'domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $params['license'] . '&typo3_version=' . $this->typo3Version,
                    $params['extension']['extension_key'] ?? ($params['extension_key'] ?? null)
                );
            }
            if (isset($params['extension']) && is_array($licenseData)) {
                if ($params['extension']['isUpdateAction'] && empty($licenseData['isUpdatable'])) {
                    return $this->finishActivation(
                        LocalizationUtility::translate('errorMessage.license_expired', 'NsLicense'),
                        'Your annual License key is expired',
                        ContextualFeedbackSeverity::ERROR
                    );
                }
            }
            if (isset($params['action']) && is_array($licenseData)) {
                if ($params['action'] === 'activation' && isset($licenseData['isUpdatable']) && !$licenseData['isUpdatable']) {
                    return $this->finishActivation(
                        LocalizationUtility::translate('errorMessage.license_expired', 'NsLicense'),
                        'Your annual License key is expired',
                        ContextualFeedbackSeverity::ERROR
                    );
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
                    return $this->finishActivation(
                        'EXT:' . ($licenseData['extension_key'] ?? '') . LocalizationUtility::translate('license-activation.activated', 'NsLicense'),
                        'EXT:' . ($licenseData['extension_key'] ?? ''),
                        ContextualFeedbackSeverity::OK
                    );
                }

                $isAvailable = $this->nsLicenseRepository->fetchData($licenseData['extension_key'] ?? '');
                $isVersionUpdate = ($fromWhere === 'fromUpdate') || !empty($params['isUpdateAction']);
                $licenseRecordOnlyUpdate = false;

                if ($isAvailable && $isVersionUpdate) {
                    // List "Update" action: reinstall ZIP (extension files change).
                    try {
                        if (!$this->isComposerMode) {
                            $overwrite = true;
                            $extensionKey = (string)($licenseData['extension_key'] ?? '');
                            // Install bundled foundation dependency first, then main extension.
                            // ns_t3af: license + repair only — never zip-install the product itself.
                            $this->installBundledDependencies($licenseData, $overwrite);
                            if ($extensionKey !== 'ns_t3af') {
                                $this->installExtensionFromDownloadUrls(
                                    $licenseData['extension_download_url'] ?? [],
                                    $licenseData,
                                    $extensionKey,
                                    $overwrite,
                                    true
                                );
                            }
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
                            return $this->finishActivation(
                                LocalizationUtility::translate('errorMessage.error4', 'NsLicense', [$licenseData['extension_key'] ?? '', $this->typo3Version]),
                                $licenseData['extension_key'] ?? '',
                                ContextualFeedbackSeverity::ERROR
                            );
                        }
                        return $this->finishActivation(
                            LocalizationUtility::translate('license-activation.overwrite_message', 'NsLicense'),
                            $licenseData['extension_key'] ?? '',
                            ContextualFeedbackSeverity::ERROR
                        );
                    }
                    $this->nsLicenseRepository->updateData(json_decode(json_encode($licenseData)), 1);
                } elseif ($isAvailable) {
                    // Trial → paid (or key exchange): update DB only; leave installed extension as-is.
                    $this->nsLicenseRepository->updateData(json_decode(json_encode($licenseData)), 1);
                    $licenseRecordOnlyUpdate = true;
                } elseif (!$isAvailable) {
                    // OPTION 1. Repairing > Let's just repair, If the product already there in typo3conf/ext + needs repair
                    $extFolder = $this->extensionListService->getExtensionFolder($licenseData['extension_key'] ?? '');

                    // Check if Update Repair
                    if ($this->licenseService->updateRepairFiles($extFolder, $licenseData['extension_key'] ?? '')) {
                        $isRepair = 'Yes';
                    }

                    // OPTION 2. First-time download when not already licensed locally
                    else {
                        try {
                            if (!$this->isComposerMode) {
                                $overwrite = false;
                                $extensionKey = (string)($licenseData['extension_key'] ?? '');
                                // Install bundled foundation dependency first, then main extension.
                                // ns_t3af: license + repair only — never zip-install the product itself.
                                $this->installBundledDependencies($licenseData, $overwrite);
                                if ($extensionKey !== 'ns_t3af') {
                                    $this->installExtensionFromDownloadUrls(
                                        $licenseData['extension_download_url'] ?? [],
                                        $licenseData,
                                        $extensionKey,
                                        $overwrite,
                                        true
                                    );
                                }
                            }
                            // Let's flush all the cache to change the version number
                            $this->cacheManager->flushCaches();
                        } catch (\Exception $e) {
                            if (str_contains($e->getMessage(), 'Unable to open zip')) {
                                return $this->finishActivation(
                                    LocalizationUtility::translate('errorMessage.error4', 'NsLicense', [$licenseData['extension_key'] ?? '', $this->typo3Version]),
                                    $licenseData['extension_key'] ?? '',
                                    ContextualFeedbackSeverity::ERROR
                                );
                            }
                            return $this->finishActivation(
                                LocalizationUtility::translate('errorMessage.default', 'NsLicense'),
                                $licenseData['extension_key'] ?? '',
                                ContextualFeedbackSeverity::ERROR
                            );
                        }
                    }
                    // Free Packagist AF may ship with an empty extension_download_url map.
                    if (
                        $this->isComposerMode
                        && empty($licenseData['extension_download_url'])
                        && ($licenseData['extension_key'] ?? '') !== 'ns_t3af'
                    ) {
                        return $this->finishActivation(
                            LocalizationUtility::translate('errorMessage.error4', 'NsLicense', [$licenseData['extension_key'] ?? '', $this->typo3Version]),
                            $licenseData['extension_key'] ?? '',
                            ContextualFeedbackSeverity::ERROR
                        );
                    }
                    $this->nsLicenseRepository->insertNewData(json_decode(json_encode($licenseData)));
                }

                // Is it from Update version?
                if ($fromWhere == 'fromUpdate') {
                    $successMessage = LocalizationUtility::translate('license-activation.downloaded_successfully_from_update', 'NsLicense');
                } elseif ($isRepair == 'Yes') {
                    $successMessage = LocalizationUtility::translate('license-activation.extension_repair', 'NsLicense');
                } elseif ($licenseRecordOnlyUpdate) {
                    $successMessage = LocalizationUtility::translate('license-activation.activated', 'NsLicense');
                } else {
                    $messageKey = $this->isComposerMode
                        ? 'license-activation.activated_composer_success'
                        : 'license-activation.downloaded_successfully';
                    $successMessage = LocalizationUtility::translate($messageKey, 'NsLicense');
                }
                $successTitle = 'EXT:' . ($licenseData['extension_key'] ?? '');

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
                            return $this->finishActivation($e->getMessage(), 'Extension not updated', ContextualFeedbackSeverity::ERROR);
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
                        return $this->finishActivation($e->getMessage(), 'Extension not updated', ContextualFeedbackSeverity::ERROR);
                    }

                    // Update Path in Database (If Composer Mode)
                    if (Environment::isComposerMode()) {
                        $this->nsLicenseRepository->updateSchema();
                    }
                }

                return $this->finishActivation($successMessage, $successTitle, ContextualFeedbackSeverity::OK);
            }
            $title = is_array($licenseData) ? ($licenseData['extKey'] ?? 'ERROR') : 'ERROR';
            $message = LocalizationUtility::translate('errorMessage.default', 'NsLicense');
            if (is_array($licenseData) && !empty($licenseData['error_code'])) {
                $license_type = $licenseData['license_type'] ?? '';
                $message = LocalizationUtility::translate('errorMessage.' . $licenseData['error_code'], 'NsLicense', [$license_type]);
            }
            return $this->finishActivation($message, $title, ContextualFeedbackSeverity::ERROR);
        }

        return $this->finishActivation(
            LocalizationUtility::translate('errorMessage.license_not_entered', 'NsLicense') ?: 'Please enter a license key.',
            'ERROR',
            ContextualFeedbackSeverity::ERROR
        );
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
     * Return full catalog product detail (tags, features, FAQ, changelog) for one extension key.
     * List cards stay slim; detail loads on demand to avoid oversized customer caches.
     */
    public function getCatalogProductDetailAction(ServerRequestInterface $request): JsonResponse
    {
        $params = $request->getQueryParams();
        $body = $request->getParsedBody();
        if (is_array($body)) {
            $params = array_merge($params, $body);
        }
        $extensionKey = trim((string)($params['extensionKey'] ?? ''));
        if ($extensionKey === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'extensionKey is required.',
                'error_code' => 'missing_extension_key',
            ], 400);
        }

        $item = $this->catalogCacheService->fetchProductDetail($extensionKey);
        if ($item === null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Product detail not found.',
                'error_code' => 'product_not_found',
            ], 404);
        }

        return new JsonResponse([
            'success' => true,
            'item' => $item,
        ]);
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
                $isSuccess = $result && ((isset($result['sections']) && is_array($result['sections'])) || (isset($result['tabs']) && is_array($result['tabs'])));
                $successMessageKey = 'fetchData.success.shop_updated';
            } else {
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
     * Get New License modal: return the list of products (trial/purchase).
     *
     * @param ServerRequestInterface $request
     * @return JsonResponse
     */
    public function getProductsAction(ServerRequestInterface $request): JsonResponse
    {
        try {
            $result = $this->licenseService->getProducts();
            $status = !empty($result['success']) ? 200 : 502;
            return new JsonResponse($result, $status);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get New License modal: start a free trial (sends an email OTP).
     *
     * @param ServerRequestInterface $request
     * @return JsonResponse
     */
    public function startTrialAction(ServerRequestInterface $request): JsonResponse
    {
        $params = $request->getParsedBody() ?? [];
        $extensionKey = trim((string) ($params['extension_key'] ?? ''));
        $email = trim((string) ($params['email'] ?? ''));
        $name = trim((string) ($params['name'] ?? ''));
        $domain = trim((string) ($params['domain'] ?? ''));
        $termsAccepted = !empty($params['terms_accepted']);

        if ($extensionKey === '' || $email === '') {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'invalid_data',
                'message' => LocalizationUtility::translate('errorMessage.invalid_data', 'NsLicense', ['Invalid input data']),
            ], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'invalid_email',
                'message' => LocalizationUtility::translate('license-get-new.form.error.email', 'NsLicense') ?: 'Please enter a valid email address.',
            ], 400);
        }
        if ($domain === '') {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'invalid_domain',
                'message' => LocalizationUtility::translate('license-get-new.form.error.domain', 'NsLicense') ?: 'Please enter your domain.',
            ], 400);
        }
        if (str_contains($domain, ',') || str_contains($domain, ';')) {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'invalid_domain',
                'message' => LocalizationUtility::translate('license-get-new.form.error.domain_multiple', 'NsLicense')
                    ?: 'Please enter only one domain (comma-separated domains are not allowed).',
            ], 400);
        }
        if (!$termsAccepted) {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'terms_required',
                'message' => LocalizationUtility::translate('license-get-new.form.error.terms', 'NsLicense') ?: 'Please accept the Terms & Conditions and Privacy Policy.',
            ], 400);
        }

        try {
            $result = $this->licenseService->startTrial([
                'extension_key' => $extensionKey,
                'email' => $email,
                'name' => $name,
                'domain' => $domain,
                'extension_name' => trim((string) ($params['extension_name'] ?? '')),
                'price_annual' => trim((string) ($params['price_annual'] ?? '')),
                'price_lifetime' => trim((string) ($params['price_lifetime'] ?? '')),
                'language' => trim((string) ($params['language'] ?? 'en')),
                'terms_accepted' => $termsAccepted,
            ]);
            $status = !empty($result['success']) ? 200 : 400;
            return new JsonResponse($result, $status);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get New License modal: verify the trial OTP and create the trial license.
     *
     * @param ServerRequestInterface $request
     * @return JsonResponse
     */
    public function verifyTrialOtpAction(ServerRequestInterface $request): JsonResponse
    {
        $params = $request->getParsedBody() ?? [];
        $extensionKey = trim((string) ($params['extension_key'] ?? ''));
        $email = trim((string) ($params['email'] ?? ''));
        $otp = trim((string) ($params['otp'] ?? ''));

        if ($extensionKey === '' || $email === '' || $otp === '') {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'invalid_data',
                'message' => LocalizationUtility::translate('errorMessage.invalid_data', 'NsLicense', ['Invalid input data']),
            ], 400);
        }

        try {
            $result = $this->licenseService->verifyTrialOtp([
                'extension_key' => $extensionKey,
                'email' => $email,
                'otp' => $otp,
            ]);
            $status = !empty($result['success']) ? 200 : 400;
            return new JsonResponse($result, $status);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get New License modal: validate catalog checkout URL for Buy / Purchase.
     */
    public function prepareCheckoutAction(ServerRequestInterface $request): JsonResponse
    {
        $params = $request->getParsedBody() ?? [];
        $extensionKey = trim((string) ($params['extension_key'] ?? ''));

        if ($extensionKey === '') {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'invalid_data',
                'message' => LocalizationUtility::translate('errorMessage.invalid_data', 'NsLicense', ['Invalid input data']),
            ], 400);
        }

        try {
            $catalog = $this->licenseService->getProducts();
            if (empty($catalog['success']) || !isset($catalog['products']) || !is_array($catalog['products'])) {
                return new JsonResponse([
                    'success' => false,
                    'error_code' => $catalog['error_code'] ?? 'error',
                    'message' => $catalog['message']
                        ?? (LocalizationUtility::translate('license-get-new.error.load_failed', 'NsLicense') ?: 'Failed to load products'),
                ], 502);
            }

            $product = null;
            foreach ($catalog['products'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (trim((string) ($row['extensionKey'] ?? '')) === $extensionKey) {
                    $product = $row;
                    break;
                }
            }

            if ($product === null) {
                return new JsonResponse([
                    'success' => false,
                    'error_code' => 'product_not_found',
                    'message' => LocalizationUtility::translate('license-get-new.buy.unavailable', 'NsLicense')
                        ?: 'Purchase is not available for this product.',
                ], 404);
            }

            $rawCheckoutUrl = trim((string) ($product['checkoutUrl'] ?? ''));
            $redirectParam = trim((string) ($product['checkoutRedirectParam'] ?? ''));
            $returnUrl = $this->checkoutReturnUrlBuilder->fromModule(['purchase_success' => '1']);
            $checkoutUrl = $this->checkoutUrlBuilder->normalize($rawCheckoutUrl, $returnUrl, $redirectParam);
            if ($checkoutUrl === '') {
                return new JsonResponse([
                    'success' => false,
                    'error_code' => 'checkout_unavailable',
                    'message' => LocalizationUtility::translate('license-get-new.buy.unavailable', 'NsLicense')
                        ?: 'Purchase is not available for this product.',
                ], 422);
            }

            // RedirectTo (checkout_redirect_param) is optional: open checkout without it.
            // When set, CheckoutUrlBuilder already appends ?cf_redirectto_*=returnUrl.
            return new JsonResponse([
                'success' => true,
                'checkoutUrl' => $checkoutUrl,
                'returnUrl' => $returnUrl,
                'extensionKey' => $extensionKey,
                'name' => (string) ($product['name'] ?? $extensionKey),
                'priceAnnual' => (string) ($product['priceAnnual'] ?? ''),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get New License modal: resolve encrypted purchase_token from checkout redirect.
     * Decrypts on composer API only; returns license_key for the success UI.
     */
    public function resolvePurchaseTokenAction(ServerRequestInterface $request): JsonResponse
    {
        $params = $request->getParsedBody() ?? [];
        if (!is_array($params)) {
            $params = [];
        }
        $token = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($params['purchase_token'] ?? '')) ?? '';

        if ($token === '') {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'invalid_data',
                'message' => LocalizationUtility::translate('errorMessage.invalid_data', 'NsLicense', ['Invalid input data']),
            ], 400);
        }

        try {
            $result = $this->licenseService->resolvePurchaseToken($token);
            $status = !empty($result['success']) ? 200 : 400;

            return new JsonResponse($result, $status);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get New License modal: activate the license key shown on the success step.
     * Reuses downloadExtension (activation=1) and returns JSON instead of redirecting.
     */
    public function activateLicenseAction(ServerRequestInterface $request): JsonResponse
    {
        $params = $request->getParsedBody() ?? [];
        if (!is_array($params)) {
            $params = [];
        }
        $license = preg_replace('/[^a-zA-Z0-9]/', '', (string)($params['license'] ?? '')) ?? '';

        if ($license === '') {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'license_not_entered',
                'message' => LocalizationUtility::translate('errorMessage.license_not_entered', 'NsLicense')
                    ?: 'Please enter a license key.',
            ], 400);
        }

        try {
            $this->ensureBackendRuntimeInitialized();
            $this->returnActivationAsArray = true;
            $result = $this->downloadExtension([
                'license' => $license,
                'activation' => true,
            ]);
            $this->returnActivationAsArray = false;

            if (!is_array($result)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => LocalizationUtility::translate('license-activation.downloaded_successfully', 'NsLicense')
                        ?: 'License activated.',
                ]);
            }

            $status = !empty($result['success']) ? 200 : 400;

            return new JsonResponse($result, $status);
        } catch (\Throwable $e) {
            $this->returnActivationAsArray = false;

            return new JsonResponse([
                'success' => false,
                'error_code' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ensure properties normally set in Extbase initializeAction (AJAX routes skip it).
     */
    protected function ensureBackendRuntimeInitialized(): void
    {
        if ($this->typo3Version > 0 && is_string($this->siteRoot) && $this->siteRoot !== '') {
            return;
        }
        $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $this->siteRoot = rtrim(Environment::getPublicPath(), '/') . '/';
        $this->isComposerMode = Environment::isComposerMode();
        $this->typo3Version = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();
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
                ['headers' => ['Authorization' => $authorization]],
            );

            $rawResponse = $response->getBody()->getContents();
            file_put_contents($extKeyPath, $rawResponse);

            // Let's take backup to /uploads/ns_license/
            $this->extensionArchiveService->getBackupToUploadFolder($extKey);
        } catch (\Throwable $e) {
            if ($this->returnActivationAsArray) {
                throw $e;
            }
            $this->addFlashMessage($e->getMessage(), 'Your server has an issue connecting with our license system; Please get in touch with your server administrator with the below error message.', ContextualFeedbackSeverity::ERROR);
            // Let's only redirect if we are at TYPO3 backend module (ignore at Login)
            $params = $this->request->getArguments();
            if (isset($params['action'])) {
                return $this->redirect('list');
            }
        }
    }

    /**
     * Install shared foundation dependencies before the licensed product extension.
     *
     * @param array<string, mixed> $licenseData
     */
    protected function installBundledDependencies(array $licenseData, bool $overwrite): void
    {
        if ($this->isComposerMode) {
            return;
        }

        $extensionKey = (string)($licenseData['extension_key'] ?? '');

        if (ProductBundleRegistry::isChatbotSearchProduct($extensionKey)) {
            $this->installExtensionFromDownloadUrls(
                $licenseData['cs_download_url'] ?? [],
                $licenseData,
                'ns_t3cs',
                $overwrite,
                false
            );
        }
    }

    /**
     * Download and extract an extension from a download-url map.
     * Uses the same flow for main extension and dependency extension.
     *
     * @param mixed $downloadUrls
     * @param array $licenseData
     * @param string $targetExtensionKey
     * @param bool $overwrite
     * @param bool $required If true and URL missing, throws exception
     */
    protected function installExtensionFromDownloadUrls(
        $downloadUrls,
        array $licenseData,
        string $targetExtensionKey,
        bool $overwrite,
        bool $required = false
    ): void {
        if ($targetExtensionKey === '' || $targetExtensionKey === 'ns_t3af') {
            return;
        }
        if (!is_array($downloadUrls)) {
            $downloadUrls = $downloadUrls ? (array)$downloadUrls : [];
        }
        $downloadgugrl = end($downloadUrls);

        if (!$downloadgugrl) {
            if ($required) {
                throw new \RuntimeException('Unable to open zip');
            }
            return;
        }

        $zipName = $targetExtensionKey . '.zip';
        $zipPath = $this->siteRoot . 'typo3temp/' . $zipName;
        $this->downloadZipFile(
            (string)$downloadgugrl,
            (string)($licenseData['license_key'] ?? ''),
            $zipPath,
            (string)($licenseData['user_name'] ?? ''),
            $targetExtensionKey
        );
        $this->extensionArchiveService->extractExtensionFromZipFile($zipPath, $targetExtensionKey, $overwrite);
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
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