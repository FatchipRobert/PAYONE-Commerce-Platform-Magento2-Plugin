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

namespace Payone\CommercePlatform\Gateway\Request\PaymentMethod;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * Apple Pay specific input (mobile payment method): payment token of the Apple Pay JS (ApplePayPayment.token,
 * decrypted by PAYONE), 3DS return URL
 */
class ApplePayBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param ReturnUrl $returnUrl
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly ReturnUrl $returnUrl,
    ) {
    }

    /**
     * Builds the method specific content
     *
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        $payment = $this->subjectReader->readPayment($buildSubject)->getPayment();
        $order = $payment->getOrder();
        $isAutomaticCapture = (bool)$payment->getMethodInstance()->getConfigData('automatic_capture', $order->getStoreId());

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
            'authorizationMode' => $isAutomaticCapture ? 'SALE' : 'PRE_AUTHORIZATION',
            'threeDSecure' => [
                'redirectionData' => [
                    'returnUrl' => $this->returnUrl->get($order),
                ],
            ],
            'productSpecificInput' => [
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
