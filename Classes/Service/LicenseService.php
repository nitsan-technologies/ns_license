<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Service\OpcodeCacheService;
use TYPO3\CMS\Install\Service\ClearCacheService;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use NITSAN\NsLicense\Domain\Repository\NsLicenseRepository;

class LicenseService
{
    protected $nsLicenseRepository;
    protected $siteRoot;
    protected $composerSiteRoot;
    protected $isComposerMode;
    protected $typo3Version;
    protected $packageManager;
    protected $cacheManager;
    protected $requestFactory;
    protected $dependencyOrderingService;

    public function __construct(
    ) {

        $this->dependencyOrderingService = GeneralUtility::makeInstance(DependencyOrderingService::class);
        $this->packageManager = GeneralUtility::makeInstance(PackageManager::class, $this->dependencyOrderingService);
        $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $this->requestFactory = GeneralUtility::makeInstance(RequestFactory::class);

        $this->nsLicenseRepository = GeneralUtility::makeInstance(NsLicenseRepository::class);
        $this->siteRoot = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/';
        $this->composerSiteRoot = \TYPO3\CMS\Core\Core\Environment::getProjectPath() . '/';
        $this->isComposerMode = Environment::isComposerMode();

        //TYPO3 version
        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        $this->typo3Version = $versionInformation->getMajorVersion();

        // Compulsory add "/" at the end
        $this->siteRoot = rtrim($this->siteRoot, '/') . '/';
    }

    /**
     * action list.
     */
    public function connectToServer($extKey = null, $reload = 0, $checkType = '')
    {
        $extFolder = $this->getExtensionFolder($extKey);
        if (!isset($_COOKIE['serverConnectionTime']) || $reload) {
            setcookie('serverConnectionTime', (string) 1, time() + 60 * 60 * 24 * 14);

            if ($checkType == 'checkTheme') {
                $licenseData = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_key=' . $extKey . '&typo3_version=' . $this->typo3Version);
                if (isset($licenseData->status) || isset($licenseData->checkTheme)) {
                    return true;
                }
            }
            if ($extKey) {
                $extData = $this->nsLicenseRepository->fetchData($extKey);
                if (!empty($extData)) {
                    $licenseData = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $extData[0]['license_key'] . '&typo3_version=' . $this->typo3Version);
                    if (!is_null($licenseData)) {
                        if (isset($licenseData->serverError) && $licenseData->serverError) {
                            return true;
                        }
                        if (isset($licenseData->status) && $licenseData->status) {
                            $this->nsLicenseRepository->updateData($licenseData);
                            $this->updateRepairFiles($extFolder, $extKey);
                            return true;
                        }
                        if (isset($licenseData->status) && !$licenseData->status) {
                            $this->updateFiles($extFolder, $extKey);
                            return false;
                        }
                    }
                } else {
                    $this->updateFiles($extFolder, $extKey);
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * getExtensionFolder.
     *
     * @param string $extKey
     */
    public function getExtensionFolder($extKey)
    {
        if ($this->isComposerMode) {
            if ($extKey == 'dataviewer_pro') {
                $extFolder = $this->composerSiteRoot . 'vendor/aix/' . $extKey . '/';
            } else {
                $extKey = str_replace('_', '-', $extKey);
                $extFolder = $this->composerSiteRoot . 'vendor/nitsan/' . $extKey . '/';
            }
        } else {
            $extFolder = $this->siteRoot . 'typo3conf/ext/' . $extKey . '/';
        }
        return $extFolder;
    }

    /**
     * updateFiles.
     */
    public function updateFiles($extFolder, $extension)
    {
        if (is_dir($extFolder . 'Configuration/Backend') && file_exists($extFolder . 'Configuration/Backend/Modules.php')) {
            rename($extFolder . 'Configuration/Backend/Modules.php', $extFolder . 'Configuration/Backend/Modules..php');
        }
        if (file_exists($extFolder . 'ext_tables.php')) {
            rename($extFolder . 'ext_tables.php', $extFolder . 'ext_tables..php');
        }
        if (file_exists($extFolder . 'Configuration/TCA/Overrides/sys_template.php')) {
            rename($extFolder . 'Configuration/TCA/Overrides/sys_template.php', $extFolder . 'Configuration/TCA/Overrides/sys_template..php');
        }
        if (is_dir($extFolder . 'Resources/Private/Language')) {
            $languageDir = $extFolder . 'Resources/Private/Language/';
            $files = scandir($languageDir);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'xlf') {
                        $oldPath = $languageDir . $file;
                        $newPath = $languageDir . pathinfo($file, PATHINFO_FILENAME) . '..xlf';
                        if (file_exists($oldPath) && strpos($file, '..xlf') === false) {
                            rename($oldPath, $newPath);
                        }
                    }
                }
            }
        }
        
        try {
            $this->unloadExtension($extension);
        } catch (\Exception $e) {
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $e->getMessage(),
                $extension,
                ContextualFeedbackSeverity::ERROR,
            );
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->addMessage($message);
        }
    }

    /**
     * Wrapper function for unloading extensions.
     *
     * @param string $extensionKey
     */
    protected function unloadExtension($extensionKey)
    {
        try {
            ExtensionManagementUtility::unloadExtension($extensionKey);
            GeneralUtility::makeInstance(ClearCacheService::class)->clearAll();
            GeneralUtility::makeInstance(OpcodeCacheService::class)->clearAllActive();
        } catch (\Exception $e) {
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $e->getMessage(),
                $extensionKey,
                ContextualFeedbackSeverity::ERROR,
            );
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->addMessage($message);
        }

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
        $url = 'https://composer.t3planet.cloud/API/GetComposerDetails.php?' . $license;
        try {
            $response = $this->requestFactory->request(
                $url,
                'POST',
                [],
            );
            $rawResponse = $response->getBody()->getContents();
            return json_decode($rawResponse);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return ['checkTheme' => true, 'serverError' => true];
        } catch (\Throwable $e) {
            $msg = GeneralUtility::makeInstance(
                FlashMessage::class,
                $e->getMessage(),
                'Your server has an issue connecting with our license system; Please get in touch with your server administrator with the below error message.',
                ContextualFeedbackSeverity::ERROR,
            );
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->addMessage($msg);
        }
    }

    /**
     * updateRepairFiles.
     */
    public function updateRepairFiles($extFolder, $extension)
    {
        $isRepair = false;
        if (file_exists($extFolder . 'ext_tables..php')) {
            rename($extFolder . 'ext_tables..php', $extFolder . 'ext_tables.php');
            $isRepair = true;
        }
        if (file_exists($extFolder . 'Configuration/Backend/Modules..php')) {
            rename($extFolder . 'Configuration/Backend/Modules..php', $extFolder . 'Configuration/Backend/Modules.php');
            $isRepair = true;
        }
        if (file_exists($extFolder . 'Configuration/TCA/Overrides/sys_template..php')) {
            rename($extFolder . 'Configuration/TCA/Overrides/sys_template..php', $extFolder . 'Configuration/TCA/Overrides/sys_template.php');
            $isRepair = true;
        }
        if (is_dir($extFolder . 'Resources/Private/Language')) {
            $languageDir = $extFolder . 'Resources/Private/Language/';
            $files = scandir($languageDir);
            if (is_array($files)) {
               foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && str_ends_with($file, '..xlf')) {
                        $oldPath = $languageDir . $file;
                        $newPath = str_replace('..xlf', '.xlf', $oldPath);
                        if (file_exists($oldPath)) {
                            rename($oldPath, $newPath);
                        }
                    }
                }
            }
            $isRepair = true;
        }

        if ($isRepair) {
            try {
                $this->loadExtension($extension);
            } catch (\Exception $e) {
                $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
                $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
                $messageQueue->addMessage(
                    GeneralUtility::makeInstance(
                        FlashMessage::class,
                        $e->getMessage(),
                        $extension,
                        ContextualFeedbackSeverity::ERROR,
                    ),
                );
            }
        }
        return $isRepair;
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

}
