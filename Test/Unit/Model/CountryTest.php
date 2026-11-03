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

namespace Payone\CommercePlatform\Test\Unit\Model;

use Magento\Directory\Model\Country as DirectoryCountry;
use Magento\Directory\Model\CountryFactory;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\Country;
use Payone\CommercePlatform\Model\Source\SepaCountry;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

class CountryTest extends TestCase
{
    use OrderMockTrait;

    private const NAMES = [
        'DE' => 'Deutschland',
        'AT' => 'Österreich',
        'BE' => 'Belgien',
        'CH' => 'Schweiz',
        'AX' => 'Ålandinseln',
        'NL' => 'Niederlande',
    ];

    private function createCountry(?string $sepaCountries = null): Country
    {
        $countryFactory = $this->createStub(CountryFactory::class);
        $countryFactory->method('create')->willReturnCallback(function () {
            $country = $this->createPartialStub(DirectoryCountry::class, ['loadByCode', 'getName']);
            $country->method('loadByCode')->willReturnCallback(function ($code) use ($country) {
                if (isset(self::NAMES[$code])) {
                    $country->setId($code);
                    $country->setData('name', self::NAMES[$code]);
                }
                return $country;
            });
            $country->method('getName')->willReturnCallback(fn () => $country->getData('name'));
            return $country;
        });
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getPaymentValue')->willReturnCallback(
            static fn ($code, $field) => $code === 'payone_cp_directdebit' && $field === 'sepa_country' ? $sepaCountries : null
        );

        return new Country($countryFactory, $generalConfig);
    }

    public function testGetName(): void
    {
        $country = $this->createCountry();

        $this->assertSame('Deutschland', $country->getName('DE'));
        $this->assertNull($country->getName('XX'));
    }

    public function testGetEnabledCountriesSkipsUnknownCodes(): void
    {
        $this->assertSame(
            [['id' => 'DE', 'title' => 'Deutschland'], ['id' => 'AT', 'title' => 'Österreich']],
            $this->createCountry('DE,XX,,AT')->getEnabledCountries('payone_cp_directdebit')
        );
        $this->assertSame([], $this->createCountry()->getEnabledCountries('payone_cp_directdebit'));
    }

    public function testIsStateNeeded(): void
    {
        $country = $this->createCountry();

        $this->assertTrue($country->isStateNeeded('US'));
        $this->assertTrue($country->isStateNeeded('CA'));
        $this->assertFalse($country->isStateNeeded('DE'));
        $this->assertFalse($country->isStateNeeded('us'));
    }

    public function testSepaCountrySourceSortsDiacriticsWithTheirBaseLetter(): void
    {
        $options = (new SepaCountry($this->createCountry()))->toOptionArray();

        $this->assertSame([
            ['value' => 'AX', 'label' => 'Ålandinseln'],
            ['value' => 'BE', 'label' => 'Belgien'],
            ['value' => 'DE', 'label' => 'Deutschland'],
            ['value' => 'NL', 'label' => 'Niederlande'],
            ['value' => 'AT', 'label' => 'Österreich'],
            ['value' => 'CH', 'label' => 'Schweiz'],
        ], array_values($options), 'unknown country codes are skipped');
    }
}
