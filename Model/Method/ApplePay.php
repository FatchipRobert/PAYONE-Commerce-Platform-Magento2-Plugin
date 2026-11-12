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

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Gateway\Config\ApplePayConfig;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Model\Payone;
use Psr\Log\LoggerInterface;

/**
 * Apple Pay (mobile payment method, redirect method because of a possible 3DS redirect)
 *
 * Needs the shop's own Apple Developer account: without merchant identifier, certificate and private key
 * (ApplePayConfig) the method is not available. The payment token of the Apple Pay JS (ApplePayPayment.token) is
 * decrypted by PAYONE.
 */
class ApplePay extends PayoneMethod
{
    protected const CODE = Payone::METHOD_APPLEPAY;
    protected const PAYONE_METHOD_TYPE = 'mobilePaymentMethod';
    protected const PAYONE_PRODUCT_ID = 302;
    protected const ADDITIONAL_DATA_KEYS = ['payment_token'];
    protected const REQUEST_ONLY_DATA_KEYS = ['payment_token'];

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
     * @param ApplePayConfig $applePayConfig
     * @param ReturnUrl $returnUrl
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
        private readonly ApplePayConfig $applePayConfig,
        private readonly ReturnUrl $returnUrl,
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
     * Only available if merchant identifier, certificate and private key are configured
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(?CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote)
            && $this->applePayConfig->isConfigurationComplete($quote ? $quote->getStoreId() : null);
    }

    /**
     * Payment request data for the ApplePaySession
     *
     * @return array
     */
    public function getFrontendConfig(): array
    {
        return [
            'displayName' => $this->applePayConfig->getDisplayName(),
            'countryCode' => $this->applePayConfig->getCountryCode(),
            'supportedNetworks' => $this->applePayConfig->getSupportedNetworks(),
        ];
    }

    /**
     * Apple Pay specific input: fields of the payment token, capture mode, 3DS return URL
     *
     * @param Payment $payment
     * @return array
     * @throws LocalizedException
     */
    public function getSpecificInput(Payment $payment): array
    {
        $order = $payment->getOrder();

        $token = json_decode((string)$payment->getAdditionalInformation('payment_token'), true);
        $paymentData = is_array($token) ? ($token['paymentData'] ?? null) : null;
        if (!is_array($paymentData) || empty($paymentData['data']) || !is_array($paymentData['header'] ?? null)) {
            throw new LocalizedException(__('The Apple Pay payment data is missing. Please try again.'));
        }
        $header = $paymentData['header'];

        return [
            'encryptedPaymentData' => $paymentData['data'],
            'publicKeyHash' => $header['publicKeyHash'] ?? null,
            'ephemeralKey' => $header['ephemeralPublicKey'] ?? null,
            'authorizationMode' => $this->isAutomaticCapture($order->getStoreId()) ? 'SALE' : 'PRE_AUTHORIZATION',
            'threeDSecure' => [
                'redirectionData' => [
                    'returnUrl' => $this->returnUrl->get($order),
                ],
            ],
            'productSpecificInput' => [
                // Own Apple Developer account and certificates; MASS_ENABLEMENT (PAYONE certificate) is not supported yet
                'integrationType' => 'MERCHANT_CERTIFICATE',
                'network' => strtoupper((string)($token['paymentMethod']['network'] ?? '')),
                'token' => [
                    'version' => strtoupper((string)($paymentData['version'] ?? '')), // Apple: EC_v1, PAYONE enum: EC_V1
                    'signature' => $paymentData['signature'] ?? null,
                    'header' => [
                        'transactionId' => $header['transactionId'] ?? null,
                    ],
                ],
            ],
        ];
    }
}
