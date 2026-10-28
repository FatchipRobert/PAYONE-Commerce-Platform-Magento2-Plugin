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

namespace Payone\CommercePlatform\Controller\Onepage;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\Order;

class Returned extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
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
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var \Payone\CommercePlatform\Model\Api\Request\GetCheckoutDetailsFactory
     */
    protected $getCheckoutDetailsFactory;

    /**
     * @var \Payone\CommercePlatform\Model\ResourceModel\WebhookMessage\CollectionFactory
     */
    protected $webhookMessageCollectionFactory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory
     * @param \Payone\CommercePlatform\Model\Api\Request\GetCheckoutDetailsFactory $getCheckoutDetailsFactory
     * @param \Payone\CommercePlatform\Model\ResourceModel\WebhookMessage\CollectionFactory $webhookMessageCollectionFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Payone\CommercePlatform\Model\Api\Request\GetCheckoutDetailsFactory $getCheckoutDetailsFactory,
        \Payone\CommercePlatform\Model\ResourceModel\WebhookMessage\CollectionFactory $webhookMessageCollectionFactory
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->getCheckoutDetailsFactory = $getCheckoutDetailsFactory;
        $this->webhookMessageCollectionFactory = $webhookMessageCollectionFactory;
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
     * Returns the payment status of the latest received webhook for the order
     *
     * The webhook may arrive shortly after the customer returned, so the lookup is retried a few times
     *
     * @param  Order $order
     * @param  int   $retryCount
     * @return string|null
     */
    protected function getStatusFromWebhook(Order $order, $retryCount = 0)
    {
        /** @var \Payone\CommercePlatform\Model\ResourceModel\WebhookMessage\Collection $collection */
        $collection = $this->webhookMessageCollectionFactory->create();
        $collection->addFieldToFilter('payment_id', $order->getData('payone_cp_payment_id'))
            ->setOrder('date', 'DESC');

        if ($collection->getSize() > 0) {
            $webhookMessage = $collection->getFirstItem();
            return $webhookMessage->getStatus();
        }

        if ($retryCount < self::RETRY_LIMIT) { // retry
            usleep(self::RETRY_MILLISECONDS_SLEEP * 1000);
            return $this->getStatusFromWebhook($order, ++$retryCount);
        }
        return null;
    }

    /**
     * Returns the payment status of the last event of the latest payment execution from GetCheckoutDetails
     *
     * @param  Order $order
     * @return string|null
     */
    protected function getStatusFromAPI(Order $order)
    {
        $getCheckoutDetails = $this->getCheckoutDetailsFactory->create();
        $getCheckoutDetails->populateParameters($order);

        $response = $getCheckoutDetails->sendRequest([
            'commerceCaseId' => $order->getData('payone_cp_commerce_case_id'),
            'checkoutId' => $order->getData('payone_cp_checkout_id'),
        ]);

        $status = null;
        $paymentExecutions = $response->getResponse()['paymentExecutions'] ?? null;
        if (!empty($paymentExecutions) && is_array($paymentExecutions)) {
            $paymentExecution = end($paymentExecutions);
            if (!empty($paymentExecution['events']) && is_array($paymentExecution['events'])) {
                $events = $paymentExecution['events'];
                $lastEvent = end($events);
                if (!empty($lastEvent['paymentStatus'])) {
                    $status = $lastEvent['paymentStatus'];
                }
            }
        }

        return $status;
    }

    /**
     * Determines the payment status - from the received webhooks first, from the API as fallback
     *
     * @param Order $order
     * @return string|null
     */
    protected function getPaymentStatus(Order $order)
    {
        $status = $this->getStatusFromWebhook($order);
        if (empty($status)) {
            $status = $this->getStatusFromAPI($order);
        }
        return $status;
    }

    /**
     * Cancels the order and restores the quote, so the customer can retry the checkout
     *
     * @param Order $order
     * @return void
     */
    protected function cancelOrderAndRestoreQuote(Order $order)
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
     * @param Order $order
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function handleSuccessState(Order $order)
    {
        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }

    /**
     * Payment is still in progress - keep the order open, the final status is delivered by webhook
     *
     * The webhook observer authorizes or cancels the order
     *
     * @param Order $order
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function handlePendingState(Order $order)
    {
        $this->messageManager->addNoticeMessage(__('Your payment is still being processed. You will receive a confirmation as soon as the payment is completed.'));
        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }

    /**
     * Cancels the order and redirects back to the payment step with a cancellation message
     *
     * @param Order $order
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function handleCancelState(Order $order)
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
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function handleErrorState(Order $order)
    {
        $this->cancelOrderAndRestoreQuote($order);
        $this->messageManager->addErrorMessage(__('An error occurred while processing your payment. Please try again.'));
        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }

    /**
     * Redirect to success page
     *
     * Do whatever processing is needed after successful return from payment-provider
     *
     * @return void
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order->getId()) {
            return $this->handleErrorState($order);
        }

        $status = $this->getPaymentStatus($order);

        if (in_array($status, self::STATUS_LIST_SUCCESS) === true) {
            return $this->handleSuccessState($order);
        }

        if (in_array($status, self::STATUS_LIST_CANCEL) === true) {
            return $this->handleCancelState($order);
        }

        // Intermediate status (CREATED, REDIRECTED, PENDING_PAYMENT, PENDING_COMPLETION, AUTHORIZATION_REQUESTED,
        // CAPTURE_REQUESTED), unknown status or status not determinable (no webhook yet, API error):
        // the payment may still succeed, so the order must not be cancelled here
        return $this->handlePendingState($order);
    }
}
