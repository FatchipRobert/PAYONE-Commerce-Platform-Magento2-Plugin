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
 * PAYONE Secured Invoice specific input (financingPaymentMethodSpecificInput, product 3390)
 *
 * Only the capture mode: requiresApproval = true waits for the capture of the invoice in the admin,
 * false captures directly (invoice via payment.captured webhook). Customer data and device token,
 * which are mandatory for the method, are added by CommerceCaseBuilder and OrderBuilder.
 */
class SecuredInvoiceBuilder implements BuilderInterface
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
        ];
    }
}
