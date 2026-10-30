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
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Payone\CommercePlatform\Gateway\Request\Mapper\CustomerMapper;

/**
 * Checks the customer data PAYONE Buy Now Pay Later needs ("global" validator of the facade)
 *
 * Called by Adapter::validate() whenever payment data is assigned to the quote and again when the order is placed.
 * Only the order payment is checked: Magento also assigns the bare method code without the checkout input
 * (set-payment-information when the method is selected or the billing address changes).
 * Date of birth and phone number come from the checkout input or from the customer account / billing address.
 */
class BnplCustomerDataValidator extends AbstractValidator
{
    private const MINIMUM_AGE = 18;

    /**
     * Validates the payment in the subject (the info instance, not a payment data object)
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $payment = $validationSubject['payment'] ?? null;
        if (!$payment instanceof OrderPayment) {
            return $this->createResult(true);
        }
        $order = $payment->getOrder();

        $errors = [];
        $dateOfBirth = $payment->getAdditionalInformation(CustomerMapper::KEY_DATE_OF_BIRTH) ?: $order->getCustomerDob();
        if (!$this->isAdult((string)$dateOfBirth)) {
            $errors[] = __('Please enter a valid date of birth. You must be at least 18 years old to use this payment method.');
        }

        $billingAddress = $order->getBillingAddress();
        $telephone = $payment->getAdditionalInformation(CustomerMapper::KEY_TELEPHONE)
            ?: ($billingAddress ? $billingAddress->getTelephone() : null);
        if (empty(trim((string)$telephone))) {
            $errors[] = __('Please enter your phone number.');
        }

        return $this->createResult(empty($errors), $errors);
    }

    /**
     * Whether the date of birth (YYYY-MM-DD, optionally with time) is valid and at least MINIMUM_AGE years ago
     *
     * @param string $dateOfBirth
     * @return bool
     */
    private function isAdult(string $dateOfBirth): bool
    {
        $date = \DateTime::createFromFormat('!Y-m-d', substr($dateOfBirth, 0, 10));
        if ($date === false || $date->format('Y-m-d') !== substr($dateOfBirth, 0, 10)) {
            return false;
        }
        return $date <= new \DateTime('-'.self::MINIMUM_AGE.' years') && $date->format('Y') >= 1900;
    }
}
