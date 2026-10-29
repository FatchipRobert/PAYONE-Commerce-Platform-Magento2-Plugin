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

declare(strict_types=1);

namespace Payone\CommercePlatform\Gateway\Request;

use Magento\Framework\Url;
use Magento\Sales\Model\Order;

/**
 * URL the customer returns to after a redirect (3DS, PayPal, ...)
 */
class ReturnUrl
{
    /**
     * @param Url $url frontend URL builder (also when the order is placed in the admin)
     */
    public function __construct(
        private readonly Url $url,
    ) {
    }

    /**
     * Returns the URL of the Returned controller for the order
     *
     * @param Order $order
     * @return string
     */
    public function get(Order $order): string
    {
        return $this->url->getUrl('payone_cp/onepage/returned').'?incrementId='.$order->getIncrementId();
    }
}
