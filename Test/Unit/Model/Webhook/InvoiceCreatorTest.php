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

namespace Payone\CommercePlatform\Test\Unit\Model\Webhook;

use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Payone\CommercePlatform\Model\ResourceModel\ApiLog;
use Payone\CommercePlatform\Model\Webhook\InvoiceCreator;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InvoiceCreatorTest extends TestCase
{
    use MockCreationTrait;
    use OrderMockTrait;

    /**
     * @var OrderRepositoryInterface&MockObject
     */
    private MockObject $orderRepository;
    /**
     * @var InvoiceSender&MockObject
     */
    private MockObject $invoiceSender;
    /**
     * @var bool
     */
    private bool $captureRequested = false;

    private function createInvoiceCreator(): InvoiceCreator
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->invoiceSender = $this->createMock(InvoiceSender::class);
        $apiLog = $this->createStub(ApiLog::class);
        $apiLog->method('hasSuccessfulRequest')->willReturnCallback(
            fn ($incrementId, $requestName) => $incrementId === '000000100' && $requestName === 'Capture' && $this->captureRequested
        );
        return new InvoiceCreator($this->orderRepository, $this->invoiceSender, $apiLog);
    }

    /**
     * @return array{0: Order, 1: Payment&MockObject}
     */
    private function createOrderWithPayment(int $invoiceCount, bool $automaticCapture, ?Transaction $authorization = null): array
    {
        $order = $this->createOrder(['payone_cp_payment_id' => 'PAY1'], ['getBaseTotalDue']);
        $order->method('getBaseTotalDue')->willReturn(49.99);
        $collection = $this->createStub(InvoiceCollection::class);
        $collection->method('count')->willReturn($invoiceCount);
        $order->method('getInvoiceCollection')->willReturn($collection);

        // getCreatedInvoice() is a magic getter
        $payment = $this->createPartialMockWithReflection(
            Payment::class,
            ['getMethodInstance', 'getAuthorizationTransaction', 'registerCaptureNotification', 'getCreatedInvoice']
        );
        $payment->method('getMethodInstance')->willReturn(
            $this->createMethodInstance(['automatic_capture' => $automaticCapture ? '1' : '0'])
        );
        $payment->method('getAuthorizationTransaction')->willReturn($authorization ?? false);
        $order->method('getPayment')->willReturn($payment);
        return [$order, $payment];
    }

    public function testCreatesInvoiceForAutomaticCapture(): void
    {
        $creator = $this->createInvoiceCreator();
        $authorization = $this->createPartialStub(Transaction::class, []);
        $authorization->setTxnId('PAY1');
        [$order, $payment] = $this->createOrderWithPayment(0, true, $authorization);
        $invoice = $this->createStub(Invoice::class);
        $payment->expects($this->once())->method('registerCaptureNotification')->with(49.99, true);
        $payment->method('getCreatedInvoice')->willReturn($invoice);
        $this->orderRepository->expects($this->once())->method('save')->with($order);
        $this->invoiceSender->expects($this->once())->method('send')->with($invoice);

        $creator->createInvoice($order);

        $this->assertSame('PAY1-sale', $payment->getTransactionId());
        $this->assertSame('PAY1', $payment->getParentTransactionId());
    }

    public function testCreatesInvoiceForCaptureTheShopDidNotRequest(): void
    {
        $creator = $this->createInvoiceCreator();
        [$order, $payment] = $this->createOrderWithPayment(0, false);
        $payment->expects($this->once())->method('registerCaptureNotification');
        $payment->method('getCreatedInvoice')->willReturn(null);
        $this->orderRepository->expects($this->once())->method('save');
        $this->invoiceSender->expects($this->never())->method('send');

        $creator->createInvoice($order);

        $this->assertNull($payment->getParentTransactionId(), 'no authorization transaction');
    }

    public function testNoInvoiceWhenTheOrderAlreadyHasOne(): void
    {
        $creator = $this->createInvoiceCreator();
        [$order, $payment] = $this->createOrderWithPayment(1, true);
        $payment->expects($this->never())->method('registerCaptureNotification');
        $this->orderRepository->expects($this->never())->method('save');
        $this->invoiceSender->expects($this->never())->method('send');

        $creator->createInvoice($order);
    }

    public function testNoInvoiceForCaptureRequestedByTheShop(): void
    {
        $creator = $this->createInvoiceCreator();
        $this->captureRequested = true;
        [$order, $payment] = $this->createOrderWithPayment(0, false);
        $payment->expects($this->never())->method('registerCaptureNotification');
        $this->orderRepository->expects($this->never())->method('save');
        $this->invoiceSender->expects($this->never())->method('send');

        $creator->createInvoice($order);
    }
}
