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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Request;

use Magento\Payment\Model\MethodInterface;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\Request\OrderBuilder;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Model\RemoteIp;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

class OrderBuilderTest extends TestCase
{
    use OrderMockTrait;

    private function build(bool $requiresCustomerDevice, bool $transmitIp): array
    {
        $order = $this->createOrder(['increment_id' => '000000100', 'store_id' => 3]);
        $method = $this->createMethodInstance();
        $method->method('requiresCustomerDevice')->willReturn($requiresCustomerDevice);
        $method->method('getDeviceToken')->willReturnCallback(
            static fn ($storeId) => $storeId === 3 ? 'partner_merchant_uuid' : 'wrong store'
        );
        $payment = $this->createPaymentWithMethod($order, $method, ['device_token' => 'from the checkout input']);

        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getRefPrefix')->willReturn('');
        $generalConfig->method('isTransmitIp')->willReturn($transmitIp);
        $remoteIp = $this->createStub(RemoteIp::class);
        $remoteIp->method('get')->willReturn('203.0.113.5');

        $builder = new OrderBuilder(new SubjectReader(), new MerchantReference($generalConfig), $generalConfig, $remoteIp);
        return $builder->build(['payment' => $this->createPaymentDataObject($payment)])['body']['checkout']['orderRequest'];
    }

    public function testWithoutIp(): void
    {
        $this->assertSame([
            'orderType' => 'FULL',
            'orderReferences' => ['merchantReference' => 'od000000100'],
            'paymentMethodSpecificInput' => ['paymentChannel' => 'ECOMMERCE'],
        ], $this->build(false, false));
    }

    public function testIpIsTransmittedWhenConfigured(): void
    {
        $this->assertSame(
            ['ipAddress' => '203.0.113.5'],
            $this->build(false, true)['paymentMethodSpecificInput']['customerDevice']
        );
    }

    public function testMethodRequiringCustomerDeviceAlwaysSendsIpAndTheDeviceTokenOfTheMethod(): void
    {
        $this->assertSame(
            ['ipAddress' => '203.0.113.5', 'deviceToken' => 'partner_merchant_uuid'],
            $this->build(true, false)['paymentMethodSpecificInput']['customerDevice']
        );
    }

    public function testOtherPaymentMethodsNeverSendTheDevice(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPaymentWithMethod($order, $this->createStub(MethodInterface::class));
        $generalConfig = $this->createStub(GeneralConfig::class);
        $builder = new OrderBuilder(
            new SubjectReader(),
            new MerchantReference($generalConfig),
            $generalConfig,
            $this->createStub(RemoteIp::class)
        );

        $orderRequest = $builder->build(['payment' => $this->createPaymentDataObject($payment)])['body']['checkout']['orderRequest'];
        $this->assertArrayNotHasKey('customerDevice', $orderRequest['paymentMethodSpecificInput']);
    }
}
