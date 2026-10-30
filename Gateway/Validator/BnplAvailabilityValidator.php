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

namespace Payone\CommercePlatform\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * Availability of the PAYONE Buy Now Pay Later methods ("availability" validator of the facade)
 *
 * PAYONE only accepts EUR and a shipping address identical to the billing address.
 * The countries (DE, AT) are restricted by allowspecific/specificcountry in etc/config.xml.
 * Called by Adapter::isAvailable() for the method list and again when the order is placed.
 */
class BnplAvailabilityValidator extends AbstractValidator
{
    private const CURRENCY = 'EUR';

    /**
     * Address fields which must be identical in billing and shipping address
     */
    private const ADDRESS_FIELDS = ['firstname', 'lastname', 'company', 'street', 'postcode', 'city', 'country_id'];

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly SubjectReader $subjectReader,
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * Validates the quote of the payment, payments without quote (e.g. of existing orders) are valid
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $payment = $this->subjectReader->readPayment($validationSubject)->getPayment();
        if (!$payment instanceof QuotePayment || !$payment->getQuote()) {
            return $this->createResult(true);
        }

        $quote = $payment->getQuote();
        return $this->createResult($quote->getQuoteCurrencyCode() === self::CURRENCY && $this->hasSameAddresses($quote));
    }

    /**
     * Whether billing and shipping address are identical, virtual quotes have no shipping address
     *
     * @param Quote $quote
     * @return bool
     */
    private function hasSameAddresses(Quote $quote): bool
    {
        if ($quote->isVirtual()) {
            return true;
        }

        $billingAddress = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress->getSameAsBilling() || empty($billingAddress->getCountryId())) {
            return true; // no billing address entered yet - checked again when the order is placed
        }

        return $this->getComparableData($billingAddress) === $this->getComparableData($shippingAddress);
    }

    /**
     * Returns the compared fields of an address, normalized
     *
     * @param Address $address
     * @return array
     */
    private function getComparableData(Address $address): array
    {
        $data = [];
        foreach (self::ADDRESS_FIELDS as $field) {
            $data[$field] = mb_strtolower(trim((string)$address->getData($field)));
        }
        return $data;
    }
}
