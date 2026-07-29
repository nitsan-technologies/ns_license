<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
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
                        if (isset($licenseData['is_life_time'], $licenseData['expiration_date']) && !$licenseData['is_life_time'] && (int)$licenseData['expiration_date'] <= time()) {
                            $this->nsLicenseRepository->markExpired($extData[0]['license_key'],$extKey,'EXPIRED_'.$extData[0]['order_id']);
                            $this->updateFiles($extFolder, $extKey);
                            return false;
                        }
                        if (!empty($licenseData['status'])) {
                            $this->nsLicenseRepository->updateData(json_decode(json_encode($licenseData)));
                            $this->updateRepairFiles($extFolder, $extKey);
                            return true;
                        }
                        if (isset($licenseData['status']) && !$licenseData['status']) {
                            $this->nsLicenseRepository->markExpired($extData[0]['license_key'],$extKey,$extData[0]['order_id'].'EXPIRED_');
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

 
    public function updateFiles($extFolder, $extKey = null)
    {
        if ($extKey !== 'ns_t3af' && is_dir($extFolder . 'Configuration/Backend') && file_exists($extFolder . 'Configuration/Backend/Modules.php')) {
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
                    'typo3_version' => (string) $this->typo3Version,
                ]),
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ];
        } elseif ($type === 'shop') {
            $catalogCacheService = new CatalogCacheService(
                $this->cacheManager,
                $this->composerApiClient,
                GeneralUtility::makeInstance(ExtensionConfiguration::class)
            );
            $data = $catalogCacheService->refreshFromApi();
            return $this->isValidShopCatalog($data)
                ? $data
                : ['status' => false, 'message' => 'No data received from API'];
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

    /**
     * @param array<string, mixed> $data
     */
    protected function isValidShopCatalog(array $data): bool
    {
        if (isset($data['tabs']) && is_array($data['tabs'])) {
            return true;
        }

        return isset($data['sections']) && is_array($data['sections']);
    }
    protected function saveSyncDataToDatabase(string $type, array $data): bool
    {
        try {
            return $this->nsLicenseRepository->saveSyncData($type, $data);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Fetch the list of products for the "Get New License" modal.
     * Shared by both the free-trial and purchase modes.
     * Calls the signed GetProduct.php endpoint.
     *
     * @return array{success:bool, products?:array, error_code?:string, message?:string}
     */
    public function getProducts(): array
    {
        $url = $this->getApiBaseUrl() . 'GetProduct.php';

        try {
            $data = $this->composerApiClient->requestJsonArray($url, 'GET', [
                'headers' => $this->buildSignedHeaders(''),
            ]);

            if (is_array($data) && !empty($data['status']) && isset($data['products']) && is_array($data['products'])) {
                return [
                    'success' => true,
                    'products' => $data['products'],
                ];
            }

            return [
                'success' => false,
                'error_code' => $data['error_code'] ?? 'error',
                'message' => $data['message'] ?? 'Failed to load products',
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return [
                'success' => false,
                'error_code' => 'server_error',
                'message' => 'Server connection error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error_code' => 'error',
                'message' => 'Error loading products: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Start a free trial: send the email OTP (step 1 of the trial flow).
     * Signs the request and POSTs to StartTrial.php. No license is created here.
     *
     * @param array<string,mixed> $input Keys: extension_key, email, name, domain,
     *        local_domain, staging_domain, language, terms_accepted, extension_name,
     *        price_annual, price_lifetime
     * @return array{success:bool, message?:string, error_code?:string, expires_in?:int, retry_after?:int, expiration_date_formatted?:string}
     */
    public function startTrial(array $input): array
    {
        $url = $this->getApiBaseUrl() . 'StartTrial.php';

        $payload = [
            'extension_key' => (string) ($input['extension_key'] ?? ''),
            'email' => (string) ($input['email'] ?? ''),
            'name' => (string) ($input['name'] ?? ''),
            'domain' => (string) ($input['domain'] ?? ''),
            'local_domain' => (string) ($input['local_domain'] ?? ''),
            'staging_domain' => (string) ($input['staging_domain'] ?? ''),
            'language' => (string) ($input['language'] ?? 'en'),
            'terms_accepted' => !empty($input['terms_accepted']),
            'extension_name' => (string) ($input['extension_name'] ?? ''),
            'price_annual' => (string) ($input['price_annual'] ?? ''),
            'price_lifetime' => (string) ($input['price_lifetime'] ?? ''),
        ];

        // The signature must cover the EXACT body bytes we transmit.
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = $this->buildSignedHeaders($rawBody);
        $headers['Content-Type'] = 'application/json';

        try {
            $data = $this->composerApiClient->requestJsonArray($url, 'POST', [
                'headers' => $headers,
                'body' => $rawBody,
                'http_errors' => false,
            ]);

            if (is_array($data) && !empty($data['status'])) {
                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Verification code sent.',
                    'expires_in' => (int) ($data['expires_in'] ?? 600),
                ];
            }

            $result = [
                'success' => false,
                'error_code' => $data['error_code'] ?? 'error',
                'message' => $data['message'] ?? 'Failed to start the trial. Please try again.',
            ];
            if (isset($data['retry_after'])) {
                $result['retry_after'] = (int) $data['retry_after'];
            }
            if (isset($data['expiration_date_formatted'])) {
                $result['expiration_date_formatted'] = (string) $data['expiration_date_formatted'];
            }
            return $result;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return [
                'success' => false,
                'error_code' => 'server_error',
                'message' => 'Server connection error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error_code' => 'error',
                'message' => 'Error starting trial: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify the trial OTP (step 2 of the trial flow). On success the server
     * creates the trial license and returns its details.
     *
     * @param array<string,mixed> $input Keys: extension_key, email, otp
     * @return array{success:bool, message?:string, error_code?:string, license_key?:string, order_id?:string, remaining_attempts?:int}
     */
    public function verifyTrialOtp(array $input): array
    {
        $url = $this->getApiBaseUrl() . 'VerifyTrialOtp.php';

        $payload = [
            'extension_key' => (string) ($input['extension_key'] ?? ''),
            'email' => (string) ($input['email'] ?? ''),
            'otp' => (string) ($input['otp'] ?? ''),
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = $this->buildSignedHeaders($rawBody);
        $headers['Content-Type'] = 'application/json';

        try {
            $data = $this->composerApiClient->requestJsonArray($url, 'POST', [
                'headers' => $headers,
                'body' => $rawBody,
                'http_errors' => false,
            ]);

            if (is_array($data) && !empty($data['status'])) {
                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Trial license created successfully.',
                    'extension_key' => $data['extension_key'] ?? $payload['extension_key'],
                    'license_key' => $data['license_key'] ?? '',
                    'user_name' => $data['user_name'] ?? '',
                    'order_id' => $data['order_id'] ?? '',
                    'expiration_date' => $data['expiration_date'] ?? '',
                    'is_life_time' => $data['is_life_time'] ?? 0,
                    'domain' => $data['domain'] ?? '',
                ];
            }

            $result = [
                'success' => false,
                'error_code' => $data['error_code'] ?? 'error',
                'message' => $data['message'] ?? 'Verification failed. Please try again.',
            ];
            if (isset($data['remaining_attempts'])) {
                $result['remaining_attempts'] = (int) $data['remaining_attempts'];
            }
            return $result;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return [
                'success' => false,
                'error_code' => 'server_error',
                'message' => 'Server connection error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error_code' => 'error',
                'message' => 'Error verifying code: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve an encrypted purchase_token from the post-checkout redirect URL.
     * Composer API decrypts; this extension never holds the encryption secret.
     *
     * @return array{success:bool, license_key?:string, message?:string, error_code?:string}
     */
    public function resolvePurchaseToken(string $purchaseToken): array
    {
        $url = $this->getApiBaseUrl() . 'ResolvePurchaseToken.php';

        $payload = [
            'purchase_token' => $purchaseToken,
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = $this->buildSignedHeaders($rawBody);
        $headers['Content-Type'] = 'application/json';

        try {
            $data = $this->composerApiClient->requestJsonArray($url, 'POST', [
                'headers' => $headers,
                'body' => $rawBody,
                'http_errors' => false,
            ]);

            if (is_array($data) && !empty($data['status'])) {
                $licenseKey = trim((string)($data['license_key'] ?? ''));
                if ($licenseKey === '') {
                    return [
                        'success' => false,
                        'error_code' => 'invalid_token',
                        'message' => 'Purchase token did not return a license key.',
                    ];
                }

                return [
                    'success' => true,
                    'license_key' => $licenseKey,
                ];
            }

            return [
                'success' => false,
                'error_code' => $data['error_code'] ?? 'error',
                'message' => $data['message'] ?? 'Could not resolve purchase token.',
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return [
                'success' => false,
                'error_code' => 'server_error',
                'message' => 'Server connection error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error_code' => 'error',
                'message' => 'Error resolving purchase token: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get API base URL from extension configuration (falls back to the default).
     * Always returns a URL ending in a single trailing slash.
     *
     * @return string
     */
    protected function getApiBaseUrl(): string
    {
        return 'https://composer.thebetaspace.com/API/';
    }

    /**
     * Read a value from the ns_license extension configuration.
     */
    protected function getExtensionConfiguration(string $key, string $default = ''): string
    {
        try {
            $value = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ns_license', $key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        } catch (\Throwable $e) {
            // Configuration not available; use default.
        }
        return $default;
    }

    /**
     * Build the HMAC authentication headers for a signed trial-API request.
     * Returns an empty array when no shared secret is configured (the server
     * then allows the request only in local development).
     *
     * @return array<string,string>
     */
    protected function buildSignedHeaders(string $rawBody = ''): array
    {
        $secret = $this->getExtensionConfiguration('sharedSecret');
        if ($secret === '') {
            return [];
        }

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $rawBody, $secret);

        return [
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
        ];
    }

}
