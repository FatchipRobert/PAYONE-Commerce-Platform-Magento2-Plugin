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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Http;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\Http\TransferFactory;
use PHPUnit\Framework\TestCase;

class TransferFactoryTest extends TestCase
{
    private function createFactory(string $endpoint, string $httpMethod, string $requestName): TransferFactory
    {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getMerchantId')->willReturnCallback(static fn ($store) => 'P1_'.$store);
        $generalConfig->method('isLiveMode')->willReturnCallback(static fn ($store) => $store === 5);

        return new TransferFactory(new TransferBuilder(), $generalConfig, new Json(), $endpoint, $httpMethod, $requestName);
    }

    public function testPostRequestToPreProduction(): void
    {
        $factory = $this->createFactory(
            '/v1/{merchantId}/commerce-cases/{commerceCaseId}/checkouts/{checkoutId}/payment-executions/{paymentExecutionId}/capture',
            TransferFactory::METHOD_POST,
            'Capture'
        );

        $transfer = $factory->create([
            'uri' => ['commerceCaseId' => 'cc1', 'checkoutId' => 'ck1', 'paymentExecutionId' => 'pe1'],
            'body' => ['amount' => 1000, 'isFinal' => true],
            'store_id' => 1,
            'order_increment_id' => '000000100',
            'payment_method' => 'payone_cp_paypal',
        ]);

        $path = '/v1/P1_1/commerce-cases/cc1/checkouts/ck1/payment-executions/pe1/capture';
        $this->assertSame(TransferFactory::METHOD_POST, $transfer->getMethod());
        $this->assertSame(TransferFactory::API_URL_PRE_PRODUCTION.$path, $transfer->getUri());
        $this->assertSame(['Content-Type' => 'application/json; charset=utf-8'], $transfer->getHeaders());
        $this->assertSame('{"amount":1000,"isFinal":true}', $transfer->getBody());
        $this->assertFalse($transfer->shouldEncode());
        $this->assertSame([
            'store_id' => 1,
            'path' => $path,
            'request_name' => 'Capture',
            'order_increment_id' => '000000100',
            'payment_method' => 'payone_cp_paypal',
        ], $transfer->getClientConfig());
    }

    public function testGetRequestToProductionWithoutContentType(): void
    {
        $factory = $this->createFactory('/v1/{merchantId}/jwt', TransferFactory::METHOD_GET, 'GetJWT');

        $transfer = $factory->create(['store_id' => 5]);

        $this->assertSame(TransferFactory::API_URL_PRODUCTION.'/v1/P1_5/jwt', $transfer->getUri());
        $this->assertSame([], $transfer->getHeaders());
        $this->assertSame('[]', $transfer->getBody());
        $this->assertNull($transfer->getClientConfig()['order_increment_id']);
        $this->assertNull($transfer->getClientConfig()['payment_method']);
    }

    public function testUriParameterCanOverrideMerchantId(): void
    {
        $factory = $this->createFactory('/v1/{merchantId}/x', TransferFactory::METHOD_DELETE, 'X');

        $transfer = $factory->create(['uri' => ['merchantId' => 'OTHER']]);

        $this->assertSame(TransferFactory::API_URL_PRE_PRODUCTION.'/v1/OTHER/x', $transfer->getUri());
        $this->assertNull($transfer->getClientConfig()['store_id']);
        $this->assertArrayHasKey('Content-Type', $transfer->getHeaders());
    }
}
