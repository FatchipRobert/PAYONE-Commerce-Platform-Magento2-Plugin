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

namespace Payone\CommercePlatform\Model\Method;

use Magento\Payment\Model\Method\Adapter;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Exception\LocalizedException;

abstract class BaseMethod extends Adapter
{
    /**
     * @var \Payone\CommercePlatform\Gateway\Config\GeneralConfig
     */
    protected $generalConfig;

    /**
     * @var \Payone\CommercePlatform\Model\Api\Request\OneStepFactory
     */
    protected $oneStepRequestFactory;

    /**
     * URL helper
     *
     * @var \Magento\Framework\Url
     */
    protected $url;

    /**
     * Checkout session model
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * Determines if payment is set to pending_payment instead of direct authorization
     *
     * @var bool
     */
    protected $isPaymentPendingMethod = false;

    /**
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Payment\Gateway\Config\ValueHandlerPoolInterface $valueHandlerPool
     * @param \Magento\Payment\Gateway\Data\PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $code
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
     * @param \Payone\CommercePlatform\Model\Api\Request\OneStepFactory $oneStepRequestFactory
     * @param \Magento\Framework\Url $url
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Payment\Gateway\Command\CommandPoolInterface|null $commandPool
     * @param \Magento\Payment\Gateway\Validator\ValidatorPoolInterface|null $validatorPool
     * @param \Magento\Payment\Gateway\Command\CommandManagerInterface|null $commandExecutor
     * @param \Psr\Log\LoggerInterface|null $logger
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Payment\Gateway\Config\ValueHandlerPoolInterface $valueHandlerPool,
        \Magento\Payment\Gateway\Data\PaymentDataObjectFactory $paymentDataObjectFactory,
        $code,
        $formBlockType,
        $infoBlockType,
        \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig,
        \Payone\CommercePlatform\Model\Api\Request\OneStepFactory $oneStepRequestFactory,
        \Magento\Framework\Url $url,
        \Magento\Checkout\Model\Session $checkoutSession,
        ?\Magento\Payment\Gateway\Command\CommandPoolInterface $commandPool = null,
        ?\Magento\Payment\Gateway\Validator\ValidatorPoolInterface $validatorPool = null,
        ?\Magento\Payment\Gateway\Command\CommandManagerInterface $commandExecutor = null,
        ?\Psr\Log\LoggerInterface $logger = null
    ) {
        if (empty($this->methodCode)) {
            throw new \LogicException("MethodCode is empty!");
        }
        $code = $this->methodCode;

        parent::__construct($eventManager, $valueHandlerPool, $paymentDataObjectFactory, $code, $formBlockType, $infoBlockType, $commandPool, $validatorPool, $commandExecutor, $logger);

        $this->generalConfig = $generalConfig;
        $this->oneStepRequestFactory = $oneStepRequestFactory;
        $this->url = $url;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Checks if the order stays in pending_payment until the payment is completed (redirect methods)
     *
     * @return bool
     */
    public function isPendingPaymentMethod()
    {
        return $this->isPaymentPendingMethod;
    }

    /**
     * Pending payment methods send the payment request in initialize() instead of authorize()
     *
     * @return bool
     */
    public function isInitializeNeeded()
    {
        return $this->isPendingPaymentMethod();
    }

    /**
     * Instantiate state and set it to state object
     *
     * @param string $paymentAction
     * @param \Magento\Framework\DataObject $stateObject
     * @return void
     */
    public function initialize($paymentAction, $stateObject)
    {
        parent::initialize($paymentAction, $stateObject);

        if ($stateObject !== null) {
            $stateObject->setData('state', \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $stateObject->setData('status', 'pending_payment');
        }

        $this->handlePayonePayment();
    }

    /**
     * @inheritdoc
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new LocalizedException(__('The authorize action is not available.'));
        }

        if ($this->isInitializeNeeded() === false) {
            $this->handlePayonePayment($payment, $amount);
        }

        return $this;
    }

    /**
     * Sends the OneStep request (commerce case + checkout + order) for the payment
     *
     * @param  InfoInterface $payment
     * @param  float         $amount
     * @return \Payone\CommercePlatform\Model\Api\Response
     * @throws LocalizedException
     */
    protected function handlePaymentRequest(InfoInterface $payment, $amount)
    {
        $oneStepRequest = $this->oneStepRequestFactory->create();
        $oneStepRequest->populateParameters($payment, $amount);
        $response = $oneStepRequest->sendRequest();

        if ($response->isSuccessResponse() === false) {
            throw new LocalizedException(__($response->getFirstErrorMessage()));
        }

        return $response;
    }

    /**
     * Removes data of a previous payment attempt from the checkout session
     *
     * @return void
     */
    protected function unsetSessionFlags()
    {
        $this->checkoutSession->unsPayoneRedirectUrl();
    }

    /**
     * Sends the payment request and stores the returned PAYONE ids on the order
     *
     * @param InfoInterface|null $payment
     * @param float|null         $amount
     * @return void
     */
    protected function handlePayonePayment(?InfoInterface $payment = null, $amount = null)
    {
        if ($payment === null) {
            $payment = $this->getInfoInstance();
        }

        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        if ($amount === null) {
            $amount = $order->getTotalDue();
        }

        $this->unsetSessionFlags();

        $response = $this->handlePaymentRequest($payment, $amount);

        $paymentId = $response->findData('paymentId');
        if (!empty($paymentId)) {
            $order->setData('payone_cp_commerce_case_id', $response->findData('commerceCaseId'));
            $order->setData('payone_cp_checkout_id', $response->findData('checkoutId'));
            $order->setData('payone_cp_payment_id', $paymentId);
            $order->setData('payone_cp_payment_execution_id', $response->findData('paymentExecutionId'));

            $payment->setTransactionId($paymentId);
            $payment->setIsTransactionClosed(0);
            if (!empty($response->findData('redirectURL'))) {
                $this->checkoutSession->setPayoneRedirectUrl($response->findData('redirectURL'));
            }
        }
    }

    /**
     * Completes the authorization of a pending payment after the successful webhook
     *
     * @param InfoInterface $payment
     * @param string $transId
     * @return void
     */
    public function authorizePayment(InfoInterface $payment, $transId)
    {
        $order = $payment->getOrder();
        $baseTotalDue = $order->getBaseTotalDue();

        $this->setTransactionId($payment, $transId, true);

        $payment->authorize(true, $baseTotalDue);
    }
}
