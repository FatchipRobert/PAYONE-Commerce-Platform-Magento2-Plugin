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
 * Source class for existing text cases
 */
class ButtonTextCase implements OptionSourceInterface
{
    /**
     * Return existing operation modes
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'capitalize',
                'label' => __('Capitalize'),
            ],
            [
                'value' => 'uppercase',
                'label' => __('Uppercase')
            ]
        ];
    }
}
