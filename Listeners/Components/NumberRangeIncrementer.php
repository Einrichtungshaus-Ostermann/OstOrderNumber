<?php declare(strict_types=1);

namespace OstOrderNumber\Listeners\Components;

use Enlight_Components_Db_Adapter_Pdo_Mysql as Db;
use OstOrderNumber\Services\StoreServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\NumberRangeIncrementerInterface;
use Shopware\Components\Plugin\CachedConfigReader;
use Shopware\Models\Shop\Shop;

class NumberRangeIncrementer implements NumberRangeIncrementerInterface
{
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
        /* @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');

        // set configuration
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



        $scope = $configuration['scope'];


        /* @var $storeService StoreServiceInterface */
        $storeService = Shopware()->Container()->get('ost_order_number.store_service');

        $storeKey = $storeService->getKey();
        $storeKeyInt = (int) $storeKey;



        $firstNumber = (string) $storeKeyInt . $scope . '0000001';





        // get invoice sub-name
        $shopName = 'invoice--store-' . $storeKey . '--scope-' . $scope;

        // check if the current number group is already set
        $query = "SELECT 1 FROM s_order_number WHERE name = '" . $shopName . "'";
        $valid = (int) $this->db->fetchOne($query);

        // we have to set it first
        if ($valid === 0) {
            // create a description for this shop
            $desc = 'Bestellungen - Store: ' . $storeKey . ' - Scope: ' . $scope;

            // insert order number
            $query = 'INSERT INTO s_order_number SET `number` = :number, `name` = :name, `desc` = :desc';
            $this->db->query($query, ['number' => $firstNumber, 'name' => $shopName, 'desc' => $desc]);
        }



        // get the number via core service
        return $this->coreService->increment($shopName);
    }
}
