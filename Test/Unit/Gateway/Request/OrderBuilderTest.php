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

use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\Request\OrderBuilder;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Model\RemoteIp;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

class OrderBuilderTest extends TestCase
{
    use OrderMockTrait;

    private function build(?string $methodType, bool $transmitIp, array $additionalInformation = []): array
    {
        $order = $this->createOrder(['increment_id' => '000000100']);
        $payment = $this->createPayment($order, $additionalInformation, ['payone_method_type' => $methodType]);

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
        ], $this->build('cardPaymentMethod', false, [OrderBuilder::KEY_DEVICE_TOKEN => 'ignored']));
    }

    public function testIpIsTransmittedWhenConfigured(): void
    {
        $this->assertSame(
            ['ipAddress' => '203.0.113.5'],
            $this->build('cardPaymentMethod', true)['paymentMethodSpecificInput']['customerDevice']
        );
    }

    public function testBnplAlwaysSendsIpAndDeviceToken(): void
    {
        $this->assertSame(
            ['ipAddress' => '203.0.113.5', 'deviceToken' => 'partner_merchant_uuid'],
            $this->build(Payone::METHOD_TYPE_FINANCING, false, [OrderBuilder::KEY_DEVICE_TOKEN => 'partner_merchant_uuid'])
                ['paymentMethodSpecificInput']['customerDevice']
        );
    }

    public function testBnplWithoutDeviceToken(): void
    {
        $this->assertSame(
            ['ipAddress' => '203.0.113.5'],
            $this->build(Payone::METHOD_TYPE_FINANCING, false)['paymentMethodSpecificInput']['customerDevice']
        );
    }
}
