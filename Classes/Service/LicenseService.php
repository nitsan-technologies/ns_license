<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use NITSAN\NsLicense\Domain\Repository\NsLicenseRepository;
use NITSAN\NsLicense\Service\ExtensionListService;
use NITSAN\NsLicense\Service\ComposerApiClient;

final class LicenseService
{
    protected $nsLicenseRepository;
    protected $typo3Version;
    protected $packageManager;
    protected $cacheManager;
    protected $dependencyOrderingService;
    protected $extensionListService;
    protected ComposerApiClient $composerApiClient;

    public function __construct(?ExtensionListService $extensionListService = null, ?ComposerApiClient $composerApiClient = null)
    {
        $this->dependencyOrderingService = GeneralUtility::makeInstance(DependencyOrderingService::class);
        $this->packageManager = GeneralUtility::makeInstance(PackageManager::class);
        $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $this->nsLicenseRepository = GeneralUtility::makeInstance(NsLicenseRepository::class);
        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        $this->typo3Version = $versionInformation->getMajorVersion();
        $this->extensionListService = $extensionListService
            ?? GeneralUtility::makeInstance(
                ExtensionListService::class,
                $this->nsLicenseRepository,
                $this->packageManager
            );
        $this->composerApiClient = $composerApiClient
            ?? GeneralUtility::makeInstance(ComposerApiClient::class);
    }

   
    public function connectToServer($extKey = null, $reload = 0, $checkType = '')
    {
        $extFolder = $this->extensionListService->getExtensionFolder($extKey);
      
        if (!isset($_COOKIE['serverConnectionTime']) || $reload) {
            setcookie('serverConnectionTime', (string) 1, time() + 60 * 60 * 24 * 14);

            if ($checkType == 'checkTheme') {
                $licenseData = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_key=' . $extKey . '&typo3_version=' . $this->typo3Version);
                if (is_array($licenseData) && (isset($licenseData['status']) || isset($licenseData['checkTheme']))) {
                    return true;
                }
            }
            if ($extKey) {
                $extData = $this->nsLicenseRepository->fetchData($extKey);
                if (!empty($extData)) {
                    $licenseData = $this->fetchLicense('domain=' . GeneralUtility::getIndpEnv('HTTP_HOST') . '&ns_license=' . $extData[0]['license_key'] . '&typo3_version=' . $this->typo3Version);
                    if (is_array($licenseData)) {
                        if (!empty($licenseData['serverError'])) {
                            return true;
                        }
                        if (isset($licenseData['expiration_date']) && (int)$licenseData['expiration_date'] <= time()) {
                            $this->nsLicenseRepository->markExpired($extData[0]['license_key'],$extKey,'EXPIRED_'.$extData[0]['order_id']);
                            $this->updateFiles($extFolder);
                            return false;
                        }
                        if (!empty($licenseData['status'])) {
                            $this->nsLicenseRepository->updateData(json_decode(json_encode($licenseData)));
                            $this->updateRepairFiles($extFolder, $extKey);
                            return true;
                        }
                        if (isset($licenseData['status']) && !$licenseData['status']) {
                            $this->nsLicenseRepository->markExpired($extData[0]['license_key'],$extKey,$extData[0]['order_id'].'EXPIRED_');
                            $this->updateFiles($extFolder);
                            return false;
                        }
                    }
                } else {
                    $this->updateFiles($extFolder);
                    return false;
                }
            }
        }
        return true;
    }

 
    public function updateFiles($extFolder)
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
        $apiBaseUrl = $this->getApiBaseUrl();
        $url = $apiBaseUrl . 'GetComposerDetails.php?' . $license;

        try {
            return $this->composerApiClient->requestJsonArray($url, 'POST', []);
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

    /**
     * Add domain to server using license key
     * First validates the license key, then adds the domain to the server
     *
     * @param string $licenseKey
     * @param string $domain
     * @param string $extensionKey
     * @param string $environment
     * @return array|null Returns response with status and message, or null on error
     */
    public function addDomainToServer(string $licenseKey, string $domain, string $extensionKey = '', string $environment = 'local'): ?array
    {
        $apiBaseUrl = $this->getApiBaseUrl();

        // First, validate the license key by calling GetComposerDetails.php
        $currentDomain = GeneralUtility::getIndpEnv('HTTP_HOST');
        $validateUrl = $apiBaseUrl . 'GetComposerDetails.php?domain=' . urlencode($currentDomain) . '&ns_license=' . urlencode($licenseKey) . '&typo3_version=' . $this->typo3Version;

        try {
            $validateData = $this->composerApiClient->requestJsonArray($validateUrl, 'POST', []);
            // Check if license is valid (status should be true or have extension_key)
            if (!isset($validateData['status']) || !$validateData['status']) {
                return [
                    'success' => false,
                    'error_code' => $validateData['error_code'] ?? 'error1',
                    'message' => 'Invalid license key or license expired'
                ];
            }

            // License is valid, now add the new domain to server using POST
            $addDomainUrl = $apiBaseUrl . 'AddDomainToLicense.php';
            $addData = $this->composerApiClient->requestJsonArray($addDomainUrl, 'POST', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'ns_license' => $licenseKey,
                    'domain' => $domain,
                    'environment' => $environment,
                ]),
            ]);
            if (isset($addData['status']) && $addData['status']) {
                return [
                    'success' => true,
                    'message' => $addData['message'] ?? 'Domain added successfully to server',
                    'domains' => $addData['domains'] ?? ''
                ];
            } else {
                return [
                    'success' => false,
                    'error_code' => $addData['error_code'] ?? 'error1',
                    'message' => $addData['message'] ?? 'Failed to add domain to server'
                ];
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return [
                'success' => false,
                'error_code' => 'server_error',
                'message' => 'Server connection error: ' . $e->getMessage()
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error_code' => 'error',
                'message' => 'Error adding domain to server: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove domain from license on the API server (POST to RemoveDomainFromLicense.php)
     *
     * @param string $licenseKey
     * @param string $domain
     * @param string $environment production, staging, or local
     * @return array|null Returns response with success and message, or null on error
     */
    public function removeDomainFromServer(string $licenseKey, string $domain, string $environment = 'production'): ?array
    {
        $apiBaseUrl = $this->getApiBaseUrl();
        $url = $apiBaseUrl . 'RemoveDomainFromLicense.php';

        try {
            $data = $this->composerApiClient->requestJsonArray($url, 'POST', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'ns_license' => $licenseKey,
                    'domain' => $domain,
                    'environment' => $environment,
                ]),
            ]);
            if (isset($data['status']) && $data['status']) {
                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Domain removed successfully from server',
                    'domains' => $data['domains'] ?? '',
                ];
            }

            return [
                'success' => false,
                'error_code' => $data['error_code'] ?? 'error1',
                'message' => $data['message'] ?? 'Failed to remove domain from server',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error_code' => 'server_error',
                'message' => 'Error removing domain from server: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update (edit) domain name on the API server (POST to UpdateDomainInLicense.php).
     * Only the domain name is updated; environment stays the same.
     *
     * @param string $licenseKey
     * @param string $oldDomain
     * @param string $newDomain
     * @param string $environment production, staging, or local
     * @return array|null
     */
    public function updateDomainOnServer(string $licenseKey, string $oldDomain, string $newDomain, string $environment): ?array
    {
        $apiBaseUrl = $this->getApiBaseUrl();
        $url = $apiBaseUrl . 'UpdateDomainInLicense.php';

        try {
            $data = $this->composerApiClient->requestJsonArray($url, 'POST', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'ns_license' => $licenseKey,
                    'old_domain' => $oldDomain,
                    'new_domain' => $newDomain,
                    'environment' => $environment,
                ]),
            ]);
            if (isset($data['status']) && $data['status']) {
                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Domain updated successfully on server',
                ];
            }

            return [
                'success' => false,
                'error_code' => $data['error_code'] ?? 'error1',
                'message' => $data['message'] ?? 'Failed to update domain on server',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error_code' => 'server_error',
                'message' => 'Error updating domain on server: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Extend trial period for a license
     *
     * @param string $licenseKey License key
     * @return array|null Response from API
     */
    public function extendTrialPeriod(string $licenseKey): ?array
    {
        $apiBaseUrl = $this->getApiBaseUrl();
        $url = $apiBaseUrl . 'ExtendTrial.php';

        try {
            $data = $this->composerApiClient->requestJsonArray($url, 'POST', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'license' => $licenseKey,
                ]),
            ]);

            // If API call was successful, update local database
            if ($data && isset($data['status']) && $data['status'] && isset($data['expiration_date'])) {
                try {
                    $this->nsLicenseRepository->updateTrialExtended($licenseKey, (int)$data['expiration_date']);
                } catch (\Exception $e) {
                    // Log error but don't fail the request
                    // The server database is already updated, local update is secondary
                }
            }

            return $data;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return [
                'status' => false,
                'error_code' => 'server_error',
                'message' => 'Server connection error: ' . $e->getMessage()
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'error_code' => 'error',
                'message' => 'Error extending trial: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fetch data from API and update database
     * Supports types: 'shop', 'services', 'extensions'
     * @param string $type Type of data to fetch: 'shop', 'services', or 'extensions'
     * @return array Response from API
     */
    public function fetchData(string $type = 'shop'): array
    {
        $apiBaseUrl = $this->getApiBaseUrl();

        // Determine API endpoint and method based on type
        if ($type === 'extensions') {
            $extensions = [];
            $allLicense = $this->nsLicenseRepository->fetchData();
            if($allLicense){
                foreach ($allLicense as $license) {
                    $extensions[$license['extension_key']] = $license['license_key'];
                }
            }

            $url = $apiBaseUrl . 'GetAccessLogs.php';
            $method = 'POST';
            // Get all license keys from repository
            if (!$extensions) {
                return [
                    'status' => false,
                    'message' => 'No license keys found. Add a license first to fetch extension logs.',
                    'error_code' => 'no_license_keys'
                ];
            }
            $options = [
                'body' => json_encode([
                    'extensions' => $extensions,
                ]),
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ];
        } elseif ($type === 'shop') {
            $url = $apiBaseUrl . 'GetShopAndServicesData.php?type=shop';
            $method = 'GET';
            $options = [];
        } else {
            $url = $apiBaseUrl . 'GetShopAndServicesData.php?type=services';
            $method = 'GET';
            $options = [];
        }

        try {
            $data = $this->composerApiClient->requestJsonArray($url, $method, $options);
            // If API call was successful, update database
            if ($data) {
                if ($type === 'extensions') {
                    if (isset($data['logs']) && is_array($data['logs'])) {
                        $this->saveSyncDataToDatabase('extensions', $data['logs']);
                    }
                    if (isset($data['details']) && is_array($data['details'])) {
                        foreach ($data['details'] as $key => $licenseData) {
                            if (!isset($licenseData['extension_download_url'])) {
                                $licenseData['extension_download_url'] = [];
                            }
                            $licenseKey = trim((string)($licenseData['license_key'] ?? ''));
                            if ($licenseKey !== '') {
                                $licenseDataObj = json_decode(json_encode($licenseData));
                                $this->nsLicenseRepository->updateData($licenseDataObj);
                            } else {
                                $licenseDataObj = json_decode(json_encode($licenseData));
                                $this->nsLicenseRepository->insertNewData($licenseDataObj);
                            }
                        }
                    }
                } elseif ($type === 'shop') {
                    if (isset($data['sections']) && is_array($data['sections'])) {
                        $this->saveSyncDataToDatabase('shop', $data);
                    }
                } else {
                    if (isset($data['categories']) && is_array($data['categories'])) {
                        $this->saveSyncDataToDatabase('services', $data['categories']);
                    }
                }
            }

            return $data ?: ['status' => false, 'message' => 'No data received from API'];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return [
                'status' => false,
                'error_code' => 'server_error',
                'message' => 'Server connection error: ' . $e->getMessage()
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'error_code' => 'error',
                'message' => 'Error fetching data: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Save synchronized data to database
     *
     * @param string $type 'shop', 'services', or 'extensions'
     * @param array $data Data to save
     * @return bool
     */
    protected function saveSyncDataToDatabase(string $type, array $data): bool
    {
        try {
            return $this->nsLicenseRepository->saveSyncData($type, $data);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get API base URL based on environment
     *
     * @return string
     */
    protected function getApiBaseUrl(): string
    {
        return 'https://composer.t3planet.cloud/API/';
    }

}
