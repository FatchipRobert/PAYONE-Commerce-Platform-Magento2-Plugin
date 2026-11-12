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

namespace Payone\CommercePlatform\Test\Unit\Gateway;

use Magento\Sales\Model\Order\Invoice;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MerchantReferenceTest extends TestCase
{
    use OrderMockTrait;

    /**
     * @var MerchantReference
     */
    private MerchantReference $merchantReference;

    protected function setUp(): void
    {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getRefPrefix')
            ->willReturnCallback(static fn ($store) => $store === 2 ? 'dev' : 'wrong-store');
        $this->merchantReference = new MerchantReference($generalConfig);
    }

    public function testGetCombinesTypePrefixConfiguredPrefixAndIncrementId(): void
    {
        $order = $this->createOrder(['store_id' => 2, 'increment_id' => '000000123']);
        $payment = $this->createPayment($order);

        $this->assertSame('ccdev000000123', $this->merchantReference->get($order, MerchantReference::PREFIX_COMMERCE_CASE));
        $this->assertSame('mddev000000123', $this->merchantReference->get($order, MerchantReference::PREFIX_MANDATE));
        $this->assertSame('dev', $payment->getAdditionalInformation(MerchantReference::REF_PREFIX_KEY));
    }

    public static function storedPrefixProvider(): array
    {
        return [
            'prefix changed after the order was placed' => ['old-', 'odold-000000123'],
            'order placed without prefix' => ['', 'od000000123'],
        ];
    }

    #[DataProvider('storedPrefixProvider')]
    public function testStoredPrefixOfTheOrderIsKept(string $storedPrefix, string $expected): void
    {
        $order = $this->createOrder(['store_id' => 2, 'increment_id' => '000000123']);
        $this->createPayment($order, [MerchantReference::REF_PREFIX_KEY => $storedPrefix]);

        $this->assertSame($expected, $this->merchantReference->get($order, MerchantReference::PREFIX_ORDER));
    }

    public function testFirstCaptureHasNoSuffix(): void
    {
        $invoice = $this->createInvoice(10);
        $order = $this->createOrderWithInvoices([$invoice]);

        $this->assertSame('cpdev000000123', $this->merchantReference->getForCapture($order, $invoice));
    }

    public function testLaterCapturesGetTheirPosition(): void
    {
        $first = $this->createInvoice(10);
        $second = $this->createInvoice(11);
        $third = $this->createInvoice(12);
        $order = $this->createOrderWithInvoices([$third, $first, $second]);

        $this->assertSame('cpdev000000123', $this->merchantReference->getForCapture($order, $first));
        $this->assertSame('cpdev000000123-2', $this->merchantReference->getForCapture($order, $second));
        $this->assertSame('cpdev000000123-3', $this->merchantReference->getForCapture($order, $third));
    }

    public function testUnsavedInvoiceComesAfterAllSavedInvoices(): void
    {
        $new = $this->createInvoice(null);
        $order = $this->createOrderWithInvoices([$this->createInvoice(10), $this->createInvoice(11), $new]);

        $this->assertSame('cpdev000000123-3', $this->merchantReference->getForCapture($order, $new));
    }

    private function createInvoice(?int $id): Invoice
    {
        $invoice = $this->createPartialStub(Invoice::class, []);
        $invoice->setId($id);
        return $invoice;
    }

    private function createOrderWithInvoices(array $invoices)
    {
        $order = $this->createOrder(['store_id' => 2, 'increment_id' => '000000123']);
        $order->method('getInvoiceCollection')->willReturn($invoices);
        $this->createPayment($order);
        return $order;
    }
}
