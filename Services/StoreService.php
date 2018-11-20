<?php declare(strict_types=1);

/**
 * Einrichtungshaus Ostermann GmbH & Co. KG - Order Number
 *
 * @package   OstOrderNumber
 *
 * @author    Eike Brandt-Warneke <e.brandt-warneke@ostermann.de>
 * @copyright 2018 Einrichtungshaus Ostermann GmbH & Co. KG
 * @license   proprietary
 */
namespace OstOrderNumber\Services;

use OstClient\Services\LocationServiceInterface;

class StoreService implements StoreServiceInterface
{
    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        // set configuration
        $configuration = Shopware()->Container()->get('ost_order_number.configuration');

        // are we using the client store api?
        if ($configuration['storeApi'] === false) {
            // return by manual value
            return $this->formatKey($configuration['storeManual']);
        }

        /* @var $clientService LocationServiceInterface */
        $clientService = Shopware()->Container()->get('ost_client.location_service');

        // return formatted by client api
        return $this->formatKey($clientService->getStoreKey());
    }

    /**
     * ...
     *
     * @param string $key
     *
     * @return string
     */
    private function formatKey(string $key): string
    {
        // always 2 chars
        return str_pad(substr($key, 0, 2), 2, '0', STR_PAD_LEFT);
    }
}
