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

namespace Payone\CommercePlatform\Test\Unit\Model\Method;

use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Model\Method\Creditcard;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use Payone\CommercePlatform\Test\Unit\Helper\PaymentMethodTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CreditcardTest extends TestCase
{
    use OrderMockTrait;
    use PaymentMethodTrait;

    private const RETURN_URL = 'https://shop.test/payone_cp/onepage/returned/?incrementId=000000100';

    /**
     * @var array
     */
    private array $paymentValues = [];
    /**
     * @var array
     */
    private array $generalValues = [];

    private function createMethod(bool $automaticCapture = false): Creditcard
    {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getEnvironment')->willReturn('test');
        $generalConfig->method('getPaymentValue')->willReturnCallback(
            fn ($code, $field) => $this->paymentValues[$code.'/'.$field] ?? null
        );
        $generalConfig->method('getGeneralValue')->willReturnCallback(
            fn ($field, $group) => $this->generalValues[$group.'/'.$field] ?? null
        );
        $returnUrl = $this->createStub(ReturnUrl::class);
        $returnUrl->method('get')->willReturn(self::RETURN_URL);

        return new Creditcard(
            ...$this->methodArguments(['automatic_capture' => $automaticCapture ? '1' : '0']),
            generalConfig: $generalConfig,
            returnUrl: $returnUrl
        );
    }

    public function testIdentity(): void
    {
        $method = $this->createMethod();

        $this->assertSame(Payone::METHOD_CREDITCARD, $method->getCode());
        $this->assertSame('cardPaymentMethod', $method->getPayoneMethodType());
        $this->assertSame(1, $method->getPayoneProductId());
    }

    public function testFrontendConfig(): void
    {
        $method = $this->createMethod();
        $this->paymentValues = [
            'payone_cp_creditcard/clicktopay_enabled' => '1',
            'payone_cp_creditcard/clicktopay_register_enabled' => '0',
            'payone_cp_creditcard/clicktopay_shopname' => 'My Shop',
            'payone_cp_creditcard/types' => 'visa,americanexpress,jcb',
        ];
        $this->assertSame([
            'mode' => 'test',
            'ctpEnabled' => '0', // Click to Pay not offered yet, stored values are ignored
            'ctpRegisterEnabled' => '0',
            'ctpShopName' => '',
            'allowedCardSchemes' => ['visa', 'amex'], // unknown brands are dropped
        ], $method->getFrontendConfig());

        $this->paymentValues = ['payone_cp_creditcard/types' => ''];
        $this->assertSame(['visa', 'mastercard', 'amex', 'diners'], $method->getFrontendConfig()['allowedCardSchemes']);

        $this->generalValues = [
            'creditcard/customization_enabled' => '1',
            'creditcard/font_family' => 'Arial',
            'creditcard/accent_color' => '#ff0000',
        ];
        $config = $method->getFrontendConfig();
        $this->assertSame('1', $config['uiConfigCustomizationEnabled']);
        $this->assertSame('Arial', $config['uiConfigFontFamily']);
        $this->assertSame('#ff0000', $config['uiConfigAccentColor']);
        $this->assertNull($config['uiConfigCardItemRadius']);
        $this->assertCount(27, $config);
    }

    public static function specificInputProvider(): array
    {
        return [
            'Visa, pre-authorization' => ['VISA', false, 1, 'PRE_AUTHORIZATION'],
            'Amex, sale' => ['AMERICAN_EXPRESS', true, 2, 'SALE'],
            'Mastercard' => ['MASTERCARD', true, 3, 'SALE'],
            'Diners' => ['DINERS', true, 132, 'SALE'],
            'unknown card type: product id of the method' => ['JCB', true, null, 'SALE'],
        ];
    }

    #[DataProvider('specificInputProvider')]
    public function testSpecificInput(string $cardType, bool $automaticCapture, ?int $productId, string $mode): void
    {
        $payment = $this->createPayment(
            $this->createOrder(),
            ['pseudocardpan' => 'pseudo123', 'cardholder' => 'Max Mustermann', 'cardtype' => $cardType]
        );
        $expected = [
            'authorizationMode' => $mode,
            'paymentProcessingToken' => 'pseudo123',
            'transactionChannel' => 'ECOMMERCE',
            'card' => ['cardholderName' => 'Max Mustermann'],
            'returnUrl' => self::RETURN_URL,
        ];
        if ($productId !== null) {
            $expected['paymentProductId'] = $productId;
        }

        $this->assertSame($expected, $this->createMethod($automaticCapture)->getSpecificInput($payment));
    }

    public function testTokenIsMaskedAfterThePaymentRequest(): void
    {
        $payment = $this->createPayment($this->createOrder(), ['pseudocardpan' => 'pseudo123', 'cardtype' => 'VISA']);

        $this->createMethod()->reduceCheckoutInput($payment);

        $this->assertSame(['pseudocardpan' => '*****o123', 'cardtype' => 'VISA'], $payment->getAdditionalInformation());
    }
}
