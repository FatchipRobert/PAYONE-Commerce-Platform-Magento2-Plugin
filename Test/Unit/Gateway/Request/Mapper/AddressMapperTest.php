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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Request\Mapper;

use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\Request\Mapper\AddressMapper;
use Payone\CommercePlatform\Model\Country;
use Magento\Directory\Model\CountryFactory;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

class AddressMapperTest extends TestCase
{
    use OrderMockTrait;

    /**
     * @var AddressMapper
     */
    private AddressMapper $mapper;

    protected function setUp(): void
    {
        $region = $this->createPartialStub(Region::class, ['loadByName']);
        $region->method('loadByName')->willReturnCallback(function ($name, $countryId) use ($region) {
            if ($name === 'California' && $countryId === 'US') {
                $region->setData(['id' => 12, 'code' => 'CA']);
            }
            return $region;
        });
        $regionFactory = $this->createStub(RegionFactory::class);
        $regionFactory->method('create')->willReturn($region);

        $country = new Country($this->createStub(CountryFactory::class), $this->createStub(GeneralConfig::class));
        $this->mapper = new AddressMapper($country, $regionFactory);
    }

    public function testMapsAddressWithMultilineStreet(): void
    {
        $address = $this->createAddress([
            'street' => "Hauptstraße 1\nHinterhaus",
            'postcode' => '12345',
            'city' => 'Berlin',
            'country_id' => 'DE',
            'region_code' => 'BER',
        ]);

        $this->assertSame([
            'street' => 'Hauptstraße 1 Hinterhaus',
            'zip' => '12345',
            'city' => 'Berlin',
            'countryCode' => 'DE',
        ], $this->mapper->map($address), 'no state for countries that do not need one');
    }

    public function testTwoLetterRegionCodeIsUsedAsState(): void
    {
        $this->assertSame('NY', $this->mapUsState('NY'));
    }

    public function testCountryPrefixIsRemovedFromRegionCode(): void
    {
        $this->assertSame('TX', $this->mapUsState('US-TX'));
    }

    public function testRegionNameIsResolvedToItsCode(): void
    {
        $this->assertSame('CA', $this->mapUsState('California'));
    }

    public function testUnknownRegionIsSentUnchanged(): void
    {
        $this->assertSame('Atlantis', $this->mapUsState('Atlantis'));
    }

    public function testNoStateWithoutRegionCode(): void
    {
        $address = $this->createAddress(['street' => 'Main St', 'country_id' => 'US']);

        $this->assertArrayNotHasKey('state', $this->mapper->map($address));
    }

    private function mapUsState(string $regionCode): string
    {
        $address = $this->createAddress([
            'street' => ' 1 Main St ',
            'postcode' => '10001',
            'city' => 'New York',
            'country_id' => 'US',
            'region_code' => $regionCode,
        ]);
        $result = $this->mapper->map($address);
        $this->assertSame('1 Main St', $result['street']);
        return $result['state'];
    }
}
