<?php

namespace NITSAN\NsLicense\Domain\Repository;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

/***
 *
 * This file is part of the "NS License" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/
/**
 * The repository for NsLicense
 */
class NsLicenseRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    /**
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     */
    public function insertNewData($data, $extVersion = null)
    {
        $isAvailable = $this->fetchData($data->extension_key);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('ns_product_license');
        $row = [];
        if ($isAvailable) {
            $this->updateData($data, 1);
        } else {
            $extensionDownloadUrl = $data->extension_download_url;
            if (PHP_VERSION > 8) {
                $extensionDownloadUrl = get_mangled_object_vars($data->extension_download_url);
            }
            end($extensionDownloadUrl);
            $key = key($extensionDownloadUrl);
            if (is_null($extVersion)) {
                $extVersion = $key;
            }
            $row = $queryBuilder
                ->insert('ns_product_license')
                ->values([
                    'name' => $data->name,
                    'email' => $data->email,
                    'order_id' => $data->order_id,
                    'license_key' => $data->license_key,
                    'extension_key' => $data->extension_key,
                    'product_link' => $data->product_link,
                    'documentation_link' => $data->documentation_link,
                    'version' => $extVersion,
                    'lts_version' => $key,
                    'is_life_time' => $data->is_life_time,
                    'expiration_date' => $data->expiration_date,
                    'domains' => $data->domains,
                    'license_type' => $data->license_type,
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('ns_product_license');
        $queryBuilder
            ->select('*')
            ->from('ns_product_license');
        if ($extensionKey != '') {
            $queryBuilder->where(
                $queryBuilder->expr()->eq('extension_key', $queryBuilder->createNamedParameter($extensionKey))
            );
        }
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
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('ns_product_license');
            $queryBuilder
            ->update('ns_product_license')
            ->where(
                $queryBuilder->expr()->eq('extension_key', $queryBuilder->createNamedParameter($data->extension_key))
            )
            ->set('name', $data->name)
            ->set('email', $data->email)
            ->set('order_id', $data->order_id)
            ->set('license_key', $data->license_key)
            ->set('extension_key', $data->extension_key)
            ->set('product_link', $data->product_link)
            ->set('is_life_time', $data->is_life_time)
            ->set('expiration_date', $data->expiration_date)
            ->set('documentation_link', $data->documentation_link)
            ->set('domains', $data->domains)
            ->set('license_type', $data->license_type);
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('ns_product_license');
        return $queryBuilder
            ->delete('ns_product_license')
            ->where(
                $queryBuilder->expr()->eq('license_key', $queryBuilder->createNamedParameter($licenseKey)),
                $queryBuilder->expr()->eq('extension_key', $queryBuilder->createNamedParameter($extensionKey))
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
        $convertedPath = "'".str_replace('/', '\/', $path)."'";

        // Find & Replace Ptah in `wp_posts` Table
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable('wp_posts');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->update('wp_posts')
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->like(
                        'guid',
                        $queryBuilder->createNamedParameter(
                            '%/typo3conf/ext/ns_revolution_slider/%',
                            Connection::PARAM_STR
                        )
                    )
                )
            )
            ->set(
                'guid',
                sprintf(
                    'REPLACE(`guid`, %s, %s)',
                    $queryBuilder->createNamedParameter('/typo3conf/ext/ns_revolution_slider/', Connection::PARAM_STR),
                    $queryBuilder->createNamedParameter($path, Connection::PARAM_STR)
                ),
                false
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
}
