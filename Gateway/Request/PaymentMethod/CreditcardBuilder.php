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
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * Card specific input: token from the hosted tokenization form, card holder, 3DS return URL
 */
class CreditcardBuilder implements BuilderInterface
{
    /**
     * PAYONE payment product ids of the card types of the tokenization form
     */
    public const PAYMENT_PRODUCT_IDS = [
        'VISA' => 1,
        'AMERICAN_EXPRESS' => 2,
        'MASTERCARD' => 3,
        'DINERS' => 123,
    ];

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
     */
    public function build(array $buildSubject): array
    {
        $payment = $this->subjectReader->readPayment($buildSubject)->getPayment();
        $order = $payment->getOrder();
        $isAutomaticCapture = (bool)$payment->getMethodInstance()->getConfigData('automatic_capture', $order->getStoreId());

        $input = [
            'authorizationMode' => $isAutomaticCapture ? 'SALE' : 'PRE_AUTHORIZATION',
            'paymentProcessingToken' => $payment->getAdditionalInformation('pseudocardpan'),
            'transactionChannel' => 'ECOMMERCE',
            'card' => [
                'cardholderName' => $payment->getAdditionalInformation('cardholder'),
            ],
            'returnUrl' => $this->returnUrl->get($order),
        ];

        $cardType = (string)$payment->getAdditionalInformation('cardtype');
        if (isset(self::PAYMENT_PRODUCT_IDS[$cardType])) {
            $input['paymentProductId'] = self::PAYMENT_PRODUCT_IDS[$cardType];
        }

        return $input;
    }
}
