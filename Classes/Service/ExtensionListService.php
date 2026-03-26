<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use NITSAN\NsLicense\Domain\Repository\NsLicenseRepository;

/**
 * Builds the list of installed ns_/nitsan_ extensions (premium and free) for the license module.
 */
class ExtensionListService
{
    protected NsLicenseRepository $nsLicenseRepository;
    protected PackageManager $packageManager;
    protected int $typo3Version;
    protected string $siteRoot;
    protected string $composerSiteRoot;
    protected bool $isComposerMode;

    public function __construct(
        NsLicenseRepository $nsLicenseRepository,
        PackageManager $packageManager
    ) {
        $this->nsLicenseRepository = $nsLicenseRepository;
        $this->packageManager = $packageManager;
        $this->typo3Version = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();
        $this->siteRoot = rtrim(Environment::getPublicPath() . '/', '/') . '/';
        $this->composerSiteRoot = Environment::getProjectPath() . '/';
        $this->isComposerMode = Environment::isComposerMode();
    }

    /**
     * Returns installed extensions grouped as premium (with license) and free.
     *
     * @return array{premium: array<string, array>, free: array<string, array>}
     */
    public function fetchExtensions(): array
    {
        $extensions = ['premium' => []];
        $licenseRecords = $this->nsLicenseRepository->fetchData();

        if ($licenseRecords !== []) {
            foreach ($licenseRecords as $extDetails) {
                if ($extDetails && isset($extDetails['extension_key'])) {
                    $package = $this->getPackage($extDetails['extension_key']);
                    $packageMetaData = $package ? $package->getPackageMetaData() : [];
                    $version = $packageMetaData ? $packageMetaData->getVersion() : '';
                    if ($this->typo3Version === 12 && $package) {
                        $icon = ExtensionManagementUtility::getExtensionIcon($package->getPackagePath());
                    } else {
                        $icon = $package ? $package->getPackageIcon() : '';
                    }
                    $description = '';
                    if ($extDetails['description']){
                        $description = $extDetails['description'] ?? '';
                    }elseif ($packageMetaData){
                        $description = $packageMetaData->getDescription();
                    }
                    if (isset($extDetails['license_key']) && $extDetails['license_key']) {
                        $domains = isset($extDetails['domains']) ? GeneralUtility::trimExplode(',', $extDetails['domains']) : [];
                        $composerPackage = 'nitsan/' . str_replace('_', '-', $extDetails['extension_key']);
                        $extensions['premium'][$extDetails['extension_key']] = [
                            'packagePath' => $package ? $package->getPackagePath() : '',
                            'key' => $package ? $package->getPackageKey() : $extDetails['extension_key'],
                            'composerPackage' => $composerPackage,
                            'version' => $version,
                            'state' => str_starts_with($version, 'dev-') ? 'alpha' : 'stable',
                            'icon' => $icon ? PathUtility::getAbsoluteWebPath($package->getPackagePath() . $icon) : '',
                            'title' => $packageMetaData ? $packageMetaData->getTitle() : '',
                            'description' => $description,
                            'is_premium' => true,
                            'details' => $extDetails,
                            'domains' => count($domains),
                            'trial' => isset($extDetails['order_id']) && str_starts_with($extDetails['order_id'],'TRIAL') ? true : false
                        ];
                    }
                }
            }
        }
        if (isset($extensions['premium']) && is_array($extensions['premium'])) {
            foreach ($extensions['premium'] as $key => $extension) {
                $extDetails = $extension['details'] ?? [];

                if (isset($extDetails['is_life_time']) && (int)$extDetails['is_life_time'] !== 1 && isset($extDetails['expiration_date'])) {
                    $extensions['premium'][$key]['details']['days'] = (int) floor((($extDetails['expiration_date'] - time()) + 86400) / 86400);
                }
                if (!empty($extDetails['domains'])) {
                    $extensions['premium'][$key]['details']['domains'] = str_replace(',', ' | ', $extDetails['domains']);
                }
                if (!empty($extDetails['local_domains'])) {
                    $extensions['premium'][$key]['details']['local_domains'] = str_replace(',', ' | ', $extDetails['local_domains']);
                }
                if (!empty($extDetails['staging_domains'])) {
                    $extensions['premium'][$key]['details']['staging_domains'] = str_replace(',', ' | ', $extDetails['staging_domains']);
                }

                $totalDomains = 0;
                if (!empty($extDetails['domains'])) {
                    $totalDomains += count(explode(',', $extDetails['domains']));
                }
                if (!empty($extDetails['local_domains'])) {
                    $totalDomains += count(explode(',', $extDetails['local_domains']));
                }
                if (!empty($extDetails['staging_domains'])) {
                    $totalDomains += count(explode(',', $extDetails['staging_domains']));
                }
                $extensions['premium'][$key]['domains'] = $totalDomains;

                $extVersion = $extensions['premium'][$key]['details']['version'];
                   

                if (isset($extDetails['lts_version']) && $extensions['premium'][$key]['version']) {
                    if (version_compare($extDetails['lts_version'], $extVersion, '>')) {
                        $extensions['premium'][$key]['details']['isUpdateAvail'] = true;
                    }
                    if($extension['key'] == 'ns_t3ac' || ($extension['key'] == 'ns_t3as')){
                        $csVersion = $extensions['premium'][$key]['details']['cs_version'];
                        if ($csVersion && version_compare($extDetails['cs_lts_version'], $csVersion, '>')) {
                            $extensions['premium'][$key]['details']['isUpdateAvail'] = true;
                        }
                    }
                }

                $extFolder = $this->getExtensionFolder($extension['key']);
                $extensions['premium'][$key]['details']['isRepareRequired'] = $this->checkRepairFiles($extFolder);
            }
        }

        return $extensions;
    }

    /**
     * Resolve filesystem path for an extension (composer or typo3conf/ext).
     */
    public function getExtensionFolder(string $extKey): string
    {
        if ($this->isComposerMode) {
            if ($extKey === 'dataviewer_pro') {
                return $this->composerSiteRoot . 'vendor/aix/' . $extKey . '/';
            }
            $extKeyForPath = str_replace('_', '-', $extKey);
            return $this->composerSiteRoot . 'vendor/nitsan/' . $extKeyForPath . '/';
        }
        return $this->siteRoot . 'typo3conf/ext/' . $extKey . '/';
    }

    /**
     * Whether the extension has disabled/renamed files that need repair.
     */
    private function checkRepairFiles(string $extFolder): bool
    {
        if (file_exists($extFolder . 'ext_tables..php')) {
            return true;
        }
        if (file_exists($extFolder . 'Configuration/TCA/Overrides/sys_template..php')) {
            return true;
        }
        if (file_exists($extFolder . 'Configuration/Backend/Modules..php')) {
            return true;
        }
        if (is_dir($extFolder . 'Resources/Private/Language')) {
            $languageDir = $extFolder . 'Resources/Private/Language/';
            $files = scandir($languageDir);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'xlf' && strpos($file, '..xlf') !== false) {
                        return true;
                    }
                }
            }
        }
        return false;
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
        $extFolder = $this->getExtensionFolder($extKey);
        if (is_file($extFolder . 'ext_emconf.php')) {
            include $extFolder . 'ext_emconf.php';
            $arrEmConf = (isset($EM_CONF[$extKey])) ? $EM_CONF[$extKey] : $EM_CONF[null];
            $versionId = $arrEmConf['version'];
        }
        return $versionId;
    }

    public function getPackage(string $extensionKey)
    {
        try {
            return $this->packageManager->getPackage($extensionKey);
        } catch (\Throwable $e) {
            return null;
        }
    }

}
