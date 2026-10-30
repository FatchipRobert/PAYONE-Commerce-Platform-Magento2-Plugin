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
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * PAYONE Secured Direct Debit specific input (financingPaymentMethodSpecificInput, product 3392)
 *
 * Capture mode like SecuredInvoiceBuilder plus the bank account (paymentProduct3392SpecificInput).
 * The BIC is only needed for countries where it cannot be derived from the IBAN (Switzerland), not for DE/AT.
 */
class SecuredDirectDebitBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
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
        $storeId = $payment->getOrder()->getStoreId();

        return [
            'requiresApproval' => !$payment->getMethodInstance()->getConfigData('automatic_capture', $storeId),
            'productSpecificInput' => [
                'bankAccountInformation' => [
                    'iban' => $payment->getAdditionalInformation('iban'),
                    'accountHolder' => $payment->getAdditionalInformation('accountholder'),
                ],
            ],
        ];
    }
}
