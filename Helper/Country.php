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
class Country extends Base
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
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context      $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Payone\CommercePlatform\Helper\Shop       $shopHelper
     * @param \Magento\Framework\App\State               $state
     * @param \Magento\Directory\Model\Country           $country
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Payone\CommercePlatform\Helper\Shop $shopHelper,
        \Magento\Framework\App\State $state,
        \Magento\Directory\Model\Country $country
    ) {
        parent::__construct($context, $storeManager, $shopHelper, $state);
        $this->country = $country;
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

        $countryCodes = $this->getConfigParam('sepa_country', $paymentMethod, 'payone_cp_payment');
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
