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

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * SEPA direct debit specific input: the mandate with the bank account
 *
 * PAYONE has no mandate management yet - a new UNIQUE mandate is sent with every payment. recurrenceType,
 * uniqueMandateReference, dateOfSignature and creditorId are mandatory "to ensure compatibility with the planned
 * introduction of mandate management" (PCP docs).
 */
class DirectDebitBuilder implements BuilderInterface
{
    /**
     * uniqueMandateReference: max. 35 characters of A-Z a-z 0-9 + - . ( )
     */
    private const MANDATE_REFERENCE_MAX_LENGTH = 35;
    private const MANDATE_REFERENCE_INVALID_CHARS = '/[^A-Za-z0-9+\-.()]/';

    /**
     * @param SubjectReader $subjectReader
     * @param GeneralConfig $generalConfig
     * @param MerchantReference $merchantReference
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly GeneralConfig $generalConfig,
        private readonly MerchantReference $merchantReference,
        private readonly TimezoneInterface $timezone,
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

        $bankAccount = [
            'iban' => $payment->getAdditionalInformation('iban'),
            'accountHolder' => $payment->getAdditionalInformation('accountholder'),
        ];
        if ($this->generalConfig->getPaymentValue($payment->getMethod(), 'request_bic', $order->getStoreId())) {
            $bankAccount['bic'] = $payment->getAdditionalInformation('bic');
        }

        return [
            'productSpecificInput' => [
                'mandate' => [
                    'recurrenceType' => 'UNIQUE',
                    'bankAccountIban' => $bankAccount,
                    // The customer "signs" the mandate by placing the order (not saved yet, so no created_at)
                    'dateOfSignature' => $this->timezone->date(null, null, true)->format('Ymd'),
                    'creditorId' => 'DE98ZZZ09999999999', ///@TODO: KLÄREN!!! Parameter von Playground übernommen
                    'uniqueMandateReference' => $this->getMandateReference($order),
                ],
            ],
        ];
    }

    /**
     * Mandate reference of the order: "md" + reference prefix + increment id, unique like the other references
     *
     * Characters not allowed in a mandate reference (e.g. "_" in the configured prefix) are removed.
     *
     * @param Order $order
     * @return string
     */
    private function getMandateReference(Order $order): string
    {
        $reference = $this->merchantReference->get($order, MerchantReference::PREFIX_MANDATE);
        $reference = (string)preg_replace(self::MANDATE_REFERENCE_INVALID_CHARS, '', $reference);
        return substr($reference, 0, self::MANDATE_REFERENCE_MAX_LENGTH);
    }
}
