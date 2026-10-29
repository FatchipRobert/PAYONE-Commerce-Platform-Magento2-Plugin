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

namespace Payone\CommercePlatform\Model\Webhook;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Payone\CommercePlatform\Model\Payone;

/**
 * Creates the invoice for automatically captured payments (payment.captured webhook)
 */
class InvoiceCreator
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceSender $invoiceSender,
    ) {
    }

    /**
     * Registers the automatic capture (SALE) reported by the payment.captured webhook
     *
     * Magento creates a paid invoice and a capture transaction below the authorization and closes the authorization.
     * Does nothing for methods without automatic capture or if the order already has an invoice.
     *
     * @param Order $order
     * @return void
     */
    public function createInvoice(Order $order): void
    {
        $payment = $order->getPayment();
        $isAutomaticCapture = (bool)$payment->getMethodInstance()->getConfigData('automatic_capture', $order->getStoreId());
        if (!$isAutomaticCapture || $order->getInvoiceCollection()->count() > 0) {
            return;
        }

        // A preset transaction id is kept by Magento, but then the parent is not set automatically
        $payment->setTransactionId($order->getData('payone_cp_payment_id').Payone::TRANSACTION_SUFFIX_SALE);
        $authorizationTransaction = $payment->getAuthorizationTransaction();
        if ($authorizationTransaction) {
            $payment->setParentTransactionId($authorizationTransaction->getTxnId());
        }
        $payment->registerCaptureNotification($order->getBaseTotalDue(), true);

        $this->orderRepository->save($order); // saves the invoice and the transaction as well

        $invoice = $payment->getCreatedInvoice();
        if ($invoice) {
            $this->invoiceSender->send($invoice);
        }
    }
}
