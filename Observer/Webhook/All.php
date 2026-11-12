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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Every webhook: authorizes or cancels orders of redirect methods (pending_payment) according to the payment status
 */
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
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    /**
     * Handles the webhook for the order
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Order|null $order */
        $order = $observer->getOrder();
        $webhookMessage = $observer->getWebhookMessage();
        if (empty($order) || empty($webhookMessage)) {
            return;
        }

        if ($this->isAuthorizationNeeded($order, $webhookMessage)) {
            $this->authorize($order, (string)($webhookMessage['payment']['id'] ?? ''));
            $this->orderRepository->save($order); // the authorization transaction is created when the order is saved
        } elseif ($this->isCancellationNeeded($order, $webhookMessage)) {
            $order->cancel();
            $order->addCommentToStatusHistory(
                __('Payment failed at PAYONE (status %1), order cancelled.', $this->getPaymentStatus($webhookMessage))
            );
            $this->orderRepository->save($order);
        }
    }

    /**
     * Registers the authorization of the payment, which was executed at PAYONE after the redirect
     *
     * Offline (no gateway call) - like authorize() in the checkout for direct methods: transaction, state, comment.
     * The transaction id has to be set first, registerAuthorizationNotification() skips existing transactions.
     *
     * @param Order $order
     * @param string $paymentId
     * @return void
     */
    private function authorize(Order $order, string $paymentId): void
    {
        $payment = $order->getPayment();
        $payment->setTransactionId($paymentId);
        $payment->setIsTransactionClosed(false);
        $payment->registerAuthorizationNotification((float)$order->getBaseTotalDue());
    }

    /**
     * Pending payment methods are authorized by the first successful webhook
     *
     * Every other webhook (rejected, cancelled, follow-up webhooks) must not authorize the order
     *
     * @param Order $order
     * @param array $webhookMessage
     * @return bool
     */
    private function isAuthorizationNeeded(Order $order, array $webhookMessage): bool
    {
        return $this->isOrderAwaitingPayment($order)
            && in_array($this->getPaymentStatus($webhookMessage), self::STATUS_LIST_AUTHORIZED, true);
    }

    /**
     * Pending payment orders are cancelled when the payment failed
     *
     * Needed when the customer does not return to the shop - otherwise the Returned controller cancels the order
     *
     * @param Order $order
     * @param array $webhookMessage
     * @return bool
     */
    private function isCancellationNeeded(Order $order, array $webhookMessage): bool
    {
        return $this->isOrderAwaitingPayment($order)
            && in_array($this->getPaymentStatus($webhookMessage), self::STATUS_LIST_FAILED, true)
            && $order->canCancel();
    }

    /**
     * Checks if the order of a redirect method (initialize instead of authorize) is still waiting for the payment
     *
     * @param Order $order
     * @return bool
     */
    private function isOrderAwaitingPayment(Order $order): bool
    {
        if (!$order->getPayment()->getMethodInstance()->isInitializeNeeded()) {
            return false; // payment has already been authorized in the checkout
        }

        // otherwise already authorized by a previous webhook or order cancelled meanwhile
        return $order->getState() === Order::STATE_PENDING_PAYMENT;
    }

    /**
     * Returns the payment status of the webhook message
     *
     * @param array $webhookMessage
     * @return string|null
     */
    private function getPaymentStatus(array $webhookMessage): ?string
    {
        return $webhookMessage['payment']['status'] ?? null;
    }
}
