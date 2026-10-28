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

namespace Payone\CommercePlatform\Model\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Source class for existing SEPA countries
 */
class SepaCountry implements ArrayInterface
{
    /**
     * All SEPA countries
     *
     * @var array
     */
    protected $sepaCountries = [
        "AX", "AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "GF", "DE", "GI",
        "GR", "GP", "HU", "IS", "IE", "IT", "LV", "LI", "LT", "LU", "MT", "MQ", "YT", "MC",
        "NL", "NO", "PL", "PT", "RE", "RO", "BL", "MF", "PM", "SK", "SI", "ES", "SE", "CH", "GB"
    ];

    /**
     * PAYONE country helper
     *
     * @var \Payone\CommercePlatform\Helper\Country
     */
    protected $countryHelper;

    /**
     * Constructor
     *
     * @param \Payone\CommercePlatform\Helper\Country $countryHelper
     */
    public function __construct(\Payone\CommercePlatform\Helper\Country $countryHelper)
    {
        $this->countryHelper = $countryHelper;
    }

    /**
     * Return existing SEPA countries sorted alphabetically
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        foreach ($this->sepaCountries as $countryCode) {
            $name = $this->countryHelper->getCountryNameByIso2($countryCode);
            if ($name) {
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
