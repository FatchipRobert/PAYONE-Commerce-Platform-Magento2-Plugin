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

namespace Payone\CommercePlatform\Observer\Webhook;

use Magento\Sales\Model\Order;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Payone\CommercePlatform\Helper\Order as OrderHelper;

class Captured implements ObserverInterface
{
    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * Constructor.
     *
     * @param OrderHelper $orderHelper
     */
    public function __construct(OrderHelper $orderHelper)
    {
        $this->orderHelper = $orderHelper;
    }

    /**
     * Handly Notify OK status
     *
     * @param  Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /* @var $order Order */
        $order = $observer->getOrder();
        $webhookMessage = $observer->getWebhookMessage();
        if (empty($order) || empty($webhookMessage)) {
            return;
        }

        // Authorization of pending payment methods is done in the All observer which is dispatched before
        if ($order->getState() === Order::STATE_PENDING_PAYMENT || $order->canInvoice() === false) {
            return;
        }

        $this->orderHelper->createInvoice($order);
    }
}
