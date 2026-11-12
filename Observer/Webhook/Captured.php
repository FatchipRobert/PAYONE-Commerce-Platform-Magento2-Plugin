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

namespace Payone\CommercePlatform\Observer\Webhook;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Payone\CommercePlatform\Model\Webhook\InvoiceCreator;

/**
 * payment.captured webhook: creates the invoice for automatically captured payments
 */
class Captured implements ObserverInterface
{
    /**
     * @param InvoiceCreator $invoiceCreator
     */
    public function __construct(
        private readonly InvoiceCreator $invoiceCreator,
    ) {
    }

    /**
     * Creates the invoice unless the order still waits for its authorization
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getData('order');
        $webhookMessage = $observer->getData('webhook_message');
        if (!$order instanceof Order || empty($webhookMessage)) {
            return;
        }

        // Authorization of pending payment methods is done in the All observer which is dispatched before
        if ($order->getState() === Order::STATE_PENDING_PAYMENT || $order->canInvoice() === false) {
            return;
        }

        $this->invoiceCreator->createInvoice($order);
    }
}
