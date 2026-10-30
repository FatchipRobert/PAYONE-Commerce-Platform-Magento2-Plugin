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
    public const METHOD_SECURED_INVOICE = 'payone_cp_securedinvoice';
    public const METHOD_SECURED_DIRECT_DEBIT = 'payone_cp_secureddirectdebit';

    /* All PAYONE payment methods - check with in_array($code, Payone::METHODS, true) */
    public const METHODS = [
        self::METHOD_CREDITCARD,
        self::METHOD_DIRECT_DEBIT,
        self::METHOD_PAYPAL,
        self::METHOD_GOOGLEPAY,
        self::METHOD_SECURED_INVOICE,
        self::METHOD_SECURED_DIRECT_DEBIT,
    ];

    /* PAYONE Buy Now Pay Later methods (config value payone_method_type): need cancellationReason, device token, IP */
    public const METHOD_TYPE_FINANCING = 'financingPaymentMethod';

    /* TRANSACTIONS */

    /* Suffix of the Magento capture transaction id for automatic captures (SALE event at PAYONE, no separate capture id) */
    public const TRANSACTION_SUFFIX_SALE = '-sale';
}
