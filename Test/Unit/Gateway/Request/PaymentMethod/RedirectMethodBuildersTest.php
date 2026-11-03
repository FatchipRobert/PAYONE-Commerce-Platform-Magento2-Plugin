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

use Magento\Payment\Gateway\Request\BuilderInterface;
use Payone\CommercePlatform\Gateway\Request\PaymentMethod\CreditcardBuilder;
use Payone\CommercePlatform\Gateway\Request\PaymentMethod\GooglePayBuilder;
use Payone\CommercePlatform\Gateway\Request\PaymentMethod\PayPalBuilder;
use Payone\CommercePlatform\Gateway\Request\PaymentMethod\WeroBuilder;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Credit card, PayPal, Google Pay and Wero builders (Apple Pay has its own test)
 */
class RedirectMethodBuildersTest extends TestCase
{
    use OrderMockTrait;

    private const RETURN_URL = 'https://shop.test/payone_cp/onepage/returned/?incrementId=000000100';

    private function build(string $builderClass, array $additionalInformation, bool $automaticCapture): array
    {
        $order = $this->createOrder();
        $payment = $this->createPayment($order, $additionalInformation, ['automatic_capture' => $automaticCapture ? '1' : '0']);
        $returnUrl = $this->createStub(ReturnUrl::class);
        $returnUrl->method('get')->willReturnCallback(fn ($urlOrder) => $urlOrder === $order ? self::RETURN_URL : '');

        /** @var BuilderInterface $builder */
        $builder = new $builderClass(new SubjectReader(), $returnUrl);
        return $builder->build(['payment' => $this->createPaymentDataObject($payment)]);
    }

    public static function creditcardProvider(): array
    {
        return [
            'Visa, pre-authorization' => ['VISA', false, 1, 'PRE_AUTHORIZATION'],
            'Amex, sale' => ['AMERICAN_EXPRESS', true, 2, 'SALE'],
            'Mastercard' => ['MASTERCARD', true, 3, 'SALE'],
            'Diners' => ['DINERS', true, 132, 'SALE'],
            'unknown card type: product id of the method config' => ['JCB', true, null, 'SALE'],
        ];
    }

    #[DataProvider('creditcardProvider')]
    public function testCreditcard(string $cardType, bool $automaticCapture, ?int $productId, string $mode): void
    {
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

        $this->assertSame($expected, $this->build(
            CreditcardBuilder::class,
            ['pseudocardpan' => 'pseudo123', 'cardholder' => 'Max Mustermann', 'cardtype' => $cardType],
            $automaticCapture
        ));
    }

    public function testPayPal(): void
    {
        $this->assertSame([
            'requiresApproval' => true,
            'tokenize' => false,
            'productSpecificInput' => ['addressSelectionAtPayPal' => false],
            'redirectionData' => ['returnUrl' => self::RETURN_URL],
        ], $this->build(PayPalBuilder::class, [], false));

        $this->assertFalse($this->build(PayPalBuilder::class, [], true)['requiresApproval']);
    }

    public function testGooglePaySendsTheTokenBase64Encoded(): void
    {
        $token = '{"signature":"MEQ","protocolVersion":"ECv2","signedMessage":"{}"}';

        $this->assertSame([
            'encryptedPaymentData' => base64_encode($token),
            'authorizationMode' => 'SALE',
            'threeDSecure' => ['redirectionData' => ['returnUrl' => self::RETURN_URL]],
        ], $this->build(GooglePayBuilder::class, ['payment_token' => $token], true));

        $this->assertSame(
            'PRE_AUTHORIZATION',
            $this->build(GooglePayBuilder::class, ['payment_token' => $token], false)['authorizationMode']
        );
    }

    public function testWeroIsAlwaysDirectSale(): void
    {
        $this->assertSame([
            'requiresApproval' => false,
            'tokenize' => false,
            'redirectionData' => ['returnUrl' => self::RETURN_URL],
        ], $this->build(WeroBuilder::class, [], false));
    }
}
