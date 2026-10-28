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

class Api extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Formats amount for API
     *
     * @param double $amount
     * @return float|int
     */
    public function formatAmount($amount)
    {
        $decimalMultiplier = 100;

        return number_format($amount * $decimalMultiplier, 0, '.', '');
    }
}
