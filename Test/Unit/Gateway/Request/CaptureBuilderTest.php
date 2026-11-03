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

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Payone\CommercePlatform\Gateway\AmountConverter;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\Request\CaptureBuilder;
use Payone\CommercePlatform\Gateway\Request\LineItemsBuilder;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Observer\OrderPaymentCapture;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CaptureBuilderTest extends TestCase
{
    use OrderMockTrait;

    private function createBuilder(Invoice $expectedInvoice): CaptureBuilder
    {
        $merchantReference = $this->createStub(MerchantReference::class);
        $merchantReference->method('getForCapture')->willReturnCallback(
            fn ($order, $invoice) => $invoice === $expectedInvoice ? 'cp100-2' : 'wrong invoice'
        );
        $lineItemsBuilder = $this->createStub(LineItemsBuilder::class);
        $lineItemsBuilder->method('build')->willReturnCallback(
            fn ($entity) => $entity === $expectedInvoice ? [['invoice items']] : []
        );
        return new CaptureBuilder(new SubjectReader(), new AmountConverter(), $merchantReference, $lineItemsBuilder);
    }

    private function buildBody(float $amount, bool $isFinal, ?string $methodType, bool $invoiceFromObserver): array
    {
        $order = $this->createOrder(['base_grand_total' => '100.0000']);
        $payment = $this->createPayment($order, [], ['payone_method_type' => $methodType], 'payone_cp_x', ['isCaptureFinal']);
        $payment->method('isCaptureFinal')->willReturn($isFinal);

        $invoice = $this->createPartialStub(Invoice::class, []);
        if ($invoiceFromObserver) {
            $payment->setData(OrderPaymentCapture::PAYMENT_KEY_CAPTURE_INVOICE, $invoice);
        } else {
            $collection = $this->createStub(InvoiceCollection::class);
            $collection->method('getLastItem')->willReturn($invoice);
            $order->method('getInvoiceCollection')->willReturn($collection);
        }

        return $this->createBuilder($invoice)->build([
            'payment' => $this->createPaymentDataObject($payment),
            'amount' => $amount,
        ])['body'];
    }

    public function testPartialCapture(): void
    {
        $this->assertSame([
            'amount' => 4050,
            'isFinal' => false,
            'references' => ['merchantReference' => 'cp100-2'],
            'delivery' => ['items' => [['invoice items']]],
        ], $this->buildBody(40.5, false, 'redirectPaymentMethod', true));
    }

    public function testInvoiceFallsBackToLastInvoiceOfOrder(): void
    {
        $body = $this->buildBody(100.0, true, 'redirectPaymentMethod', false);

        $this->assertTrue($body['isFinal']);
        $this->assertSame([['invoice items']], $body['delivery']['items']);
        $this->assertSame('cp100-2', $body['references']['merchantReference']);
    }

    public static function cancellationReasonProvider(): array
    {
        return [
            'BNPL final partial capture' => [60.0, true, Payone::METHOD_TYPE_FINANCING, true],
            'BNPL full capture' => [100.0, true, Payone::METHOD_TYPE_FINANCING, false],
            'BNPL non-final partial capture' => [60.0, false, Payone::METHOD_TYPE_FINANCING, false],
            'other method final partial capture' => [60.0, true, 'cardPaymentMethod', false],
        ];
    }

    #[DataProvider('cancellationReasonProvider')]
    public function testCancellationReasonOnlyForFinalPartialCaptureOfBnpl(
        float $amount,
        bool $isFinal,
        string $methodType,
        bool $expectReason
    ): void {
        $body = $this->buildBody($amount, $isFinal, $methodType, true);

        if ($expectReason) {
            $this->assertSame(CaptureBuilder::CANCELLATION_REASON_FINAL_PARTIAL, $body['cancellationReason']);
        } else {
            $this->assertArrayNotHasKey('cancellationReason', $body);
        }
    }
}
