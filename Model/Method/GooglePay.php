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

namespace Payone\CommercePlatform\Model\Method;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Model\Payone;
use Psr\Log\LoggerInterface;

/**
 * Google Pay (mobile payment method, redirect method because of a possible 3DS redirect)
 *
 * encryptedPaymentData is the complete token JSON of Google (paymentMethodData.tokenizationData.token) base64 encoded
 * (PCP docs) - the raw JSON is rejected with INVALID_GOOGLE_PAY_TOKEN.
 */
class GooglePay extends PayoneMethod
{
    protected const CODE = Payone::METHOD_GOOGLEPAY;
    protected const PAYONE_METHOD_TYPE = 'mobilePaymentMethod';
    protected const PAYONE_PRODUCT_ID = 320;
    protected const ADDITIONAL_DATA_KEYS = ['payment_token'];

    private const DEFAULT_STORE_NAME = 'Online Store';

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
     * @param GeneralConfig $generalConfig
     * @param ReturnUrl $returnUrl
     * @param Session $checkoutSession proxy (etc/di.xml)
     * @param ValidatorPoolInterface|null $validatorPool
     */
    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        string $formBlockType,
        string $infoBlockType,
        CommandPoolInterface $commandPool,
        LoggerInterface $logger,
        private readonly GeneralConfig $generalConfig,
        private readonly ReturnUrl $returnUrl,
        private readonly Session $checkoutSession,
        ?ValidatorPoolInterface $validatorPool = null,
    ) {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $logger,
            $validatorPool
        );
    }

    /**
     * Gateway merchant id and store name for the Google Pay JS
     *
     * @return array
     */
    public function getFrontendConfig(): array
    {
        return [
            'merchantId' => $this->generalConfig->getMerchantId(),
            'storeName' => $this->getStoreName(),
            'googlePayMerchantId' => $this->generalConfig->getPaymentValue($this->getCode(), 'google_pay_merchant_id'),
            'operationMode' => $this->generalConfig->getEnvironment(),
        ];
    }

    /**
     * Google Pay specific input: encrypted token, capture mode, 3DS return URL
     *
     * @param Payment $payment
     * @return array
     */
    public function getSpecificInput(Payment $payment): array
    {
        $order = $payment->getOrder();

        return [
            'encryptedPaymentData' => base64_encode((string)$payment->getAdditionalInformation('payment_token')),
            'authorizationMode' => $this->isAutomaticCapture($order->getStoreId()) ? 'SALE' : 'PRE_AUTHORIZATION',
            'threeDSecure' => [
                'redirectionData' => [
                    'returnUrl' => $this->returnUrl->get($order),
                ],
            ],
        ];
    }

    /**
     * Returns the configured store name, the frontend name of the store as fallback
     *
     * @return string
     */
    private function getStoreName(): string
    {
        $storeName = (string)$this->generalConfig->getPaymentValue($this->getCode(), 'store_name');
        if ($storeName === '') {
            $storeName = (string)$this->checkoutSession->getQuote()->getStore()->getFrontendName();
        }
        return $storeName !== '' ? $storeName : self::DEFAULT_STORE_NAME;
    }
}
