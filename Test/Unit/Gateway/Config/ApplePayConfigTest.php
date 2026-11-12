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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Config;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Payone\CommercePlatform\Gateway\Config\ApplePayConfig;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\Config\EncryptedFile;
use Payone\CommercePlatform\Model\Payone;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ApplePayConfigTest extends TestCase
{
    /**
     * @var array
     */
    private array $paymentValues = [];
    /**
     * @var array
     */
    private array $values = [];
    /**
     * @var Store&Stub
     */
    private Stub $store;
    /**
     * @var ApplePayConfig
     */
    private ApplePayConfig $config;

    protected function setUp(): void
    {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getPaymentValue')->willReturnCallback(
            function (string $methodCode, string $field) {
                $this->assertSame(Payone::METHOD_APPLEPAY, $methodCode);
                return $this->paymentValues[$field] ?? null;
            }
        );
        $generalConfig->method('getValue')->willReturnCallback(fn (string $path) => $this->values[$path] ?? null);

        $this->store = static::getStubBuilder(Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFrontendName'])
            ->getStub();
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->store);

        $encryptedFile = $this->createStub(EncryptedFile::class);
        $encryptedFile->method('decode')->willReturnCallback(
            static fn (string $value) => $value !== '' ? ['name' => 'file', 'content' => 'content of '.$value] : null
        );

        $this->config = new ApplePayConfig($generalConfig, $storeManager, $encryptedFile);
    }

    public function testConfigurationIsCompleteWithMerchantIdCertificateAndKey(): void
    {
        $this->paymentValues = [
            'merchant_identifier' => ' merchant.com.example ',
            'certificate_file' => 'cert',
            'private_key_file' => 'key',
            'private_key_password' => 'pw',
        ];

        $this->assertTrue($this->config->isConfigurationComplete());
        $this->assertSame('merchant.com.example', $this->config->getMerchantIdentifier());
        $this->assertSame('content of cert', $this->config->getCertificate());
        $this->assertSame('content of key', $this->config->getPrivateKey());
        $this->assertSame('pw', $this->config->getPrivateKeyPassword());
    }

    public static function incompleteProvider(): array
    {
        $complete = ['merchant_identifier' => 'm', 'certificate_file' => 'c', 'private_key_file' => 'k'];
        return [
            'no merchant id' => [array_merge($complete, ['merchant_identifier' => ' '])],
            'no certificate' => [array_merge($complete, ['certificate_file' => null])],
            'no private key' => [array_merge($complete, ['private_key_file' => ''])],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('incompleteProvider')]
    public function testConfigurationIsIncomplete(array $paymentValues): void
    {
        $this->paymentValues = $paymentValues;
        $this->assertFalse($this->config->isConfigurationComplete());
    }

    public function testDisplayNameFromConfig(): void
    {
        $this->paymentValues = ['display_name' => '  My Shop  '];
        $this->assertSame('My Shop', $this->config->getDisplayName());
    }

    public function testDisplayNameFallsBackToStoreName(): void
    {
        $this->store->method('getFrontendName')->willReturn('Frontend Name');
        $this->assertSame('Frontend Name', $this->config->getDisplayName());
    }

    public function testDisplayNameFallsBackToDefaultAndIsTruncatedTo64Characters(): void
    {
        $this->store->method('getFrontendName')->willReturn('');
        $this->assertSame('Online Store', $this->config->getDisplayName());

        $this->paymentValues = ['display_name' => str_repeat('ä', 70)];
        $this->assertSame(str_repeat('ä', 64), $this->config->getDisplayName());
    }

    public function testSupportedNetworks(): void
    {
        $this->assertSame([], $this->config->getSupportedNetworks());
        $this->paymentValues = ['supported_networks' => 'visa,,masterCard'];
        $this->assertSame(['visa', 'masterCard'], $this->config->getSupportedNetworks());
    }

    public function testCountryCodeFromStoreInformation(): void
    {
        $this->values = ['general/store_information/country_id' => 'de', 'general/country/default' => 'AT'];
        $this->assertSame('DE', $this->config->getCountryCode());
    }

    public function testCountryCodeFallsBackToDefaultCountry(): void
    {
        $this->values = ['general/country/default' => 'at'];
        $this->assertSame('AT', $this->config->getCountryCode());
    }
}
