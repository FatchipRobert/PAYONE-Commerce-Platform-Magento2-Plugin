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
 * PayPal specific input (redirect payment method)
 */
class PayPalBuilder implements BuilderInterface
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
     */
    public function build(array $buildSubject): array
    {
        $payment = $this->subjectReader->readPayment($buildSubject)->getPayment();
        $order = $payment->getOrder();
        $isAutomaticCapture = (bool)$payment->getMethodInstance()->getConfigData('automatic_capture', $order->getStoreId());

        return [
            'requiresApproval' => !$isAutomaticCapture,
            'tokenize' => false,
            'productSpecificInput' => [
                'addressSelectionAtPayPal' => false,
            ],
            'redirectionData' => [
                'returnUrl' => $this->returnUrl->get($order),
            ],
        ];
    }
}
