<?php

namespace NITSAN\NsLicense\Domain\Repository;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

/***
 *
 * This file is part of the "NS License" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2026
 *
 ***/
/**
 * The repository for NsLicense
 */
class NsLicenseRepository
{
    /**
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     */
    public function insertNewData($data, $extVersion = null)
    {
        $isAvailable = $this->fetchData($data->extension_key);
        $queryBuilder = $this->getQueryBuilder('ns_product_license');
        $row = [];
        if ($isAvailable) {
            $this->updateData($data, 1);
        } else {
            $extensionDownloadUrl = $data->extension_download_url ?? [];
            if (PHP_VERSION > 8) {
                $extensionDownloadUrl = $data->extension_download_url ? get_mangled_object_vars($data->extension_download_url) : [];
            }
            end($extensionDownloadUrl);
            $key = key($extensionDownloadUrl);
            if (is_null($extVersion)) {
                $extVersion = $key;
            }
            $row = $queryBuilder
                ->insert('ns_product_license')
                ->values([
                    'name' => $data->name ?? '',
                    'email' => $data->email ?? '',
                    'order_id' => $data->order_id ?? 'FREE',
                    'license_key' => $data->license_key ?? '',
                    'extension_key' => $data->extension_key,
                    'product_link' => $data->product_link,
                    'documentation_link' => $data->documentation_link,
                    'version' => $extVersion,
                    'lts_version' => $key ?? 0,
                    'is_life_time' => $data->is_life_time ?? 0,
                    'expiration_date' => $data->expiration_date ?? 0,
                    'domains' => $data->domains ?? '',
                    'local_domains' => $data->local ?? '',
                    'staging_domains' => $data->staging ?? '',
                    'license_type' => $data->license_type ?? '',
                    'rating' => $data->rating ?? 0,
                    'downloads' => $data->downloads ?? 0,
                    'username' => $data->user_name ?? ''
                ])
                ->executeStatement();
        }

        return $row;
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function fetchData($extensionKey = null)
    {
        $queryBuilder = $this->getQueryBuilder('ns_product_license');
        $queryBuilder
            ->select('*')
            ->from('ns_product_license');
        if ($extensionKey != '') {
            $queryBuilder->where(
                $queryBuilder->expr()->eq('extension_key', $queryBuilder->createNamedParameter($extensionKey)),
            );
        }
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * Fetch license row(s) by license key. Returns same structure as fetchData (array of rows).
     *
     * @param string $licenseKey
     * @return array
     * @throws Exception
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function fetchDataByLicenseKey(string $licenseKey): array
    {
        if ($licenseKey === '') {
            return [];
        }
        $queryBuilder = $this->getQueryBuilder('ns_product_license');
        $queryBuilder
            ->select('*')
            ->from('ns_product_license')
            ->where(
                $queryBuilder->expr()->eq('license_key', $queryBuilder->createNamedParameter($licenseKey))
            );
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DBALException
     */
    public function updateData($data, $ltsCheck = 0): void
    {
        $extensionDownloadUrl = $data->extension_download_url;
        if (PHP_VERSION > 8) {
            if ($data->extension_download_url) {
                $extensionDownloadUrl = get_mangled_object_vars($data->extension_download_url);
            }
        }
        end($extensionDownloadUrl);
        $key = key($extensionDownloadUrl);
     
        if ($key) {
            $queryBuilder = $this->getQueryBuilder('ns_product_license');
            $queryBuilder
            ->update('ns_product_license')
            ->where(
                $queryBuilder->expr()->eq('extension_key', $queryBuilder->createNamedParameter($data->extension_key)),
            )
            ->set('name', $data->name ?? '')
            ->set('email', $data->email ?? '')
            ->set('order_id', $data->order_id ?? '')
            ->set('license_key', $data->license_key ?? '')
            ->set('username', $data->user_name ?? '')
            ->set('extension_key', $data->extension_key)
            ->set('product_link', $data->product_link)
            ->set('is_life_time', $data->is_life_time ?? 0)
            ->set('expiration_date', $data->expiration_date ?? 0)
            ->set('documentation_link', $data->documentation_link)
            ->set('domains', $data->domains ?? '')
            ->set('local_domains', $data->local_domains ?? '')
            ->set('staging_domains', $data->staging_domains ?? '')
            ->set('rating', $data->rating ?? 0)
            ->set('downloads', $data->downloads ?? 0)
            ->set('license_type', $data->license_type ?? 0)
            ->set('trial_extended', $data->trial_extended ?? 0);
            
            if ($ltsCheck == 1) {
                $queryBuilder->set('version', $key);
            }
            $queryBuilder->set('lts_version', $key)
                ->executeStatement();
        }

    }

    /**
     * @throws DBALException
     */
    public function deactivate($licenseKey, $extensionKey)
    {
        $queryBuilder = $this->getQueryBuilder('ns_product_license');
        return $queryBuilder
            ->delete('ns_product_license')
            ->where(
                $queryBuilder->expr()->eq('license_key', $queryBuilder->createNamedParameter($licenseKey)),
                $queryBuilder->expr()->eq('extension_key', $queryBuilder->createNamedParameter($extensionKey)),
            )
            ->executeStatement();
    }

    /**
     * updateSchema function
     *
     * @return void
     */
    public function updateSchema()
    {
        // Assets Path
        $path = '/_assets/8a9c9ad5ec273e16a34d1ff6a0d6f983/';
        $convertedPath = "'" . str_replace('/', '\/', $path) . "'";

        // Find & Replace Ptah in `wp_posts` Table
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $this->getQueryBuilder('wp_posts');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->update('wp_posts')
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->like(
                        'guid',
                        $queryBuilder->createNamedParameter(
                            '%/typo3conf/ext/ns_revolution_slider/%',
                            Connection::PARAM_STR,
                        ),
                    ),
                ),
            )
            ->set(
                'guid',
                sprintf(
                    'REPLACE(`guid`, %s, %s)',
                    $queryBuilder->createNamedParameter('/typo3conf/ext/ns_revolution_slider/', Connection::PARAM_STR),
                    $queryBuilder->createNamedParameter($path, Connection::PARAM_STR),
                ),
                false,
            )
            ->executeStatement();

        // Find & Replace Ptah in `wp_revslider_slides` Table
        $connection = $connectionPool->getConnectionForTable('wp_revslider_slides');
        $query1 = "UPDATE wp_revslider_slides SET params = JSON_REPLACE(params, '$.bg.image', REPLACE(JSON_UNQUOTE(JSON_EXTRACT(params, '$.bg.image')), '/typo3conf/ext/ns_revolution_slider/', $convertedPath)) WHERE JSON_UNQUOTE(JSON_EXTRACT(params, '$.bg.image')) LIKE '%/typo3conf/ext/ns_revolution_slider/%'";

        $connection->executeQuery($query1);
        $query2 = "UPDATE wp_revslider_slides SET params = JSON_REPLACE(params, '$.thumb.customThumbSrc', REPLACE(JSON_UNQUOTE(JSON_EXTRACT(params, '$.thumb.customThumbSrc')), '/typo3conf/ext/ns_revolution_slider/', $convertedPath))
            WHERE JSON_UNQUOTE(JSON_EXTRACT(params, '$.thumb.customThumbSrc')) LIKE '%/typo3conf/ext/ns_revolution_slider/%'";

        $connection->executeQuery($query2);
    }

    /**
     * Add domain to extension license
     * 
     * @param string $extensionKey
     * @param string $domain
     * @param string $environment (production, staging, local)
     * @return bool
     * @throws DBALException
     */
    public function addDomain(string $extensionKey, string $domain, string $environment): bool
    {
        $queryBuilder = $this->getQueryBuilder('ns_product_license');
        
        // Fetch current license data
        $currentData = $this->fetchData($extensionKey);
        if (empty($currentData)) {
            return false;
        }
        
        $licenseData = $currentData[0];
        
        // Determine which field to update based on environment
        $fieldName = 'domains'; // default to production
        if ($environment === 'staging') {
            $fieldName = 'staging_domains';
        } elseif ($environment === 'local') {
            $fieldName = 'local_domains';
        }
        
        // Get existing domains
        $existingDomains = !empty($licenseData[$fieldName]) ? GeneralUtility::trimExplode(',', $licenseData[$fieldName], true) : [];
        
        // Check if domain already exists
        if (in_array($domain, $existingDomains)) {
            return false; // Domain already exists
        }
        
        // Add new domain
        $existingDomains[] = $domain;
        $updatedDomains = implode(',', $existingDomains);
        
        // Update the database
        $queryBuilder
            ->update('ns_product_license')
            ->where(
                $queryBuilder->expr()->eq('extension_key', $queryBuilder->createNamedParameter($extensionKey)),
            )
            ->set($fieldName, $updatedDomains)
            ->executeStatement();
        
        return true;
    }

    /**
     * Remove domain from extension license
     *
     * @param string $extensionKey
     * @param string $domain
     * @param string $environment (production, staging, local)
     * @return bool
     * @throws DBALException
     */
    public function removeDomain(string $extensionKey, string $domain, string $environment): bool
    {
        $queryBuilder = $this->getQueryBuilder('ns_product_license');

        $currentData = $this->fetchData($extensionKey);
        if (empty($currentData)) {
            return false;
        }

        $licenseData = $currentData[0];

        $fieldName = 'domains';
        if ($environment === 'staging') {
            $fieldName = 'staging_domains';
        } elseif ($environment === 'local') {
            $fieldName = 'local_domains';
        }

        $existingDomains = !empty($licenseData[$fieldName]) ? GeneralUtility::trimExplode(',', $licenseData[$fieldName], true) : [];
        $key = array_search($domain, $existingDomains);
        if ($key === false) {
            return false;
        }

        array_splice($existingDomains, $key, 1);
        $updatedDomains = implode(',', $existingDomains);

        $queryBuilder
            ->update('ns_product_license')
            ->where(
                $queryBuilder->expr()->eq('extension_key', $queryBuilder->createNamedParameter($extensionKey)),
            )
            ->set($fieldName, $updatedDomains)
            ->executeStatement();

        return true;
    }

    /**
     * Remove domain from the given environment by license key.
     *
     * @param string $licenseKey
     * @param string $domain
     * @param string $environment (production, staging, local)
     * @return bool
     * @throws DBALException
     */
    public function removeDomainByLicenseKey(string $licenseKey, string $domain, string $environment): bool
    {
        $currentData = $this->fetchDataByLicenseKey($licenseKey);
        if (empty($currentData)) {
            return false;
        }
        $licenseData = $currentData[0];
        $fieldName = 'domains';
        if ($environment === 'staging') {
            $fieldName = 'staging_domains';
        } elseif ($environment === 'local') {
            $fieldName = 'local_domains';
        }
        $existingDomains = !empty($licenseData[$fieldName]) ? GeneralUtility::trimExplode(',', $licenseData[$fieldName], true) : [];
        $key = array_search($domain, $existingDomains);
        if ($key === false) {
            return false;
        }
        array_splice($existingDomains, $key, 1);
        $updatedDomains = implode(',', $existingDomains);
        $queryBuilder = $this->getQueryBuilder('ns_product_license');
        $queryBuilder
            ->update('ns_product_license')
            ->where(
                $queryBuilder->expr()->eq('license_key', $queryBuilder->createNamedParameter($licenseKey)),
            )
            ->set($fieldName, $updatedDomains)
            ->executeStatement();
        return true;
    }

    /**
     * Update (edit) domain name in the given environment only.
     * Checks that old_domain exists in that environment, then replaces it with new_domain.
     *
     * @param string $extensionKey
     * @param string $oldDomain
     * @param string $newDomain
     * @param string $environment production, staging, local
     * @return bool
     * @throws DBALException
     */
    public function updateDomain(string $extensionKey, string $oldDomain, string $newDomain, string $environment): bool
    {
        $queryBuilder = $this->getQueryBuilder('ns_product_license');
        $currentData = $this->fetchData($extensionKey);
        if (empty($currentData)) {
            return false;
        }
        $licenseData = $currentData[0];

        $field = 'domains';
        if ($environment === 'staging') {
            $field = 'staging_domains';
        } elseif ($environment === 'local') {
            $field = 'local_domains';
        }

        $list = !empty($licenseData[$field]) ? GeneralUtility::trimExplode(',', $licenseData[$field], true) : [];
        $key = array_search($oldDomain, $list);
        if ($key === false) {
            return false;
        }
        if ($oldDomain === $newDomain) {
            return true;
        }
        if (in_array($newDomain, $list, true)) {
            return false;
        }

        $list[$key] = $newDomain;
        $queryBuilder
            ->update('ns_product_license')
            ->where($queryBuilder->expr()->eq('extension_key', $queryBuilder->createNamedParameter($extensionKey)))
            ->set($field, implode(',', $list))
            ->executeStatement();
        return true;
    }

    /**
     * Update (edit) domain name in the given environment by license key.
     *
     * @param string $licenseKey
     * @param string $oldDomain
     * @param string $newDomain
     * @param string $environment production, staging, local
     * @return bool
     * @throws DBALException
     */
    public function updateDomainByLicenseKey(string $licenseKey, string $oldDomain, string $newDomain, string $environment): bool
    {
        $currentData = $this->fetchDataByLicenseKey($licenseKey);
        if (empty($currentData)) {
            return false;
        }
        $licenseData = $currentData[0];
        $field = 'domains';
        if ($environment === 'staging') {
            $field = 'staging_domains';
        } elseif ($environment === 'local') {
            $field = 'local_domains';
        }
        $list = !empty($licenseData[$field]) ? GeneralUtility::trimExplode(',', $licenseData[$field], true) : [];
        $key = array_search($oldDomain, $list);
        if ($key === false) {
            return false;
        }
        if ($oldDomain === $newDomain) {
            return true;
        }
        if (in_array($newDomain, $list, true)) {
            return false;
        }
        $list[$key] = $newDomain;
        $queryBuilder = $this->getQueryBuilder('ns_product_license');
        $queryBuilder
            ->update('ns_product_license')
            ->where($queryBuilder->expr()->eq('license_key', $queryBuilder->createNamedParameter($licenseKey)))
            ->set($field, implode(',', $list))
            ->executeStatement();
        return true;
    }

    /**
     * Update trial extended flag and expiration date for a license
     * 
     * @param string $licenseKey
     * @param int $expirationDate
     * @return bool
     * @throws DBALException
     */
    public function updateTrialExtended(string $licenseKey, int $expirationDate): bool
    {
        $queryBuilder = $this->getQueryBuilder('ns_product_license');
        
        $queryBuilder
            ->update('ns_product_license')
            ->where(
                $queryBuilder->expr()->eq('license_key', $queryBuilder->createNamedParameter($licenseKey)),
            )
            ->set('trial_extended', 1)
            ->set('expiration_date', $expirationDate)
            ->executeStatement();
        
        return true;
    }

    /**
     * Mark license as expired by prefixing order_id with EXPIRED_.
     */
    public function markExpired(string $licenseKey, string $extensionKey, string $newOrderId): bool
    {
        $queryBuilder = $this->getQueryBuilder('ns_product_license');
        $queryBuilder
            ->update('ns_product_license')
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('license_key', $queryBuilder->createNamedParameter($licenseKey)),
                    $queryBuilder->expr()->eq('extension_key', $queryBuilder->createNamedParameter($extensionKey)),
                ),
            )
            ->set('order_id', $newOrderId)
            ->executeStatement();
        return true;
    }

    /**
     * Get query builder for any table
     * 
     * @param string $tableName Table name
     * @return \TYPO3\CMS\Core\Database\Query\QueryBuilder
     */
    protected function getQueryBuilder(string $tableName)
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
    }

    /**
     * Normalize and validate data type
     * Maps 'extensions' to 'logs' and validates the type
     * 
     * @param string $dataType Type of data
     * @return string|null Normalized data type or null if invalid
     */
    protected function normalizeDataType(string $dataType): ?string
    {
        if ($dataType === 'extensions') {
            $dataType = 'logs';
        }
        if (!in_array($dataType, ['shop', 'services', 'logs'])) {
            return null;
        }
        return $dataType;
    }

    /**
     * Save or update synchronized data in registry table
     * 
     * @param string $dataType Type of data: 'shop', 'services', 'logs' (or 'extensions' which maps to 'logs')
     * @param array $data Data to save (will be JSON encoded)
     * @return bool True on success, false on failure
     * @throws DBALException
     * @throws Exception
     */
    public function saveSyncData(string $dataType, array $data): bool
    {
        $normalizedType = $this->normalizeDataType($dataType);
        if ($normalizedType === null) {
            return false;
        }
        
        // Encode data to JSON (single line, no whitespace)
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            return false;
        }
        
        $currentTime = time();
        $queryBuilder = $this->getQueryBuilder('ns_sync_registry');
        
        // Check if record exists
        $existingRecord = $queryBuilder
            ->select('uid')
            ->from('ns_sync_registry')
            ->where(
                $queryBuilder->expr()->eq('data_type', $queryBuilder->createNamedParameter($normalizedType))
            )
            ->executeQuery()
            ->fetchOne();
        
        if ($existingRecord) {
            // Update existing record
            $queryBuilder = $this->getQueryBuilder('ns_sync_registry');
            $queryBuilder
                ->update('ns_sync_registry')
                ->where(
                    $queryBuilder->expr()->eq('data_type', $queryBuilder->createNamedParameter($normalizedType))
                )
                ->set('data_content', $jsonData)
                ->set('updated_at', $currentTime)
                ->executeStatement();
        } else {
            // Insert new record
            $queryBuilder = $this->getQueryBuilder('ns_sync_registry');
            $queryBuilder
                ->insert('ns_sync_registry')
                ->values([
                    'data_type' => $normalizedType,
                    'data_content' => $jsonData,
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime,
                ])
                ->executeStatement();
        }
        
        return true;
    }

    /**
     * Get synchronized data from registry table
     * 
     * @param string $dataType Type of data: 'shop', 'services', 'logs' (or 'extensions' which maps to 'logs')
     * @return array Decoded data array, empty array if not found or invalid
     * @throws Exception
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function getSyncData(string $dataType): array
    {
        $normalizedType = $this->normalizeDataType($dataType);
        if ($normalizedType === null) {
            return [];
        }
        
        $queryBuilder = $this->getQueryBuilder('ns_sync_registry');
        $result = $queryBuilder
            ->select('data_content')
            ->from('ns_sync_registry')
            ->where(
                $queryBuilder->expr()->eq('data_type', $queryBuilder->createNamedParameter($normalizedType))
            )
            ->executeQuery()
            ->fetchOne();
        
        if ($result === false || $result === null) {
            return [];
        }
        
        // Decode JSON data
        $decodedData = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        
        return is_array($decodedData) ? $decodedData : [];
    }
}
