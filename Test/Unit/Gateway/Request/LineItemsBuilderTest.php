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
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Payone\CommercePlatform\Gateway\AmountConverter;
use Payone\CommercePlatform\Gateway\Request\LineItemsBuilder;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

class LineItemsBuilderTest extends TestCase
{
    use OrderMockTrait;

    /**
     * @var LineItemsBuilder
     */
    private LineItemsBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new LineItemsBuilder(new AmountConverter());
    }

    private function createOrderItem(array $data, bool $isDummy = false): Order\Item
    {
        $item = $this->createPartialStub(Order\Item::class, ['isDummy']);
        $item->setData($data);
        $item->method('isDummy')->willReturn($isDummy);
        return $item;
    }

    private function createChildItem(string $className, Order\Item $orderItem, array $data): Invoice\Item|Creditmemo\Item
    {
        $item = $this->createPartialStub($className, ['getOrderItem']);
        $item->setData($data);
        $item->method('getOrderItem')->willReturn($orderItem);
        return $item;
    }

    public function testOrderWithProductsShippingAndCoupon(): void
    {
        $order = $this->createOrder([
            'shipping_incl_tax' => '5.9500',
            'shipping_tax_amount' => '0.9500',
            'discount_amount' => '-10.0000',
            'coupon_code' => 'SALE10',
        ]);
        $order->method('getAllItems')->willReturn([
            $this->createOrderItem([
                'name' => 'Configurable Shirt',
                'sku' => 'shirt-red',
                'qty_ordered' => '2.0000',
                'price_incl_tax' => '29.9900',
                'tax_amount' => '9.5800',
            ]),
            $this->createOrderItem(['name' => 'Child', 'sku' => 'shirt-red'], true),
        ]);

        $this->assertSame([
            [
                'invoiceData' => ['description' => 'Configurable Shirt'],
                'orderLineDetails' => [
                    'productCode' => 'shirt-red',
                    'productPrice' => 2999,
                    'productType' => LineItemsBuilder::TYPE_GOODS,
                    'quantity' => 2,
                    'taxAmount' => 958,
                ],
            ],
            [
                'invoiceData' => ['description' => 'Shipping Costs'],
                'orderLineDetails' => [
                    'productCode' => 'shipping',
                    'productPrice' => 595,
                    'productType' => LineItemsBuilder::TYPE_SHIPMENT,
                    'quantity' => 1,
                    'taxAmount' => 95,
                ],
            ],
            [
                'invoiceData' => ['description' => 'Coupon - SALE10'],
                'orderLineDetails' => [
                    'productCode' => 'discount',
                    'productPrice' => 1000,
                    'productType' => LineItemsBuilder::TYPE_DISCOUNT,
                    'quantity' => 1,
                ],
            ],
        ], $this->builder->build($order));
    }

    public function testZeroShippingAndDiscountStringsAddNoLines(): void
    {
        $order = $this->createOrder(['shipping_incl_tax' => '0.0000', 'discount_amount' => '0.0000']);
        $order->method('getAllItems')->willReturn([]);

        $this->assertSame([], $this->builder->build($order));
    }

    public function testInvoiceUsesItemQuantityAndSkipsDummyOrderItems(): void
    {
        $orderItem = $this->createOrderItem(['qty_ordered' => 5]);
        $dummyOrderItem = $this->createOrderItem([], true);
        $invoice = $this->createPartialStub(Invoice::class, ['getAllItems']);
        $invoice->setData(['discount_amount' => '-2.5']);
        $invoice->method('getAllItems')->willReturn([
            $this->createChildItem(Invoice\Item::class, $orderItem, [
                'name' => 'Mug',
                'sku' => 'mug',
                'qty' => '3.0000',
                'price_incl_tax' => '10',
                'tax_amount' => '4.79',
            ]),
            $this->createChildItem(Invoice\Item::class, $dummyOrderItem, ['name' => 'Bundle child']),
        ]);

        $lineItems = $this->builder->build($invoice);

        $this->assertCount(2, $lineItems);
        $this->assertSame(3, $lineItems[0]['orderLineDetails']['quantity']);
        $this->assertSame('Discount', $lineItems[1]['invoiceData']['description']);
        $this->assertSame(250, $lineItems[1]['orderLineDetails']['productPrice']);
        $this->assertArrayNotHasKey('taxAmount', $lineItems[1]['orderLineDetails']);
    }

    public function testCreditmemo(): void
    {
        $creditmemo = $this->createPartialStub(Creditmemo::class, ['getAllItems']);
        $creditmemo->setData(['shipping_incl_tax' => 4.0, 'shipping_tax_amount' => 0.0]);
        $creditmemo->method('getAllItems')->willReturn([
            $this->createChildItem(Creditmemo\Item::class, $this->createOrderItem([]), [
                'name' => 'Mug',
                'sku' => 'mug',
                'qty' => 1,
                'price_incl_tax' => 10,
                'tax_amount' => 1.6,
            ]),
        ]);

        $lineItems = $this->builder->build($creditmemo);

        $this->assertSame(['mug', 'shipping'], array_column(array_column($lineItems, 'orderLineDetails'), 'productCode'));
        $this->assertSame(0, $lineItems[1]['orderLineDetails']['taxAmount']);
    }
}
