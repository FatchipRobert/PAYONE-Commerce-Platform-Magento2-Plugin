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

namespace Payone\CommercePlatform\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source class for existing creditcard types
 */
class CreditcardTypes implements OptionSourceInterface
{
    /**
     * Creditcard types
     */
    private const TYPES = [
        'visa'              => ['name' => 'Visa',                  'cardtype' => 'V', 'cvc_length' => 3],
        'mastercard'        => ['name' => 'Mastercard',            'cardtype' => 'M', 'cvc_length' => 3],
        'americanexpress'   => ['name' => 'American Express',      'cardtype' => 'A', 'cvc_length' => 4],
        'dinersclub'        => ['name' => 'Diners Club',           'cardtype' => 'D', 'cvc_length' => 3],
    ];

    /**
     * Return available creditcard type array
     *
     * @return array
     */
    public function getCreditcardTypes(): array
    {
        return self::TYPES;
    }

    /**
     * Return existing creditcard types
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::TYPES as $id => $type) {
            $options[] = [
                'value' => $id,
                'label' => $type['name'],
            ];
        }
        return $options;
    }
}
