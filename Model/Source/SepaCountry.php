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
use Payone\CommercePlatform\Model\Country;

/**
 * Source class for existing SEPA countries
 */
class SepaCountry implements OptionSourceInterface
{
    /**
     * All SEPA countries
     */
    private const SEPA_COUNTRIES = [
        "AX", "AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "GF", "DE", "GI",
        "GR", "GP", "HU", "IS", "IE", "IT", "LV", "LI", "LT", "LU", "MT", "MQ", "YT", "MC",
        "NL", "NO", "PL", "PT", "RE", "RO", "BL", "MF", "PM", "SK", "SI", "ES", "SE", "CH", "GB"
    ];

    /**
     * @param Country $country
     */
    public function __construct(
        private readonly Country $country,
    ) {
    }

    /**
     * Return existing SEPA countries sorted alphabetically
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::SEPA_COUNTRIES as $countryCode) {
            $name = $this->country->getName($countryCode);
            if ($name !== null) {
                $options[$name] = [
                    'value' => $countryCode,
                    'label' => $name,
                ];
            }
        }
        ksort($options);
        return $options;
    }
}
