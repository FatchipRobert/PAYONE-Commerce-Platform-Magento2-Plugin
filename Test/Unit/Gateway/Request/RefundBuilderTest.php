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

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Payone\CommercePlatform\Gateway\AmountConverter;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\Request\LineItemsBuilder;
use Payone\CommercePlatform\Gateway\Request\RefundBuilder;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RefundBuilderTest extends TestCase
{
    use OrderMockTrait;

    private function build(?Creditmemo $creditmemo, array $invoices = []): array
    {
        $order = $this->createOrder([
            'increment_id' => '000000100',
            'order_currency_code' => 'EUR',
            'payone_cp_payment_id' => 'PAY1',
        ]);
        $order->method('getInvoiceCollection')->willReturn($invoices);
        $payment = $this->createPayment($order);
        if ($creditmemo !== null) {
            $payment->setCreditmemo($creditmemo);
        }

        $lineItemsBuilder = $this->createStub(LineItemsBuilder::class);
        $lineItemsBuilder->method('build')->willReturn([['creditmemo items']]);
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getRefPrefix')->willReturn('');

        $builder = new RefundBuilder(
            new SubjectReader(),
            new AmountConverter(),
            new MerchantReference($generalConfig),
            $lineItemsBuilder
        );
        return $builder->build(['payment' => $this->createPaymentDataObject($payment), 'amount' => '12.34'])['body'];
    }

    private function createCreditmemo(?Invoice $invoice): Creditmemo
    {
        $creditmemo = $this->createPartialStub(Creditmemo::class, ['getInvoice']);
        $creditmemo->method('getInvoice')->willReturn($invoice);
        return $creditmemo;
    }

    private function createInvoice(int $id, ?string $transactionId): Invoice
    {
        $invoice = $this->createPartialStub(Invoice::class, []);
        $invoice->setId($id);
        $invoice->setTransactionId($transactionId);
        return $invoice;
    }

    public function testRefundOfCaptureRequestedByTheShop(): void
    {
        $first = $this->createInvoice(1, 'CAPTURE1');
        $second = $this->createInvoice(2, 'CAPTURE2');

        $this->assertSame([
            'amountOfMoney' => ['amount' => 1234, 'currencyCode' => 'EUR'],
            'references' => ['merchantReference' => 'rf000000100', 'captureReference' => 'cp000000100-2'],
            'return' => ['items' => [['creditmemo items']]],
        ], $this->build($this->createCreditmemo($second), [$first, $second]));
    }

    public static function captureWithoutReferenceProvider(): array
    {
        return [
            'automatic capture (SALE) registered by the webhook' => ['PAY1-sale'],
            'capture registered with the payment id' => ['PAY1'],
        ];
    }

    #[DataProvider('captureWithoutReferenceProvider')]
    public function testInvoicesNotCapturedByTheShopHaveNoCaptureReference(string $transactionId): void
    {
        $invoice = $this->createInvoice(1, $transactionId);

        $body = $this->build($this->createCreditmemo($invoice), [$invoice]);

        $this->assertSame(['merchantReference' => 'rf000000100'], $body['references']);
    }

    public function testCreditmemoWithoutInvoiceUsesFirstCaptureReference(): void
    {
        $body = $this->build($this->createCreditmemo(null));

        $this->assertSame('cp000000100', $body['references']['captureReference']);
    }

    public function testWithoutCreditmemo(): void
    {
        $body = $this->build(null);

        $this->assertSame('cp000000100', $body['references']['captureReference']);
        $this->assertSame([], $body['return']['items']);
    }
}
