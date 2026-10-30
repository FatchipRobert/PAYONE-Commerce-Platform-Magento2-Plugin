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

namespace Payone\CommercePlatform\Block\Info;

use Magento\Framework\DataObject;
use Magento\Payment\Block\Info;

/**
 * Payment info block with the payment instructions of PAYONE Secured Invoice
 *
 * The customer pays to the bank account of PAYONE, so the instructions are shown wherever the payment info
 * is rendered: order view (admin, customer account), order/invoice mails and invoice PDF.
 * Stored by Gateway\Response\PaymentInstructionsHandler once the payment is captured.
 */
class PaymentInstructions extends Info
{
    public const KEY_PAYEE_NAME = 'payone_cp_payee_name';
    public const KEY_PAYEE_IBAN = 'payone_cp_payee_iban';
    public const KEY_PAYEE_BIC = 'payone_cp_payee_bic';
    public const KEY_REFERENCE_NUMBER = 'payone_cp_reference_number';
    public const KEY_DUE_DATE = 'payone_cp_due_date';

    /**
     * Shown fields: additional_information key => label
     */
    private const FIELDS = [
        self::KEY_PAYEE_NAME => 'Account holder',
        self::KEY_PAYEE_IBAN => 'IBAN',
        self::KEY_PAYEE_BIC => 'BIC',
        self::KEY_REFERENCE_NUMBER => 'Payment reference',
        self::KEY_DUE_DATE => 'Due date',
    ];

    /**
     * Adds the stored payment instructions to the specific information
     *
     * @param DataObject|array|null $transport
     * @return DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $payment = $this->getInfo();

        foreach (self::FIELDS as $key => $label) {
            $value = (string)$payment->getAdditionalInformation($key);
            if ($value !== '') {
                $transport->setData((string)__($label), $key === self::KEY_DUE_DATE ? $this->formatDueDate($value) : $value);
            }
        }

        return $transport;
    }

    /**
     * Formats the due date (YYYYMMDD) in the locale of the store
     *
     * @param string $dueDate
     * @return string
     */
    private function formatDueDate(string $dueDate): string
    {
        $date = \DateTime::createFromFormat('!Ymd', $dueDate);
        if ($date === false) {
            return $dueDate;
        }
        return $this->_localeDate->formatDateTime($date, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, null, 'UTC');
    }
}
