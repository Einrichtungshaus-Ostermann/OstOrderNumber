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
namespace OstOrderNumber\Listeners\Controllers\Backend;

use Enlight_Event_EventArgs as EventArgs;
use Shopware_Controllers_Backend_CanceledOrder as Controller;

class CanceledOrder
{
    /**
     * ...
     *
     * @var array
     */
    private $configuration;

    /**
     * ...
     *
     * @param array $configuration
     */
    public function __construct($configuration)
    {
        // set parameters
        $this->configuration = $configuration;
    }

    /**
     * ...
     *
     * @param EventArgs   $arguments
     *
     * @return void
     */

    public function onPostDispatch( EventArgs $arguments )
    {
        // get the controller
        /* @var $controller Controller */
        $controller = $arguments->get( "subject" );

        // only convert order
        if ($controller->Request()->getActionName() !== 'convertOrder') {
            // done
            return;
        }

        // get parameters
        $view = $controller->View();

        // get vars
        $vars = $view->getAssign();

        // only successful
        if ($vars['success'] !== true) {
            // dont do shit
            return;
        }

        // get the order id
        $orderId = (integer) $controller->Request()->getParam('orderId');

        // get actual new order number
        $number = Shopware()->Modules()->Order()->sGetOrderNumber();

        // update every order and details
        $query = '
            UPDATE s_order
            SET ordernumber = ?
            WHERE id = ?
        ';
        Shopware()->Db()->query($query, array($number, $orderId));

        // and the details
        $query = '
            UPDATE s_order_details
            SET ordernumber = ?
            WHERE orderID = ?
        ';
        Shopware()->Db()->query($query, array($number, $orderId));
    }
}
