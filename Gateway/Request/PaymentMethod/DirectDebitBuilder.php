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
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * SEPA direct debit specific input: the mandate with the bank account
 */
class DirectDebitBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param GeneralConfig $generalConfig
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly GeneralConfig $generalConfig,
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

        $bankAccount = [
            'iban' => $payment->getAdditionalInformation('iban'),
            'accountHolder' => $payment->getAdditionalInformation('accountholder'),
        ];
        if ($this->generalConfig->getPaymentValue($payment->getMethod(), 'request_bic', $payment->getOrder()->getStoreId())) {
            $bankAccount['bic'] = $payment->getAdditionalInformation('bic');
        }

        return [
            'productSpecificInput' => [
                'mandate' => [
                    'recurrenceType' => 'UNIQUE',
                    'bankAccountIban' => $bankAccount,
                    'dateOfSignature' => '20220101', ///@TODO: KLÄREN!!! Parameter von Playground übernommen
                    'creditorId' => 'DE98ZZZ09999999999', ///@TODO: KLÄREN!!! Parameter von Playground übernommen
                    'uniqueMandateReference' => date('YmdHis'), ///@TODO: KLÄREN!!! Parameter von Playground übernommen
                ],
            ],
        ];
    }
}
