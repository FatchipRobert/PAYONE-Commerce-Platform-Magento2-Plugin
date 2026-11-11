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

use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Model\Method\GooglePay;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use Payone\CommercePlatform\Test\Unit\Helper\PaymentMethodTrait;
use PHPUnit\Framework\TestCase;

class GooglePayTest extends TestCase
{
    use OrderMockTrait;
    use PaymentMethodTrait;

    private const RETURN_URL = 'https://shop.test/payone_cp/onepage/returned/?incrementId=000000100';

    /**
     * @var array
     */
    private array $paymentValues = [];

    private function createMethod(bool $automaticCapture = true): GooglePay
    {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getEnvironment')->willReturn('test');
        $generalConfig->method('getMerchantId')->willReturn('P1_123');
        $generalConfig->method('getPaymentValue')->willReturnCallback(
            fn ($code, $field) => $this->paymentValues[$code.'/'.$field] ?? null
        );
        $returnUrl = $this->createStub(ReturnUrl::class);
        $returnUrl->method('get')->willReturn(self::RETURN_URL);
        $store = $this->createStub(Store::class);
        $store->method('getFrontendName')->willReturnOnConsecutiveCalls('Frontend Name', '');
        $quote = $this->createStub(Quote::class);
        $quote->method('getStore')->willReturn($store);
        $session = $this->createStub(Session::class);
        $session->method('getQuote')->willReturn($quote);

        return new GooglePay(
            ...$this->methodArguments(['automatic_capture' => $automaticCapture ? '1' : '0']),
            generalConfig: $generalConfig,
            returnUrl: $returnUrl,
            checkoutSession: $session
        );
    }

    public function testIdentity(): void
    {
        $method = $this->createMethod();

        $this->assertSame(Payone::METHOD_GOOGLEPAY, $method->getCode());
        $this->assertSame('mobilePaymentMethod', $method->getPayoneMethodType());
        $this->assertSame(320, $method->getPayoneProductId());
    }

    public function testFrontendConfig(): void
    {
        $method = $this->createMethod();
        $this->paymentValues = ['payone_cp_googlepay/google_pay_merchant_id' => 'BCR2'];

        $this->assertSame([
            'merchantId' => 'P1_123',
            'storeName' => 'Frontend Name',
            'googlePayMerchantId' => 'BCR2',
            'operationMode' => 'test',
        ], $method->getFrontendConfig());
        $this->assertSame('Online Store', $method->getFrontendConfig()['storeName'], 'default without frontend name');

        $this->paymentValues['payone_cp_googlepay/store_name'] = 'Configured Name';
        $this->assertSame('Configured Name', $method->getFrontendConfig()['storeName']);
    }

    public function testSpecificInputSendsTheTokenBase64Encoded(): void
    {
        $token = '{"signature":"MEQ","protocolVersion":"ECv2","signedMessage":"{}"}';
        $payment = $this->createPayment($this->createOrder(), ['payment_token' => $token]);

        $this->assertSame([
            'encryptedPaymentData' => base64_encode($token),
            'authorizationMode' => 'SALE',
            'threeDSecure' => ['redirectionData' => ['returnUrl' => self::RETURN_URL]],
        ], $this->createMethod()->getSpecificInput($payment));

        $this->assertSame(
            'PRE_AUTHORIZATION',
            $this->createMethod(false)->getSpecificInput($payment)['authorizationMode']
        );
    }
}
