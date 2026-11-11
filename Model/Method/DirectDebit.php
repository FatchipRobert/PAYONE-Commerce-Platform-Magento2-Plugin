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
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Model\Country;
use Payone\CommercePlatform\Model\Payone;
use Psr\Log\LoggerInterface;

/**
 * SEPA direct debit (direct method, always automatic capture)
 *
 * PAYONE has no mandate management yet - a new UNIQUE mandate is sent with every payment. recurrenceType,
 * uniqueMandateReference, dateOfSignature and creditorId are mandatory "to ensure compatibility with the planned
 * introduction of mandate management" (PCP docs). creditorId is the SEPA creditor identifier from the config.
 */
class DirectDebit extends PayoneMethod
{
    protected const CODE = Payone::METHOD_DIRECT_DEBIT;
    protected const PAYONE_METHOD_TYPE = 'sepaDirectDebitPaymentMethod';
    protected const PAYONE_PRODUCT_ID = 771;
    protected const ADDITIONAL_DATA_KEYS = ['bank_country', 'accountholder', 'iban', 'bic'];

    /**
     * uniqueMandateReference: max. 35 characters of A-Z a-z 0-9 + - . ( )
     */
    private const MANDATE_REFERENCE_MAX_LENGTH = 35;
    private const MANDATE_REFERENCE_INVALID_CHARS = '/[^A-Za-z0-9+\-.()]/';

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
     * @param GeneralConfig $generalConfig
     * @param MerchantReference $merchantReference
     * @param TimezoneInterface $timezone
     * @param Country $country
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
        private readonly MerchantReference $merchantReference,
        private readonly TimezoneInterface $timezone,
        private readonly Country $country,
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
     * The direct debit is always collected directly
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isAutomaticCapture(int|string|null $storeId = null): bool
    {
        return true;
    }

    /**
     * Allowed bank countries, BIC field
     *
     * @return array
     */
    public function getFrontendConfig(): array
    {
        return [
            'sepaCountries' => $this->country->getEnabledCountries($this->getCode()),
            'requestBic' => (bool)$this->generalConfig->getPaymentValue($this->getCode(), 'request_bic'),
        ];
    }

    /**
     * SEPA specific input: the mandate with the bank account
     *
     * @param Payment $payment
     * @return array
     */
    public function getSpecificInput(Payment $payment): array
    {
        $order = $payment->getOrder();

        $bankAccount = [
            'iban' => $payment->getAdditionalInformation('iban'),
            'accountHolder' => mb_substr((string)$payment->getAdditionalInformation('accountholder'), 0, 50), // max. 50
        ];
        if ($this->generalConfig->getPaymentValue($this->getCode(), 'request_bic', $order->getStoreId())) {
            $bankAccount['bic'] = $payment->getAdditionalInformation('bic');
        }

        return [
            'productSpecificInput' => [
                'mandate' => [
                    'recurrenceType' => 'UNIQUE',
                    'bankAccountIban' => $bankAccount,
                    // The customer "signs" the mandate by placing the order (not saved yet, so no created_at)
                    'dateOfSignature' => $this->timezone->date(null, null, true)->format('Ymd'),
                    'creditorId' => (string)$this->generalConfig->getPaymentValue(
                        $this->getCode(),
                        'creditor_id',
                        $order->getStoreId()
                    ),
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
