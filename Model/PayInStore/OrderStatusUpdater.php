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

namespace Payone\CommercePlatform\Model\PayInStore;

use Magento\Framework\App\Area;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Store\Model\App\Emulation;
use Payone\CommercePlatform\Observer\PayInStore\DeleteCheckout;

/**
 * Pay in Store: updates an open order according to the status of its PAYONE checkout
 *
 * The checkout has no order at PAYONE, the payment is made in the store (the till books SALE payment information).
 * Any payment, full or partial → invoice over the order total (offline, no API call), still waiting for the payment
 * after cancel_after_days (payment/payone_cp_payinstore/, 0 = never) → cancelled (Observer\PayInStore\DeleteCheckout
 * deletes the checkout), checkout deleted in the store → cancelled. Nothing else is synchronized (decided with the
 * merchant): partial pickups and refunds in the store are not reflected in Magento.
 */
class OrderStatusUpdater
{
    /**
     * statusOutput.paymentStatus of the checkout: nothing paid yet - only then the order is cancelled automatically
     */
    public const STATUS_WAITING = 'WAITING_FOR_PAYMENT';

    /**
     * checkoutStatus of a checkout deleted in the store (GET still returns it)
     */
    public const CHECKOUT_DELETED = 'DELETED';

    /**
     * Suffix of the Magento capture transaction id (checkout id + suffix, there is no PAYONE payment id)
     */
    public const TRANSACTION_SUFFIX = '-instore';

    private const SECONDS_PER_DAY = 86400;

    /**
     * @param CommandPoolInterface $commandPool PayoneCPServiceCommandPool
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceSender $invoiceSender
     * @param DateTime $dateTime
     * @param Emulation $emulation
     */
    public function __construct(
        private readonly CommandPoolInterface $commandPool,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceSender $invoiceSender,
        private readonly DateTime $dateTime,
        private readonly Emulation $emulation,
    ) {
    }

    /**
     * Checks the checkout status of the order and marks it as paid or cancels it
     *
     * @param Order $order
     * @return void
     * @throws CommandException if the checkout status could not be determined
     */
    public function update(Order $order): void
    {
        $checkout = $this->commandPool->get('get_checkout_details')->execute(['order' => $order])->get();
        if (($checkout['checkoutStatus'] ?? null) === self::CHECKOUT_DELETED) {
            // already deleted at PAYONE - no DELETE call when the order is cancelled
            $order->getPayment()->setData(DeleteCheckout::FLAG_CHECKOUT_DELETED, true);
            $this->cancel($order, __('Checkout was cancelled in store, order cancelled.'));
            return;
        }

        // statusOutput.paymentStatus is PAYMENT_COMPLETED already after a partial payment, the amount is clearer
        $collected = (int)($checkout['statusOutput']['collectedAmount'] ?? 0);
        $status = $checkout['statusOutput']['paymentStatus'] ?? null;
        if ($collected > 0) {
            $this->registerPayment($order);
        } elseif ($status === self::STATUS_WAITING && $this->isExpired($order)) {
            $this->cancel(
                $order,
                __('No payment received in store within %1 days, order cancelled.', $this->getCancelAfterDays($order))
            );
        }
    }

    /**
     * Creates the paid invoice for the payment made in the store
     *
     * @param Order $order
     * @return void
     */
    private function registerPayment(Order $order): void
    {
        $payment = $order->getPayment();
        $payment->setTransactionId($order->getData('payone_cp_checkout_id').self::TRANSACTION_SUFFIX);
        $payment->registerCaptureNotification($order->getBaseTotalDue(), true);

        $this->orderRepository->save($order); // saves the invoice and the transaction as well

        $invoice = $payment->getCreatedInvoice();
        if ($invoice) {
            $this->invoiceSender->send($invoice);
        }
    }

    /**
     * Whether the configured number of days without payment has passed since the order was placed
     *
     * @param Order $order
     * @return bool
     */
    private function isExpired(Order $order): bool
    {
        $days = $this->getCancelAfterDays($order);
        if ($days <= 0) {
            return false;
        }

        // created_at is stored in UTC
        $createdAt = (new \DateTimeImmutable((string)$order->getCreatedAt(), new \DateTimeZone('UTC')))->getTimestamp();
        return $createdAt + $days * self::SECONDS_PER_DAY <= (int)$this->dateTime->gmtTimestamp();
    }

    /**
     * Cancels the unpaid order
     *
     * @param Order $order
     * @param Phrase $comment
     * @return void
     * @throws CommandException if the checkout could not be deleted (Observer\PayInStore\DeleteCheckout)
     */
    private function cancel(Order $order, Phrase $comment): void
    {
        if (!$order->canCancel()) {
            return;
        }

        $order->cancel();
        $order->addCommentToStatusHistory($this->translate($comment, (int)$order->getStoreId()));
        $this->orderRepository->save($order);
    }

    /**
     * Renders the comment in the language of the store - the cron runs without store locale
     *
     * Only around the rendering: the invoice mail emulates the store itself and emulations cannot be nested.
     *
     * @param Phrase $comment
     * @param int $storeId
     * @return string
     */
    private function translate(Phrase $comment, int $storeId): string
    {
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            return (string)$comment;
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    /**
     * Returns the number of days after which an unpaid order is cancelled, 0 = never
     *
     * @param Order $order
     * @return int
     */
    private function getCancelAfterDays(Order $order): int
    {
        return (int)$order->getPayment()->getMethodInstance()->getConfigData('cancel_after_days', $order->getStoreId());
    }
}
