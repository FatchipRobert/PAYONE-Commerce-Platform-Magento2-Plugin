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

class Customer extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    protected $regionFactory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context      $context
     * @param \Magento\Directory\Model\RegionFactory     $regionFactory
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Directory\Model\RegionFactory $regionFactory
    ) {
        parent::__construct($context);
        $this->regionFactory = $regionFactory;
    }

    /**
     * Get region code by address
     *
     * @param  \Magento\Quote\Model\Quote\Address|\Magento\Sales\Model\Order\Address $address
     * @return string
     */
    public function getRegionCode($address)
    {
        $regionCode = $address->getRegionCode();
        if (strlen($regionCode) != 2) {
            $regionCode = str_ireplace($address->getCountryId().'-', '', $regionCode); // MAG2-266
        }
        if (strlen($regionCode) != 2) {
            $region = $this->regionFactory->create();
            $region->loadByName($regionCode, $address->getCountryId());
            if ($region->getId()) {
                $regionCode = $region->getCode();
            }
        }
        return $regionCode;
    }

    /**
     * Map magento gender to PAYONE gender parameter
     *
     * @param  int $gender
     * @return string
     */
    public function getGenderParameter($gender)
    {
        $genderParameter = 'UNKNOWN';
        if ($gender == '1') {
            $genderParameter = 'MALE';
        } elseif ($gender == '2') {
            $genderParameter = 'FEMALE';
        }
        return $genderParameter;
    }
}
