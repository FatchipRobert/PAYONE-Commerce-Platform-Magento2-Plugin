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

namespace Payone\CommercePlatform\Helper;

use Payone\CommercePlatform\Model\Payone;

class Payment extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * List of all currently available Payone payment methods
     *
     * @var array
     */
    protected $availablePayments = [
        Payone::METHOD_CREDITCARD,
        Payone::METHOD_DIRECT_DEBIT,
        Payone::METHOD_PAYPAL,
        Payone::METHOD_GOOGLEPAY,
    ];

    /**
     * Return all available payment types
     *
     * @return array
     */
    public function getAvailablePaymentTypes()
    {
        return $this->availablePayments;
    }
}
