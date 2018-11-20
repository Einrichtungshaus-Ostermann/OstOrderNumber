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

interface StoreServiceInterface
{
    /**
     * ...
     *
     * @return string
     */
    public function getKey();
}
