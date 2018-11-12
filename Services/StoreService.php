<?php declare(strict_types=1);

namespace OstOrderNumber\Services;

use OstClient\Services\LocationServiceInterface;

class StoreService implements StoreServiceInterface
{
    public function getKey()
    {
        // set configuration
        $configuration = Shopware()->Container()->get('ost_order_number.configuration');

        if ($configuration['storeApi'] === false) {
            return $this->formatKey($configuration['storeManual']);
        }


        /* @var $clientService LocationServiceInterface */
        $clientService = Shopware()->Container()->get('ost_client.location_service');

        return $this->formatKey($clientService->getStoreKey());
    }



    private function formatKey($key)
    {
        return str_pad(substr($key, 0, 2), 2, '0', STR_PAD_LEFT);
    }
}
