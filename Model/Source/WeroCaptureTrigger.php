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
 * Wero capture triggers (paymentProduct900SpecificInput.captureTrigger) - shown to the customer in the Wero app
 */
class WeroCaptureTrigger implements OptionSourceInterface
{
    public const SHIPPING = 'shipping';
    public const DELIVERY = 'delivery';
    public const AVAILABILITY = 'availability';
    public const SERVICE_FULFILLMENT = 'serviceFulfillment';
    public const OTHER = 'other';

    /**
     * Return the capture triggers
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::SHIPPING, 'label' => __('On shipping')],
            ['value' => self::DELIVERY, 'label' => __('On delivery')],
            ['value' => self::AVAILABILITY, 'label' => __('When the order is available')],
            ['value' => self::SERVICE_FULFILLMENT, 'label' => __('When the service is fulfilled')],
            ['value' => self::OTHER, 'label' => __('Other')],
        ];
    }
}
