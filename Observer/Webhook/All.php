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
use Payone\CommercePlatform\Model\Method\BaseMethod;

class All implements ObserverInterface
{
    /**
     * Payment status values which mean that the payment has been authorized
     */
    public const STATUS_LIST_AUTHORIZED = [
        'PENDING_CAPTURE',
        'CAPTURED',
    ];

    /**
     * Payment status values which mean that the payment has failed
     */
    public const STATUS_LIST_FAILED = [
        'REJECTED',
        'CANCELLED',
    ];

    /**
     * Pending payment methods are authorized by the first successful webhook
     *
     * Every other webhook (rejected, cancelled, follow-up webhooks) must not authorize the order
     *
     * @param  Order $order
     * @param  array $webhookMessage
     * @return bool
     */
    protected function isAuthorizationNeeded(Order $order, $webhookMessage)
    {
        return $this->isOrderAwaitingPayment($order) === true
            && in_array($this->getPaymentStatus($webhookMessage), self::STATUS_LIST_AUTHORIZED, true);
    }

    /**
     * Pending payment orders are cancelled when the payment failed
     *
     * Needed when the customer does not return to the shop - otherwise the Returned controller cancels the order
     *
     * @param  Order $order
     * @param  array $webhookMessage
     * @return bool
     */
    protected function isCancellationNeeded(Order $order, $webhookMessage)
    {
        return $this->isOrderAwaitingPayment($order) === true
            && in_array($this->getPaymentStatus($webhookMessage), self::STATUS_LIST_FAILED, true)
            && $order->canCancel() === true;
    }

    /**
     * Checks if the order of a pending payment method is still waiting for the payment
     *
     * @param  Order $order
     * @return bool
     */
    protected function isOrderAwaitingPayment(Order $order)
    {
        /** @var BaseMethod $methodInstance */
        $methodInstance = $order->getPayment()->getMethodInstance();
        if ($methodInstance->isPendingPaymentMethod() === false) {
            return false; // payment has already been authorized in the checkout
        }

        // otherwise already authorized by a previous webhook or order cancelled meanwhile
        return $order->getState() === Order::STATE_PENDING_PAYMENT;
    }

    /**
     * Returns the payment status of the webhook message
     *
     * @param  array $webhookMessage
     * @return string|null
     */
    protected function getPaymentStatus($webhookMessage)
    {
        return $webhookMessage['payment']['status'] ?? null;
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

        $transId = null;
        if (!empty($webhookMessage['payment']['id'])) {
            $transId = $webhookMessage['payment']['id'];
        }

        $payment = $order->getPayment();

        /** @var BaseMethod $methodInstance */
        $methodInstance = $payment->getMethodInstance();
        if ($this->isAuthorizationNeeded($order, $webhookMessage) === true) {
            $methodInstance->authorizePayment($payment, $transId);

            $order->save(); // Transaction is created in order save and therefor needs to be done
        } elseif ($this->isCancellationNeeded($order, $webhookMessage) === true) {
            $order->cancel();
            $order->addCommentToStatusHistory(__('Payment failed at PAYONE (status %1), order cancelled.', $this->getPaymentStatus($webhookMessage)));
            $order->save();
        }

        $methodInstance->handleWebhookSpecific($order, $webhookMessage);
    }
}
