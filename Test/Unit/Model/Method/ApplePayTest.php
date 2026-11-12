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

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Payone\CommercePlatform\Gateway\Config\ApplePayConfig;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Model\Method\ApplePay;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use Payone\CommercePlatform\Test\Unit\Helper\PaymentMethodTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApplePayTest extends TestCase
{
    use OrderMockTrait;
    use PaymentMethodTrait;

    /**
     * @var array store id ('' = current store) => configuration complete
     */
    private array $configurationComplete = [];

    private function createMethod(array $configData = ['active' => '1', 'automatic_capture' => '1']): ApplePay
    {
        $applePayConfig = $this->createStub(ApplePayConfig::class);
        $applePayConfig->method('isConfigurationComplete')->willReturnCallback(
            fn ($store) => $this->configurationComplete[(string)$store] ?? false
        );
        $applePayConfig->method('getDisplayName')->willReturn('My Shop');
        $applePayConfig->method('getCountryCode')->willReturn('DE');
        $applePayConfig->method('getSupportedNetworks')->willReturn(['visa', 'masterCard']);
        $returnUrl = $this->createStub(ReturnUrl::class);
        $returnUrl->method('get')->willReturn('https://shop.test/return');

        return new ApplePay(...$this->methodArguments($configData), applePayConfig: $applePayConfig, returnUrl: $returnUrl);
    }

    public function testIdentity(): void
    {
        $method = $this->createMethod();

        $this->assertSame(Payone::METHOD_APPLEPAY, $method->getCode());
        $this->assertSame('mobilePaymentMethod', $method->getPayoneMethodType());
        $this->assertSame(302, $method->getPayoneProductId());
    }

    public function testOnlyAvailableWithCompleteConfiguration(): void
    {
        $quote = $this->createStub(CartInterface::class);
        $quote->method('getStoreId')->willReturn(5);

        $this->assertFalse($this->createMethod()->isAvailable(), 'without certificates');

        $this->configurationComplete = ['' => true];
        $this->assertTrue($this->createMethod()->isAvailable());
        $this->assertFalse($this->createMethod()->isAvailable($quote), 'checked for the store of the quote');
        $this->assertFalse($this->createMethod(['active' => '0'])->isAvailable(), 'inactive');

        $this->configurationComplete = ['5' => true];
        $this->assertTrue($this->createMethod()->isAvailable($quote));
    }

    public function testFrontendConfig(): void
    {
        $this->assertSame([
            'displayName' => 'My Shop',
            'countryCode' => 'DE',
            'supportedNetworks' => ['visa', 'masterCard'],
        ], $this->createMethod()->getFrontendConfig());
    }

    private function buildInput(?string $token, bool $automaticCapture = true): array
    {
        $payment = $this->createPayment($this->createOrder(), ['payment_token' => $token], [], Payone::METHOD_APPLEPAY);
        return $this->createMethod(['automatic_capture' => $automaticCapture ? '1' : '0'])->getSpecificInput($payment);
    }

    public function testMapsApplePayTokenToPayoneFields(): void
    {
        $token = json_encode([
            'paymentData' => [
                'data' => 'ENCRYPTED',
                'signature' => 'SIG',
                'version' => 'EC_v1',
                'header' => [
                    'publicKeyHash' => 'HASH',
                    'ephemeralPublicKey' => 'EPHEMERAL',
                    'transactionId' => 'TXN',
                    'applicationData' => 'not supported by PAYONE',
                ],
            ],
            'paymentMethod' => ['network' => 'Visa'],
            'transactionIdentifier' => 'TXN',
        ]);

        $this->assertSame([
            'encryptedPaymentData' => 'ENCRYPTED',
            'publicKeyHash' => 'HASH',
            'ephemeralKey' => 'EPHEMERAL',
            'authorizationMode' => 'SALE',
            'threeDSecure' => ['redirectionData' => ['returnUrl' => 'https://shop.test/return']],
            'productSpecificInput' => [
                'integrationType' => 'MERCHANT_CERTIFICATE',
                'network' => 'VISA',
                'token' => [
                    'version' => 'EC_V1',
                    'signature' => 'SIG',
                    'header' => ['transactionId' => 'TXN'],
                ],
            ],
        ], $this->buildInput($token));
    }

    public function testMissingOptionalFieldsAndPreAuthorization(): void
    {
        $result = $this->buildInput(json_encode(['paymentData' => ['data' => 'ENCRYPTED', 'header' => []]]), false);

        $this->assertSame('PRE_AUTHORIZATION', $result['authorizationMode']);
        $this->assertNull($result['publicKeyHash']);
        $this->assertSame('', $result['productSpecificInput']['network']);
        $this->assertSame('', $result['productSpecificInput']['token']['version']);
    }

    public static function invalidTokenProvider(): array
    {
        return [
            'no token' => [null],
            'no json' => ['abc'],
            'no payment data' => ['{"paymentMethod":{}}'],
            'payment data no array' => ['{"paymentData":"x"}'],
            'no encrypted data' => ['{"paymentData":{"data":"","header":{}}}'],
            'no header' => ['{"paymentData":{"data":"ENCRYPTED"}}'],
            'header no array' => ['{"paymentData":{"data":"ENCRYPTED","header":"x"}}'],
        ];
    }

    #[DataProvider('invalidTokenProvider')]
    public function testInvalidTokenThrows(?string $token): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The Apple Pay payment data is missing.');
        $this->buildInput($token);
    }

    public function testTokenIsRemovedAfterThePaymentRequest(): void
    {
        $payment = $this->createPayment($this->createOrder(), ['payment_token' => '{"paymentData":{}}']);

        $this->createMethod()->reduceCheckoutInput($payment);

        $this->assertSame([], $payment->getAdditionalInformation());
    }
}
