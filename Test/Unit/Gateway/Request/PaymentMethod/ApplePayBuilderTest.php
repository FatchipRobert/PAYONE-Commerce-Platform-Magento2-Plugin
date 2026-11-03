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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Request\PaymentMethod;

use Magento\Framework\Exception\LocalizedException;
use Payone\CommercePlatform\Gateway\Request\PaymentMethod\ApplePayBuilder;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApplePayBuilderTest extends TestCase
{
    use OrderMockTrait;

    private function build(?string $token, bool $automaticCapture = true): array
    {
        $order = $this->createOrder();
        $payment = $this->createPayment(
            $order,
            ['payment_token' => $token],
            ['automatic_capture' => $automaticCapture ? '1' : '0'],
            'payone_cp_applepay'
        );
        $returnUrl = $this->createStub(ReturnUrl::class);
        $returnUrl->method('get')->willReturn('https://shop.test/return');

        return (new ApplePayBuilder(new SubjectReader(), $returnUrl))
            ->build(['payment' => $this->createPaymentDataObject($payment)]);
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
        ], $this->build($token));
    }

    public function testMissingOptionalFieldsAndPreAuthorization(): void
    {
        $result = $this->build(json_encode(['paymentData' => ['data' => 'ENCRYPTED', 'header' => []]]), false);

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
        $this->build($token);
    }
}
