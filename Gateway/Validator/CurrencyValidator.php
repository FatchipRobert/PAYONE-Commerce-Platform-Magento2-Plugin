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

/**
 * Restricts a payment method to some currencies ("currency" validator of the facade)
 *
 * Called by Adapter::canUseForCurrency() for the payment method list of the checkout and when the order is placed.
 */
class CurrencyValidator extends AbstractValidator
{
    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param string[] $allowedCurrencies ISO 4217 codes
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly array $allowedCurrencies = [],
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * Validates the currency in the subject
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        return $this->createResult(in_array($validationSubject['currency'] ?? null, $this->allowedCurrencies, true));
    }
}
