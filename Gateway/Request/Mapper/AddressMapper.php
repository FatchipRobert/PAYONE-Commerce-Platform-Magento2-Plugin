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

namespace Payone\CommercePlatform\Gateway\Request\Mapper;

use Magento\Directory\Model\RegionFactory;
use Magento\Sales\Model\Order\Address;
use Payone\CommercePlatform\Model\Country;

/**
 * Maps a Magento order address to a PAYONE address
 */
class AddressMapper
{
    /**
     * @param Country $country
     * @param RegionFactory $regionFactory
     */
    public function __construct(
        private readonly Country $country,
        private readonly RegionFactory $regionFactory,
    ) {
    }

    /**
     * Returns street, zip, city, country and - where the country needs it - the state
     *
     * Magento has no separate fields for house number and additional info.
     *
     * @param Address $address
     * @return array
     */
    public function map(Address $address): array
    {
        $street = $address->getStreet();

        $result = [
            'street' => trim(is_array($street) ? implode(' ', $street) : (string)$street),
            'zip' => $address->getPostcode(),
            'city' => $address->getCity(),
            'countryCode' => $address->getCountryId(),
        ];

        if (!empty($address->getRegionCode()) && $this->country->isStateNeeded((string)$address->getCountryId())) {
            $result['state'] = $this->getRegionCode($address);
        }

        return $result;
    }

    /**
     * Returns the 2-letter region code of the address
     *
     * @param Address $address
     * @return string
     */
    private function getRegionCode(Address $address): string
    {
        $regionCode = (string)$address->getRegionCode();
        if (strlen($regionCode) != 2) {
            $regionCode = str_ireplace($address->getCountryId().'-', '', $regionCode); // MAG2-266
        }
        if (strlen($regionCode) != 2) {
            $region = $this->regionFactory->create();
            $region->loadByName($regionCode, $address->getCountryId());
            if ($region->getId()) {
                $regionCode = (string)$region->getCode();
            }
        }
        return $regionCode;
    }
}
