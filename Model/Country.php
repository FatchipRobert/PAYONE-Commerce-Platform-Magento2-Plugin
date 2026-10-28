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

namespace Payone\CommercePlatform\Model;

use Magento\Directory\Model\CountryFactory;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;

/**
 * Country names and country specific rules of the PAYONE API
 */
class Country
{
    /**
     * Countries where the state has to be sent in addresses
     */
    private const STATE_NEEDED = ['US', 'CA', 'CN', 'JP', 'MX', 'BR', 'AR', 'ID', 'TH', 'IN'];

    /**
     * @param CountryFactory $countryFactory
     * @param GeneralConfig $generalConfig
     */
    public function __construct(
        private readonly CountryFactory $countryFactory,
        private readonly GeneralConfig $generalConfig,
    ) {
    }

    /**
     * Returns the localized country name for an ISO2 code, null for unknown codes
     *
     * @param string $countryCode
     * @return string|null
     */
    public function getName(string $countryCode): ?string
    {
        $country = $this->countryFactory->create()->loadByCode($countryCode);
        if (!$country->getId()) {
            return null;
        }
        return (string)$country->getName();
    }

    /**
     * Returns the countries enabled for a payment method (config field sepa_country) as options for the checkout
     *
     * @param string $methodCode
     * @return array<int, array{id: string, title: string}>
     */
    public function getEnabledCountries(string $methodCode): array
    {
        $result = [];
        $countryCodes = (string)$this->generalConfig->getPaymentValue($methodCode, 'sepa_country');
        foreach (array_filter(explode(',', $countryCodes)) as $countryCode) {
            $countryName = $this->getName($countryCode);
            if ($countryName !== null) {
                $result[] = [
                    'id' => $countryCode,
                    'title' => $countryName,
                ];
            }
        }
        return $result;
    }

    /**
     * Whether the state has to be sent for addresses in this country
     *
     * @param string $countryCode
     * @return bool
     */
    public function isStateNeeded(string $countryCode): bool
    {
        return in_array($countryCode, self::STATE_NEEDED, true);
    }
}
