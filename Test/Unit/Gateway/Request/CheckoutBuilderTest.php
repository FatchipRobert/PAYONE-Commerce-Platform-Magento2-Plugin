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

use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use Payone\CommercePlatform\Gateway\AmountConverter;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\Request\CheckoutBuilder;
use Payone\CommercePlatform\Gateway\Request\LineItemsBuilder;
use Payone\CommercePlatform\Gateway\Request\Mapper\AddressMapper;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

class CheckoutBuilderTest extends TestCase
{
    use OrderMockTrait;

    /**
     * @var CheckoutBuilder
     */
    private CheckoutBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = $this->createBuilder(true);
    }

    private function createBuilder(bool $autoExecuteOrder): CheckoutBuilder
    {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getRefPrefix')->willReturn('');
        $lineItemsBuilder = $this->createStub(LineItemsBuilder::class);
        $lineItemsBuilder->method('build')->willReturn([['order items']]);
        $addressMapper = $this->createStub(AddressMapper::class);
        $addressMapper->method('map')->willReturn(['street' => 'Main Street 1', 'countryCode' => 'DE']);

        return new CheckoutBuilder(
            new SubjectReader(),
            new AmountConverter(),
            new MerchantReference($generalConfig),
            $lineItemsBuilder,
            $addressMapper,
            $autoExecuteOrder
        );
    }

    private function createCheckoutOrder(): Order
    {
        $order = $this->createOrder([
            'increment_id' => '000000100',
            'order_currency_code' => 'EUR',
        ], ['getTotalDue']);
        $order->method('getTotalDue')->willReturn(99.9);
        $store = $this->createStub(Store::class);
        $store->method('getName')->willReturn('Default Store View');
        $order->method('getStore')->willReturn($store);
        return $order;
    }

    public function testAuthorizeWithShippingAddress(): void
    {
        $order = $this->createCheckoutOrder();
        $order->method('getShippingAddress')->willReturn(
            $this->createAddress(['firstname' => 'Max', 'lastname' => 'Mustermann', 'prefix' => 'Dr.'])
        );
        $payment = $this->createPayment($order);

        $result = $this->builder->build(['payment' => $this->createPaymentDataObject($payment), 'amount' => 50.0]);

        $this->assertSame(['body' => ['checkout' => [
            'amountOfMoney' => ['amount' => 5000, 'currencyCode' => 'EUR'],
            'references' => ['merchantReference' => 'ck000000100', 'merchantShopReference' => 'Default Store View'],
            'autoExecuteOrder' => true,
            'shoppingCart' => ['items' => [['order items']]],
            'shipping' => ['address' => [
                'street' => 'Main Street 1',
                'countryCode' => 'DE',
                'name' => ['firstName' => 'Max', 'surname' => 'Mustermann', 'title' => 'Dr.'],
            ]],
        ]]], $result);
    }

    public function testInitializeUsesTotalDueAndVirtualOrderHasNoShipping(): void
    {
        $order = $this->createCheckoutOrder();
        $order->method('getShippingAddress')->willReturn(null);
        $payment = $this->createPayment($order);

        $checkout = $this->builder->build(['payment' => $this->createPaymentDataObject($payment)])['body']['checkout'];

        $this->assertSame(9990, $checkout['amountOfMoney']['amount']);
        $this->assertArrayNotHasKey('shipping', $checkout);
    }

    public function testShippingNameWithoutPrefix(): void
    {
        $order = $this->createCheckoutOrder();
        $order->method('getShippingAddress')->willReturn(
            $this->createAddress(['firstname' => 'Max', 'lastname' => 'Mustermann'])
        );
        $payment = $this->createPayment($order);

        $checkout = $this->builder->build(['payment' => $this->createPaymentDataObject($payment)])['body']['checkout'];

        $this->assertSame(['firstName' => 'Max', 'surname' => 'Mustermann'], $checkout['shipping']['address']['name']);
    }

    public function testCheckoutWithoutOrderHasNoAutoExecuteOrder(): void
    {
        $order = $this->createCheckoutOrder();
        $order->method('getShippingAddress')->willReturn(null);
        $payment = $this->createPayment($order, [], [], 'payone_cp_payinstore');

        $checkout = $this->createBuilder(false)->build(['payment' => $this->createPaymentDataObject($payment)])['body']['checkout'];

        $this->assertArrayNotHasKey('autoExecuteOrder', $checkout);
        $this->assertSame(9990, $checkout['amountOfMoney']['amount']);
        $this->assertSame([['order items']], $checkout['shoppingCart']['items']);
    }
}
