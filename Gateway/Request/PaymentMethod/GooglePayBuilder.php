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

use Magento\Payment\Gateway\Request\BuilderInterface;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * Google Pay specific input (mobile payment method): encrypted token from the Google Pay JS, 3DS return URL
 */
class GooglePayBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param GeneralConfig $generalConfig
     * @param ReturnUrl $returnUrl
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly GeneralConfig $generalConfig,
        private readonly ReturnUrl $returnUrl,
    ) {
    }

    /**
     * Builds the method specific content
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $payment = $this->subjectReader->readPayment($buildSubject)->getPayment();
        $order = $payment->getOrder();
        $isAutomaticCapture = (bool)$this->generalConfig->getPaymentValue(
            $payment->getMethod(),
            'automatic_capture',
            $order->getStoreId()
        );

        return [
            'encryptedPaymentData' => $payment->getAdditionalInformation('payment_token'),
            'authorizationMode' => $isAutomaticCapture ? 'SALE' : 'PRE_AUTHORIZATION',
            'threeDSecure' => [
                'redirectionData' => [
                    'returnUrl' => $this->returnUrl->get($order),
                ],
            ],
        ];
    }
}
