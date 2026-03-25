<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException;
use TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService;
use TYPO3\CMS\Extensionmanager\Utility\FileHandlingUtility;
use NITSAN\NsLicense\Service\ExtensionListService;


final class ExtensionArchiveService
{
    protected $siteRoot;

    protected string $extensionBackupPath;

    public function __construct(
        private readonly FileHandlingUtility $fileHandlingUtility,
        private readonly ExtensionManagementService $managementService,
        private readonly ExtensionListService $extensionListService,

    ) {
        $this->siteRoot = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/';
        $this->siteRoot = rtrim($this->siteRoot, '/') . '/';
    }

     /**
     * Extracts a given zip file and installs the extension.
     *
     * @param string $uploadedFile Path to uploaded file
     * @param bool   $overwrite    Overwrite existing extension if TRUE
     *
     * @throws ExtensionManagerException
     */
    public function extractExtensionFromZipFile(string $uploadedFile, string $extensionKey, bool $overwrite = false): string
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
    private function copyExtensionFolderToTempFolder($extensionKey)
    {
        $this->extensionBackupPath = Environment::getVarPath() . '/transient/' . $extensionKey . substr(sha1($extensionKey . microtime()), 0, 7) . '/';
        GeneralUtility::mkdir($this->extensionBackupPath);
        GeneralUtility::copyDirectory(
            $this->fileHandlingUtility->getExtensionDir($extensionKey),
            $this->extensionBackupPath,
        );
    }
    
   

    /**
     * getBackupToUploadFolder.
     *
     * @param string $extKey
     */
    public function getBackupToUploadFolder($extKey)
    {
        $souceFolder = $this->extensionListService->getExtensionFolder($extKey);
        if (is_dir($souceFolder)) {
            $versionId = $this->extensionListService->getVersionFromEmconf($extKey);
            $uploadFolder = $this->siteRoot . 'uploads/ns_license/' . $extKey . '/' . $versionId . '/';
            try {
                GeneralUtility::rmdir($uploadFolder, true);
                GeneralUtility::mkdir_deep($uploadFolder);
                GeneralUtility::copyDirectory($souceFolder, $uploadFolder);
            } catch (\Exception $e) {
            }
        }
    }

}
