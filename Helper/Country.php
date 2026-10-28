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

/**
 * Helper class for everything that has to do with countries
 */
class Country extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * List of all countries where the state parameter has to be submitted
     *
     * @var array
     */
    protected static $stateNeeded = [
        'US',
        'CA',
        'CN',
        'JP',
        'MX',
        'BR',
        'AR',
        'ID',
        'TH',
        'IN',
    ];

    /**
     * Country object
     *
     * @var \Magento\Directory\Model\Country
     */
    protected $country;

    /**
     * @var \Payone\CommercePlatform\Gateway\Config\GeneralConfig
     */
    protected $generalConfig;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context                 $context
     * @param \Magento\Directory\Model\Country                      $country
     * @param \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Directory\Model\Country $country,
        \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
    ) {
        parent::__construct($context);
        $this->country = $country;
        $this->generalConfig = $generalConfig;
    }

    /**
     * Get country name by its ISO2 abbreviation
     *
     * @param  string $countryCode
     * @return string|bool
     */
    public function getCountryNameByIso2($countryCode)
    {
        $country = $this->country->loadByCode($countryCode);
        if ($country) {
            return $country->getName();
        }
        return false;
    }

    /**
     * Get all activated debit SEPA countries
     *
     * @param  string $paymentMethod
     * @return array
     */
    public function getEnabledCountries($paymentMethod)
    {
        $result = [];

        $countryCodes = $this->generalConfig->getPaymentValue($paymentMethod, 'sepa_country');
        if ($countryCodes) {
            $countries = explode(',', $countryCodes);
            foreach ($countries as $countryCode) {
                $countryName = $this->getCountryNameByIso2($countryCode);
                if ($countryName) {
                    $result[] = [
                        'id' => $countryCode,
                        'title' => $countryName,
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * Return if state parameter has to be added for the given country
     *
     * @param  string $isoToCountry
     * @return bool
     */
    public static function isStateNeeded($isoToCountry)
    {
        if (array_search($isoToCountry, self::$stateNeeded) !== false) {
            return true;
        }
        return false;
    }
}
