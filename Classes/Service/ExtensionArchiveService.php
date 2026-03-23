<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException;
use TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService;
use TYPO3\CMS\Extensionmanager\Utility\FileHandlingUtility;

final class ExtensionArchiveService
{
    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly FileHandlingUtility $fileHandlingUtility,
        private readonly ExtensionManagementService $managementService,
    ) {}

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
