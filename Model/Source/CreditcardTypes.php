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
declare(strict_types=1);

namespace Payone\CommercePlatform\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Card brands of the credit card setting "types" - the keys of Creditcard::CARD_SCHEMES
 */
class CreditcardTypes implements OptionSourceInterface
{
    private const TYPES = [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'americanexpress' => 'American Express',
        'dinersclub' => 'Diners Club',
    ];

    /**
     * Return the card brands
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::TYPES as $id => $name) {
            $options[] = [
                'value' => $id,
                'label' => $name,
            ];
        }
        return $options;
    }
}
