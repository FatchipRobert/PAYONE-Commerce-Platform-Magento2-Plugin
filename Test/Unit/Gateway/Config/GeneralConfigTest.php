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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use PHPUnit\Framework\TestCase;

class GeneralConfigTest extends TestCase
{
    /**
     * @var array
     */
    private array $values = [];
    /**
     * @var GeneralConfig
     */
    private GeneralConfig $config;

    protected function setUp(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function (string $path, string $scope, $store) {
                $this->assertSame(ScopeInterface::SCOPE_STORES, $scope);
                return $this->values[$path.'@'.($store ?? 'current')] ?? null;
            }
        );
        $this->config = new GeneralConfig($scopeConfig);
    }

    public function testGeneralGetters(): void
    {
        $this->values = [
            'payone_cp_general/global/merchant_id@2' => 'P1_123',
            'payone_cp_general/global/api_key@2' => 'key',
            'payone_cp_general/global/api_secret@2' => 'secret',
            'payone_cp_general/global/environment@2' => 'live',
            'payone_cp_general/global/ref_prefix@2' => 'dev',
            'payone_cp_general/global/transmit_ip@2' => '1',
            'payone_cp_general/global/proxy_mode@2' => '0',
        ];

        $this->assertSame('P1_123', $this->config->getMerchantId(2));
        $this->assertSame('key', $this->config->getApiKey(2));
        $this->assertSame('secret', $this->config->getApiSecret(2));
        $this->assertSame('live', $this->config->getEnvironment(2));
        $this->assertTrue($this->config->isLiveMode(2));
        $this->assertSame('dev', $this->config->getRefPrefix(2));
        $this->assertTrue($this->config->isTransmitIp(2));
        $this->assertFalse($this->config->isProxyMode(2));
    }

    public function testMissingValuesAreCastToEmptyDefaults(): void
    {
        $this->assertSame('', $this->config->getMerchantId());
        $this->assertSame('', $this->config->getApiSecret());
        $this->assertFalse($this->config->isLiveMode());
        $this->assertFalse($this->config->isTransmitIp());
        $this->assertSame([], $this->config->getValidIps());
    }

    public function testTestEnvironmentIsNotLive(): void
    {
        $this->values = ['payone_cp_general/global/environment@current' => 'test'];
        $this->assertFalse($this->config->isLiveMode());
    }

    public function testGetPaymentValueUsesPaymentSection(): void
    {
        $this->values = ['payone_cp_payment/payone_cp_directdebit/request_bic@current' => '1'];
        $this->assertSame('1', $this->config->getPaymentValue('payone_cp_directdebit', 'request_bic'));
    }

    public function testGetGeneralValueWithGroup(): void
    {
        $this->values = ['payone_cp_general/creditcard/font_family@default' => 'Arial'];
        $this->assertSame('Arial', $this->config->getGeneralValue('font_family', 'creditcard', 'default'));
    }

    public function testGetValidIpsSplitsLinesAndDropsEmptyOnes(): void
    {
        $this->values = ['payone_cp_general/global/valid_ips@current' => " 1.2.3.4 \r\n\n10.0.0.0/8\r\n  \n5.6.*.*"];
        $this->assertSame(['1.2.3.4', '10.0.0.0/8', '5.6.*.*'], $this->config->getValidIps());
    }

    public function testLocaleAndLanguage(): void
    {
        $this->values = ['general/locale/code@3' => 'de_DE'];
        $this->assertSame('de_DE', $this->config->getLocale(3));
        $this->assertSame('de', $this->config->getLanguage(3));
    }
}
