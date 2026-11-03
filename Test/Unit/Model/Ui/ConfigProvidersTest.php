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

namespace Payone\CommercePlatform\Test\Unit\Model\Ui;

use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Framework\Escaper;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Payone\CommercePlatform\Gateway\Config\ApplePayConfig;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\Country;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Model\Ui\ApplePayConfigProvider;
use Payone\CommercePlatform\Model\Ui\BnplConfigProvider;
use Payone\CommercePlatform\Model\Ui\ConfigProvider;
use Payone\CommercePlatform\Model\Ui\CreditcardConfigProvider;
use Payone\CommercePlatform\Model\Ui\DirectDebitConfigProvider;
use Payone\CommercePlatform\Model\Ui\GooglePayConfigProvider;
use PHPUnit\Framework\TestCase;

class ConfigProvidersTest extends TestCase
{
    use MockCreationTrait;

    /**
     * @var array
     */
    private array $availableMethods = [];
    /**
     * @var array
     */
    private array $instructions = [];
    /**
     * @var array
     */
    private array $paymentValues = [];
    /**
     * @var array
     */
    private array $generalValues = [];

    private function createPaymentHelper(): PaymentHelper
    {
        $paymentHelper = $this->createStub(PaymentHelper::class);
        $paymentHelper->method('getMethodInstance')->willReturnCallback(function (string $code) {
            $method = $this->createStub(MethodInterface::class);
            $method->method('isAvailable')->willReturn(in_array($code, $this->availableMethods, true));
            $method->method('getConfigData')->willReturnCallback(
                fn ($field) => $field === 'instructions' ? ($this->instructions[$code] ?? null) : null
            );
            return $method;
        });
        return $paymentHelper;
    }

    private function createGeneralConfig(): GeneralConfig
    {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getEnvironment')->willReturn('test');
        $generalConfig->method('isLiveMode')->willReturn(false);
        $generalConfig->method('getLocale')->willReturn('de_DE');
        $generalConfig->method('getMerchantId')->willReturn('P1_123');
        $generalConfig->method('getPaymentValue')->willReturnCallback(
            fn ($code, $field) => $this->paymentValues[$code.'/'.$field] ?? null
        );
        $generalConfig->method('getGeneralValue')->willReturnCallback(
            fn ($field, $group) => $this->generalValues[$group.'/'.$field] ?? null
        );
        return $generalConfig;
    }

    private function createEscaper(): Escaper
    {
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn ($value) => htmlspecialchars((string)$value));
        $escaper->method('escapeUrl')->willReturnCallback(static fn ($value) => htmlspecialchars((string)$value));
        return $escaper;
    }

    public function testConfigProviderSharedConfigAndInstructionsOfAvailableMethods(): void
    {
        $this->availableMethods = [Payone::METHOD_PAYPAL, Payone::METHOD_DIRECT_DEBIT];
        $this->instructions = [
            Payone::METHOD_PAYPAL => "Line 1\n<b>Line 2</b>",
            Payone::METHOD_CREDITCARD => 'not available',
        ];
        $session = $this->createMock(Session::class);
        $session->expects($this->once())->method('getData')->with('payone_cp_checkout_error_message', true)
            ->willReturn('The payment has been cancelled.');

        $provider = new ConfigProvider($this->createPaymentHelper(), $this->createEscaper(), $session, $this->createGeneralConfig());

        $this->assertSame(['payment' => [
            'payone_cp' => [
                'mode' => 'test',
                'fullLocale' => 'de_DE',
                'checkoutErrorMessage' => 'The payment has been cancelled.',
            ],
            'instructions' => [
                Payone::METHOD_DIRECT_DEBIT => '',
                Payone::METHOD_PAYPAL => "Line 1<br />\n&lt;b&gt;Line 2&lt;/b&gt;",
            ],
        ]], $provider->getConfig());
    }

    public function testCreditcardConfigProvider(): void
    {
        $provider = new CreditcardConfigProvider($this->createPaymentHelper(), $this->createGeneralConfig());
        $this->assertSame([], $provider->getConfig());

        $this->availableMethods = [Payone::METHOD_CREDITCARD];
        $this->paymentValues = [
            'payone_cp_creditcard/clicktopay_enabled' => '1',
            'payone_cp_creditcard/clicktopay_register_enabled' => '0',
            'payone_cp_creditcard/clicktopay_shopname' => 'My Shop',
        ];
        $this->assertSame(['payment' => ['payone_cp' => [Payone::METHOD_CREDITCARD => [
            'mode' => 'test',
            'ctpEnabled' => '1',
            'ctpRegisterEnabled' => '0',
            'ctpShopName' => 'My Shop',
        ]]]], $provider->getConfig());

        $this->generalValues = [
            'creditcard/customization_enabled' => '1',
            'creditcard/font_family' => 'Arial',
            'creditcard/accent_color' => '#ff0000',
        ];
        $config = $provider->getConfig()['payment']['payone_cp'][Payone::METHOD_CREDITCARD];
        $this->assertSame('1', $config['uiConfigCustomizationEnabled']);
        $this->assertSame('Arial', $config['uiConfigFontFamily']);
        $this->assertSame('#ff0000', $config['uiConfigAccentColor']);
        $this->assertNull($config['uiConfigCardItemRadius']);
        $this->assertCount(26, $config);
    }

    public function testDirectDebitConfigProvider(): void
    {
        $country = $this->createStub(Country::class);
        $country->method('getEnabledCountries')->willReturn([['id' => 'DE', 'title' => 'Deutschland']]);
        $provider = new DirectDebitConfigProvider($this->createPaymentHelper(), $this->createGeneralConfig(), $country);
        $this->assertSame([], $provider->getConfig());

        $this->availableMethods = [Payone::METHOD_DIRECT_DEBIT];
        $this->paymentValues = ['payone_cp_directdebit/request_bic' => '1'];
        $this->assertSame(['payment' => ['payone_cp' => [Payone::METHOD_DIRECT_DEBIT => [
            'sepaCountries' => [['id' => 'DE', 'title' => 'Deutschland']],
            'requestBic' => true,
        ]]]], $provider->getConfig());
    }

    public function testGooglePayConfigProvider(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getFrontendName')->willReturnOnConsecutiveCalls('Frontend Name', '');
        $quote = $this->createStub(Quote::class);
        $quote->method('getStore')->willReturn($store);
        $session = $this->createStub(Session::class);
        $session->method('getQuote')->willReturn($quote);
        $provider = new GooglePayConfigProvider($this->createPaymentHelper(), $this->createGeneralConfig(), $session);
        $this->assertSame([], $provider->getConfig());

        $this->availableMethods = [Payone::METHOD_GOOGLEPAY];
        $this->paymentValues = ['payone_cp_googlepay/google_pay_merchant_id' => 'BCR2'];
        $this->assertSame(['payment' => ['payone_cp' => [Payone::METHOD_GOOGLEPAY => [
            'merchantId' => 'P1_123',
            'storeName' => 'Frontend Name',
            'googlePayMerchantId' => 'BCR2',
            'operationMode' => 'test',
        ]]]], $provider->getConfig());

        $config = $provider->getConfig()['payment']['payone_cp'][Payone::METHOD_GOOGLEPAY];
        $this->assertSame('Online Store', $config['storeName'], 'default without frontend name');

        $this->paymentValues['payone_cp_googlepay/store_name'] = 'Configured Name';
        $config = $provider->getConfig()['payment']['payone_cp'][Payone::METHOD_GOOGLEPAY];
        $this->assertSame('Configured Name', $config['storeName']);
    }

    public function testApplePayConfigProvider(): void
    {
        $complete = false;
        $applePayConfig = $this->createStub(ApplePayConfig::class);
        $applePayConfig->method('isConfigurationComplete')->willReturnCallback(static function () use (&$complete) {
            return $complete;
        });
        $applePayConfig->method('getDisplayName')->willReturn('My Shop');
        $applePayConfig->method('getCountryCode')->willReturn('DE');
        $applePayConfig->method('getSupportedNetworks')->willReturn(['visa', 'masterCard']);
        $provider = new ApplePayConfigProvider($this->createPaymentHelper(), $applePayConfig);

        $this->availableMethods = [Payone::METHOD_APPLEPAY];
        $this->assertSame([], $provider->getConfig(), 'hidden without certificates');

        $complete = true;
        $this->assertSame(['payment' => ['payone_cp' => [Payone::METHOD_APPLEPAY => [
            'displayName' => 'My Shop',
            'countryCode' => 'DE',
            'supportedNetworks' => ['visa', 'masterCard'],
        ]]]], $provider->getConfig());

        $this->availableMethods = [];
        $this->assertSame([], $provider->getConfig());
    }

    public function testBnplConfigProviderCreatesDeviceTokenOncePerSession(): void
    {
        $sessionData = [];
        $session = $this->createPartialMockWithReflection(Session::class, ['getData', 'setData']);
        $session->method('getData')->willReturnCallback(static function ($key) use (&$sessionData) {
            return $sessionData[$key] ?? null;
        });
        $session->expects($this->once())->method('setData')->willReturnCallback(static function ($key, $value) use (&$sessionData, $session) {
            $sessionData[$key] = $value;
            return $session;
        });
        $identityGenerator = $this->createMock(IdentityGeneratorInterface::class);
        $identityGenerator->expects($this->once())->method('generateId')->willReturn('uuid-1');
        $code = Payone::METHOD_SECURED_INVOICE;
        $provider = new BnplConfigProvider(
            $this->createPaymentHelper(),
            $this->createGeneralConfig(),
            $session,
            $identityGenerator,
            $this->createEscaper(),
            $code
        );
        $this->assertSame([], $provider->getConfig());

        $this->availableMethods = [$code];
        $this->paymentValues = [
            $code.'/payla_partner_id' => 'e7yeryF2of8X',
            $code.'/terms_url' => 'https://payone.test/terms?a=1&b=2',
            $code.'/privacy_url' => 'https://payone.test/privacy',
        ];
        $config = $provider->getConfig()['payment']['payone_cp'][$code];

        $this->assertSame('e7yeryF2of8X', $config['paylaPartnerId']);
        $this->assertSame('P1_123', $config['paylaMerchantId']);
        $this->assertSame('t', $config['paylaEnvironment']);
        $this->assertSame('e7yeryF2of8X_P1_123_uuid-1', $config['deviceToken']);
        $this->assertStringContainsString('href="https://payone.test/terms?a=1&amp;b=2"', $config['legalText']);
        $this->assertStringContainsString('href="https://payone.test/privacy"', $config['legalText']);

        $this->assertSame(
            'e7yeryF2of8X_P1_123_uuid-1',
            $provider->getConfig()['payment']['payone_cp'][$code]['deviceToken'],
            'same token for the whole checkout session'
        );
    }
}
