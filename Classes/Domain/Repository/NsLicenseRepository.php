<?php
namespace NITSAN\NsLicense\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***
 *
 * This file is part of the "NS Basetheme" Extension for TYPO3 CMS.
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
    public function insertNewData($data, $extVersion = null)
    {
        $isAvailable = $this->fetchData($data->extension_key);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('ns_product_license');
        if ($isAvailable) {
            $this->updateData($data, 1);
        } else {
            end($data->extension_download_url);
            $key = key($data->extension_download_url);
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
                    'lts_version' => $key
                ])
                ->execute();
        }

        return $row;
    }

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
        $isAvailable = $queryBuilder
                    ->execute()
                    ->fetchAll();

        return $isAvailable;
    }

    public function updateData($data, $ltsCheck = 0)
    {
        end($data->extension_download_url);
        $key = key($data->extension_download_url);
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
           ->set('documentation_link', $data->documentation_link);
        if ($ltsCheck == 1) {
            $queryBuilder->set('version', $key);
        }
        $queryBuilder->set('lts_version', $key)
           ->execute();
    }

    public function deactivate($licenseKey, $extensionKey)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('ns_product_license');
        $affectedRows = $queryBuilder
            ->delete('ns_product_license')
            ->where(
                $queryBuilder->expr()->eq('license_key', $queryBuilder->createNamedParameter($licenseKey)),
                $queryBuilder->expr()->eq('extension_key', $queryBuilder->createNamedParameter($extensionKey))
            )
            ->execute();
        return $affectedRows;
    }
}
