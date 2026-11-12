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

use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\Request\Mapper\AddressMapper;
use Payone\CommercePlatform\Gateway\Request\Mapper\CustomerMapper;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CustomerMapperTest extends TestCase
{
    use OrderMockTrait;

    private function map(array $orderData, array $addressData, array $additionalInformation = []): array
    {
        $order = $this->createOrder(array_merge(['store_id' => 2, 'customer_email' => 'max@example.com'], $orderData));
        $order->method('getBillingAddress')->willReturn($this->createAddress(array_merge(
            ['firstname' => 'Max', 'lastname' => 'Mustermann'],
            $addressData
        )));
        $this->createPayment($order, $additionalInformation);

        $addressMapper = $this->createStub(AddressMapper::class);
        $addressMapper->method('map')->willReturn(['street' => 'Main Street 1']);
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getLanguage')->willReturnCallback(static fn ($store) => $store === 2 ? 'de' : 'en');

        return (new CustomerMapper($addressMapper, $generalConfig))->map($order);
    }

    public function testMinimalB2cCustomer(): void
    {
        $this->assertSame([
            'billingAddress' => ['street' => 'Main Street 1'],
            'contactDetails' => ['emailAddress' => 'max@example.com'],
            'personalInformation' => ['name' => ['firstName' => 'Max', 'surname' => 'Mustermann']],
            'locale' => 'de',
            'businessRelation' => 'B2C',
        ], $this->map([], []));
    }

    public function testCompletelyFilledB2bCustomer(): void
    {
        $this->assertSame([
            'billingAddress' => ['street' => 'Main Street 1'],
            'contactDetails' => ['emailAddress' => 'max@example.com', 'phoneNumber' => '+49 30 123'],
            'personalInformation' => [
                'name' => ['firstName' => 'Max', 'surname' => 'Mustermann', 'title' => 'Dr.'],
                'dateOfBirth' => '19800115',
                'gender' => 'MALE',
            ],
            'locale' => 'de',
            'businessRelation' => 'B2B',
            'companyInformation' => ['name' => 'ACME GmbH'],
        ], $this->map(
            ['customer_dob' => '1980-01-15 00:00:00', 'customer_gender' => '1'],
            ['company' => 'ACME GmbH', 'telephone' => '+49 30 123', 'prefix' => 'Dr.']
        ));
    }

    public function testCheckoutInputTakesPrecedenceOverAccountData(): void
    {
        $customer = $this->map(
            ['customer_dob' => '1980-01-15'],
            ['telephone' => '0301'],
            [CustomerMapper::KEY_DATE_OF_BIRTH => '1990-12-31', CustomerMapper::KEY_TELEPHONE => '0179 555']
        );

        $this->assertSame('19901231', $customer['personalInformation']['dateOfBirth']);
        $this->assertSame('0179 555', $customer['contactDetails']['phoneNumber']);
    }

    public function testEmptyDateOfBirthOfOldMagentoVersionsIsIgnored(): void
    {
        $customer = $this->map(['customer_dob' => '0000-00-00 00:00:00'], []);

        $this->assertArrayNotHasKey('dateOfBirth', $customer['personalInformation']);
    }

    public static function genderProvider(): array
    {
        return [['1', 'MALE'], ['2', 'FEMALE'], ['3', 'UNKNOWN']];
    }

    #[DataProvider('genderProvider')]
    public function testGender(string $gender, string $expected): void
    {
        $this->assertSame($expected, $this->map(['customer_gender' => $gender], [])['personalInformation']['gender']);
    }
}
