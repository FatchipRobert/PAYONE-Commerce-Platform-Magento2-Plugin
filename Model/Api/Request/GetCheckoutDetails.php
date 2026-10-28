<?php
/**
 * PAYONE Commerce Platform Magento 2 Connector
 *
 * Copyright (c) 2026 PAYONE GmbH
 *
 * This source file is subject to the MIT License that is bundled
 * with this package in the file LICENSE.
 *
 * PHP version 8
 *
 * @category  Payone
 * @package   Payone_CommercePlatform_Magento2_Plugin
 * @author    FATCHIP GmbH <support@fatchip.de>
 * @copyright 2026 PAYONE GmbH
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://www.payone.de
 */

namespace Payone\CommercePlatform\Model\Api\Request;

use Magento\Sales\Model\Order;

class GetCheckoutDetails extends BaseRequest
{
    /**
     * @var string
     */
    protected $endpoint = '/v1/{merchantId}/commerce-cases/{commerceCaseId}/checkouts/{checkoutId}';

    /**
     * @var string
     */
    protected $httpMethod = self::METHOD_GET;

    /**
     * No payload for this GET request - the order is needed for the store config (credentials) and the API log
     *
     * @param Order $order
     * @return void
     */
    public function populateParameters(Order $order)
    {
        $this->setOrder($order);
    }
}
