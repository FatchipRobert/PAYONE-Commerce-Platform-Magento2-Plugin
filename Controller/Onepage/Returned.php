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

namespace Payone\CommercePlatform\Controller\Onepage;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage\CollectionFactory;

/**
 * Return of the customer from a redirect (3DS, PayPal, ...)
 */
class Returned implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * Status values according to the PAYONE Commerce Platform StatusValue enum (PCP-ServerSDK-php)
     */
    public const STATUS_LIST_SUCCESS = [
        'PENDING_CAPTURE',
        'CAPTURED',
    ];

    public const STATUS_LIST_CANCEL = [
        'REJECTED',
        'CANCELLED',
    ];

    public const RETRY_LIMIT = 5;
    public const RETRY_MILLISECONDS_SLEEP = 100; // 1/10th second

    /**
     * @param Session $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $resultRedirectFactory
     * @param CollectionFactory $webhookMessageCollectionFactory
     * @param CommandPoolInterface $commandPool PayoneCPServiceCommandPool
     */
    public function __construct(
        private readonly Session $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ManagerInterface $messageManager,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly CollectionFactory $webhookMessageCollectionFactory,
        private readonly CommandPoolInterface $commandPool,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Redirects to the success page, back to the checkout or to the cart depending on the payment status
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order->getId()) {
            return $this->handleErrorState($order);
        }

        $status = $this->getPaymentStatus($order);

        if (in_array($status, self::STATUS_LIST_SUCCESS, true)) {
            return $this->handleSuccessState();
        }

        if (in_array($status, self::STATUS_LIST_CANCEL, true)) {
            return $this->handleCancelState($order);
        }

        // Intermediate status (CREATED, REDIRECTED, PENDING_PAYMENT, PENDING_COMPLETION, AUTHORIZATION_REQUESTED,
        // CAPTURE_REQUESTED), unknown status or status not determinable (no webhook yet, API error):
        // the payment may still succeed, so the order must not be cancelled here
        return $this->handlePendingState();
    }

    /**
     * Returns the payment status of the latest received webhook for the order
     *
     * The webhook may arrive shortly after the customer returned, so the lookup is retried a few times
     *
     * @param Order $order
     * @param int $retryCount
     * @return string|null
     */
    private function getStatusFromWebhook(Order $order, int $retryCount = 0): ?string
    {
        $collection = $this->webhookMessageCollectionFactory->create();
        $collection->addFieldToFilter('payment_id', $order->getData('payone_cp_payment_id'))
            ->setOrder('date', 'DESC');

        if ($collection->getSize() > 0) {
            $status = $collection->getFirstItem()->getStatus();
            return $status !== null ? (string)$status : null;
        }

        if ($retryCount < self::RETRY_LIMIT) {
            usleep(self::RETRY_MILLISECONDS_SLEEP * 1000);
            return $this->getStatusFromWebhook($order, $retryCount + 1);
        }
        return null;
    }

    /**
     * Returns the payment status of the last event of the latest payment execution from GetCheckoutDetails
     *
     * @param Order $order
     * @return string|null null if the API call failed
     */
    private function getStatusFromApi(Order $order): ?string
    {
        try {
            $checkout = $this->commandPool->get('get_checkout_details')->execute(['order' => $order])->get();
        } catch (CommandException $e) {
            return null;
        }

        $paymentExecutions = $checkout['paymentExecutions'] ?? null;
        if (empty($paymentExecutions) || !is_array($paymentExecutions)) {
            return null;
        }

        $paymentExecution = end($paymentExecutions);
        if (empty($paymentExecution['events']) || !is_array($paymentExecution['events'])) {
            return null;
        }

        $events = $paymentExecution['events'];
        $lastEvent = end($events);
        return !empty($lastEvent['paymentStatus']) ? (string)$lastEvent['paymentStatus'] : null;
    }

    /**
     * Determines the payment status - from the received webhooks first, from the API as fallback
     *
     * @param Order $order
     * @return string|null
     */
    private function getPaymentStatus(Order $order): ?string
    {
        $status = $this->getStatusFromWebhook($order);
        if (empty($status)) {
            $status = $this->getStatusFromApi($order);
        }
        return $status;
    }

    /**
     * Cancels the order and restores the quote, so the customer can retry the checkout
     *
     * @param Order $order
     * @return void
     */
    private function cancelOrderAndRestoreQuote(Order $order): void
    {
        if ($order->canCancel()) {
            $order->cancel();
            $this->orderRepository->save($order);
        }
        $this->checkoutSession->restoreQuote();
    }

    /**
     * Redirects to the checkout success page
     *
     * @return Redirect
     */
    private function handleSuccessState(): Redirect
    {
        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }

    /**
     * Payment is still in progress - keep the order open, the final status is delivered by webhook
     *
     * The webhook observer authorizes or cancels the order
     *
     * @return Redirect
     */
    private function handlePendingState(): Redirect
    {
        $this->messageManager->addNoticeMessage(__('Your payment is still being processed. You will receive a confirmation as soon as the payment is completed.'));
        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }

    /**
     * Cancels the order and redirects back to the payment step with a cancellation message
     *
     * @param Order $order
     * @return Redirect
     */
    private function handleCancelState(Order $order): Redirect
    {
        $this->cancelOrderAndRestoreQuote($order);
        // The checkout page does not render session messages (page.messages is removed in checkout_index_index.xml),
        // so the message is handed to the checkout JS via ConfigProvider
        $this->checkoutSession->setPayoneCpCheckoutErrorMessage((string)__('The payment has been cancelled.'));
        return $this->resultRedirectFactory->create()->setPath('checkout', ['_fragment' => 'payment']);
    }

    /**
     * Cancels the order and redirects to the cart with an error message
     *
     * @param Order $order
     * @return Redirect
     */
    private function handleErrorState(Order $order): Redirect
    {
        $this->cancelOrderAndRestoreQuote($order);
        $this->messageManager->addErrorMessage(__('An error occurred while processing your payment. Please try again.'));
        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }
}
