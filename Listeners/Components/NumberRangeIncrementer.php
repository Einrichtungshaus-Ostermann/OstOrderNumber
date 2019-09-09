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
namespace OstOrderNumber\Listeners\Components;

use Enlight_Components_Db_Adapter_Pdo_Mysql as Db;
use OstOrderNumber\Services\StoreServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\NumberRangeIncrementerInterface;
use Shopware\Components\Plugin\CachedConfigReader;
use Shopware\Models\Shop\Shop;
use Shopware\Bundle\AttributeBundle\Service\DataLoader as AttributeDataLoader;

class NumberRangeIncrementer implements NumberRangeIncrementerInterface
{
    /**
     * The length of the order number running number.
     *
     * @var int
     */
    const LENGTH = 6;

    /**
     * ...
     *
     * @var array
     */
    protected $barverkaufDispatches = array('02');

    /**
     * ...
     *
     * @var Db
     */
    protected $db;

    /**
     * ...
     *
     * @var ModelManager
     */
    protected $modelManager;

    /**
     * ...
     *
     * @var CachedConfigReader
     */
    protected $cachedConfigReader;

    /**
     * The previously existing core service.
     *
     * @var NumberRangeIncrementerInterface
     */
    private $coreService;

    /**
     * ...
     *
     * @param NumberRangeIncrementerInterface $coreService
     * @param Db                              $db
     * @param ModelManager                    $modelManager
     * @param CachedConfigReader              $cachedConfigReader
     */
    public function __construct(NumberRangeIncrementerInterface $coreService, Db $db, ModelManager $modelManager, CachedConfigReader $cachedConfigReader)
    {
        // set parameters
        $this->coreService = $coreService;
        $this->db = $db;
        $this->modelManager = $modelManager;
        $this->cachedConfigReader = $cachedConfigReader;
    }

    /**
     * {@inheritdoc}
     */
    public function increment($name)
    {
        // set configuration
        /** @var array $configuration */
        $configuration = Shopware()->Container()->get('ost_order_number.configuration');

        // even active?
        if ($configuration['status'] === false) {
            // call parent
            return $this->coreService->increment($name);
        }

        // do we even support this number range?
        if ($name !== 'invoice') {
            // we dont
            return $this->coreService->increment($name);
        }

        // get the scope via configuration
        $scope = $configuration['scope'];

        /* @var $storeService StoreServiceInterface */
        $storeService = Shopware()->Container()->get('ost_order_number.store_service');

        // get the tore key
        $storeKey = $storeService->getKey();
        $storeKeyInt = (int) $storeKey;

        // get the type
        $type = $this->getType($storeKey, $configuration);
        
        // get invoice sub-name
        $shopName = 'invoice--store-' . $storeKey . '--scope-' . $scope . '--type-' . strtolower($type);

        // check if the current number group is already set
        $query = "SELECT 1 FROM s_order_number WHERE name = '" . $shopName . "'";
        $valid = (int) $this->db->fetchOne($query);

        // we have to set it first
        if ($valid === 0) {
            // get the first number
            $firstNumber = $this->getFirstNumber((string) $storeKeyInt, $scope, $type, $configuration);

            // create a description for this shop
            $desc = 'Bestellungen - Store: ' . $storeKey . ' - Scope: ' . $scope . ' - Type: ' . $type;

            // insert order number
            $query = 'INSERT INTO s_order_number SET `number` = :number, `name` = :name, `desc` = :desc';
            $this->db->query($query, ['number' => $firstNumber, 'name' => $shopName, 'desc' => $desc]);
        }

        // get the number via core service
        return $this->coreService->increment($shopName);
    }

    /**
     * ...
     *
     * @param string $storeKey
     * @paray array $configuration
     *
     * @return string
     */
    private function getType($storeKey, array $configuration)
    {
        // no erp given?
        if (Shopware()->Container()->initialized('ost_erp_api.api') === false) {
            // default
            return "KV";
        }

        // get the basket content
        $basket = Shopware()->Modules()->Order()->sBasketData;

        // ...
        $numbers = array();

        // loop it
        foreach ($basket['content'] as $article) {
            if ((integer) $article['modus'] === 0) {
                $numbers[] = $article['ordernumber'];
            }
        }

        // everything in stock
        $inStock = true;

        /* @var $api \OstErpApi\Api\Api */
        $api = Shopware()->Container()->get('ost_erp_api.api');

        // loop every article
        foreach ($numbers as $number) {
            /** @var Article $article */
            $article = $api->findOneBy(
                'article',
                ['[article.number] = ' . $number]
            );

            // loop every stock
            foreach ($article->getAvailableStock() as $stock) {
                // the correct one?
                if ($stock->getStore()->getKey() !== $storeKey) {
                    // next
                    continue;
                }

                // enough stock?
                // ...

                // all good
                continue 2;
            }

            // this article does not have enough stock
            $inStock = false;

            // and stop
            break;
        }

        // not in stock?!
        if ($inStock === false) {
            // definitly this one
            return "KV";
        }

        // check for dispatch method
        $dispatchId = Shopware()->Modules()->Order()->dispatchId;

        /* @var $attributeDataLoader AttributeDataLoader */
        $attributeDataLoader = Shopware()->Container()->get( "shopware_attribute.data_loader" );

        // get attributes
        $attributes = $attributeDataLoader->load( "s_premium_dispatch_attributes", $dispatchId );

        // reset our attribute
        $cogito = (string) $attributes[$configuration['attributeSoapApiShipping']];

        // not barverkauf?
        if (!in_array($cogito, $this->barverkaufDispatches)) {
            // default
            return "KV";
        }

        // this one
        return "BV";
    }

    /**
     * ...
     *
     * @param string $storeKey
     * @param string $scope
     * @param string $type
     * @param array $configuration
     *
     * @return string
     */
    private function getFirstNumber($storeKey, $scope, $type, $configuration)
    {
        // set via configuration?
        if (!empty($configuration['defaultNumber' . strtolower($type)])) {
            // return with configuration
            return $storeKey . $scope . str_pad((string) $configuration['defaultNumber' . strtolower($type)], self::LENGTH, '0', STR_PAD_LEFT);
        }

        // calculate first number
        $firstNumber = $storeKey .
            $scope .
            ((strtolower($type) === "bv") ? "5" : "1") .
            str_pad('1', self::LENGTH - 1, '0', STR_PAD_LEFT);

        // return it
        return $firstNumber;
    }
}
