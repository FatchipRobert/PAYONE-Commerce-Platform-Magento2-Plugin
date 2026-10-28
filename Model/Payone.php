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

namespace Payone\CommercePlatform\Model;

/**
 * Collection of constant values
 */
abstract class Payone
{
    /* Module version */
    public const MODULE_VERSION = '1.0.0';

    /* PAYMENT METHOD IDENTIFIERS */

    public const METHOD_CREDITCARD = 'payone_cp_creditcard';
    public const METHOD_DIRECT_DEBIT = 'payone_cp_directdebit';
    public const METHOD_PAYPAL = 'payone_cp_paypal';
    public const METHOD_GOOGLEPAY = 'payone_cp_googlepay';

    /* METHOD TYPES */

    public const PAYMENT_METHOD_TYPE_CARD = 'cardPaymentMethod';
    public const PAYMENT_METHOD_TYPE_MOBILE = 'mobilePaymentMethod';
    public const PAYMENT_METHOD_TYPE_REDIRECT = 'redirectPaymentMethod';
    public const PAYMENT_METHOD_TYPE_SEPA = 'sepaDirectDebitPaymentMethod';
    public const PAYMENT_METHOD_TYPE_FINANCING = 'financingPaymentMethod';

    /* TRANSACTIONS */

    /* Suffix of the Magento capture transaction id for automatic captures (SALE event at PAYONE, no separate capture id) */
    public const TRANSACTION_SUFFIX_SALE = '-sale';
}
