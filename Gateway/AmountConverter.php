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

namespace Payone\CommercePlatform\Gateway;

/**
 * Converts amounts into the format of the API: integer in the smallest currency unit (cents)
 */
class AmountConverter
{
    /**
     * Converts e.g. 70.21 to 7021
     *
     * @param float $amount
     * @return int
     */
    public function toMinorUnits(float $amount): int
    {
        return (int)round($amount * 100);
    }
}
