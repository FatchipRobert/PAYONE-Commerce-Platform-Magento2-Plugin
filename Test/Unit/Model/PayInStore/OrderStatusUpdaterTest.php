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

namespace Payone\CommercePlatform\Test\Unit\Model\PayInStore;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Model\PayInStore\OrderStatusUpdater;
use Payone\CommercePlatform\Observer\PayInStore\DeleteCheckout;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderStatusUpdaterTest extends TestCase
{
    use MockCreationTrait;
    use OrderMockTrait;

    private const NOW = '2026-11-09 12:00:00';

    /**
     * @var OrderRepositoryInterface&MockObject
     */
    private MockObject $orderRepository;
    /**
     * @var InvoiceSender&MockObject
     */
    private MockObject $invoiceSender;
    /**
     * @var array
     */
    private array $commandSubjects = [];

    private function createUpdater(ArrayResult|\Exception $checkoutDetails): OrderStatusUpdater
    {
        $command = $this->createStub(CommandInterface::class);
        $command->method('execute')->willReturnCallback(function (array $subject) use ($checkoutDetails) {
            $this->commandSubjects[] = $subject;
            if ($checkoutDetails instanceof \Exception) {
                throw $checkoutDetails;
            }
            return $checkoutDetails;
        });
        $commandPool = $this->createStub(CommandPoolInterface::class);
        $commandPool->method('get')->willReturnCallback(
            static fn (string $code) => $code === 'get_checkout_details' ? $command : null
        );
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->invoiceSender = $this->createMock(InvoiceSender::class);
        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(strtotime(self::NOW.' UTC'));

        return new OrderStatusUpdater($commandPool, $this->orderRepository, $this->invoiceSender, $dateTime);
    }

    private static function checkoutDetails(string $paymentStatus, int $collectedAmount = 0): ArrayResult
    {
        return new ArrayResult([
            'checkoutId' => 'CK1',
            'statusOutput' => ['paymentStatus' => $paymentStatus, 'collectedAmount' => $collectedAmount],
        ]);
    }

    /**
     * @return array{0: Order&MockObject, 1: Payment&MockObject}
     */
    private function createOrderWithPayment(string $createdAt = '2026-11-01 12:00:00', ?string $cancelAfterDays = '3'): array
    {
        $order = $this->createPartialMockWithReflection(
            Order::class,
            ['getPayment', 'getBaseTotalDue', 'canCancel', 'cancel', 'addCommentToStatusHistory']
        );
        $order->setData([
            'store_id' => 1,
            'increment_id' => '000000100',
            'payone_cp_checkout_id' => 'CK1',
            'created_at' => $createdAt,
        ]);
        $order->method('getBaseTotalDue')->willReturn(72.10);

        // getCreatedInvoice() is a magic getter
        $payment = $this->createPartialMockWithReflection(
            Payment::class,
            ['getMethodInstance', 'registerCaptureNotification', 'getCreatedInvoice']
        );
        $payment->method('getMethodInstance')->willReturn(
            $this->createMethodInstance(['cancel_after_days' => $cancelAfterDays], 'payone_cp_payinstore')
        );
        $order->method('getPayment')->willReturn($payment);
        return [$order, $payment];
    }

    /**
     * @return array
     */
    public static function paymentProvider(): array
    {
        return [
            'fully paid' => [7210],
            'partially paid' => [1000], // invoice over the order total as well
        ];
    }

    #[DataProvider('paymentProvider')]
    public function testPaymentCreatesInvoiceOverOrderTotal(int $collectedAmount): void
    {
        $updater = $this->createUpdater(self::checkoutDetails('PAYMENT_COMPLETED', $collectedAmount));
        [$order, $payment] = $this->createOrderWithPayment();
        $invoice = $this->createStub(Invoice::class);
        $payment->expects($this->once())->method('registerCaptureNotification')->with(72.10, true);
        $payment->method('getCreatedInvoice')->willReturn($invoice);
        $order->expects($this->never())->method('cancel');
        $this->orderRepository->expects($this->once())->method('save')->with($order);
        $this->invoiceSender->expects($this->once())->method('send')->with($invoice);

        $updater->update($order);

        $this->assertSame([['order' => $order]], $this->commandSubjects);
        $this->assertSame('CK1-instore', $payment->getTransactionId());
    }

    public function testPaidCheckoutWithoutCreatedInvoiceSendsNoMail(): void
    {
        $updater = $this->createUpdater(self::checkoutDetails('PAYMENT_COMPLETED', 7210));
        [$order, $payment] = $this->createOrderWithPayment();
        $payment->expects($this->once())->method('registerCaptureNotification');
        $payment->method('getCreatedInvoice')->willReturn(null);
        $order->expects($this->never())->method('cancel');
        $this->orderRepository->expects($this->once())->method('save');
        $this->invoiceSender->expects($this->never())->method('send');

        $updater->update($order);
    }

    public function testUnpaidOrderIsCancelledAfterConfiguredDays(): void
    {
        $updater = $this->createUpdater(self::checkoutDetails(OrderStatusUpdater::STATUS_WAITING));
        [$order, $payment] = $this->createOrderWithPayment('2026-11-06 12:00:00', '3');
        $order->method('canCancel')->willReturn(true);
        $order->expects($this->once())->method('cancel');
        $order->expects($this->once())->method('addCommentToStatusHistory')->with($this->callback(
            static fn ($comment) => (string)$comment === 'No payment received in store within 3 days, order cancelled.'
        ));
        $payment->expects($this->never())->method('registerCaptureNotification');
        $this->orderRepository->expects($this->once())->method('save')->with($order);
        $this->invoiceSender->expects($this->never())->method('send');

        $updater->update($order);
    }

    public function testCheckoutDeletedInStoreCancelsOrderWithoutDeleteCall(): void
    {
        $updater = $this->createUpdater(new ArrayResult([
            'checkoutId' => 'CK1',
            'checkoutStatus' => OrderStatusUpdater::CHECKOUT_DELETED,
            'statusOutput' => ['paymentStatus' => 'NO_PAYMENT'],
        ]));
        [$order, $payment] = $this->createOrderWithPayment('2026-11-09 11:00:00', '0');
        $order->method('canCancel')->willReturn(true);
        $order->expects($this->once())->method('cancel')->willReturnCallback(function () use ($payment, $order) {
            // the flag must be set before Order::cancel() dispatches sales_order_payment_cancel
            $this->assertTrue($payment->getData(DeleteCheckout::FLAG_CHECKOUT_DELETED));
            return $order;
        });
        $order->expects($this->once())->method('addCommentToStatusHistory')->with($this->callback(
            static fn ($comment) => (string)$comment === 'Checkout was cancelled in store, order cancelled.'
        ));
        $payment->expects($this->never())->method('registerCaptureNotification');
        $this->orderRepository->expects($this->once())->method('save')->with($order);
        $this->invoiceSender->expects($this->never())->method('send');

        $updater->update($order);
    }

    public function testCheckoutDeletedInStoreOrderCannotBeCancelled(): void
    {
        $updater = $this->createUpdater(new ArrayResult(['checkoutStatus' => OrderStatusUpdater::CHECKOUT_DELETED]));
        [$order, $payment] = $this->createOrderWithPayment();
        $order->method('canCancel')->willReturn(false);
        $order->expects($this->never())->method('cancel');
        $payment->expects($this->never())->method('registerCaptureNotification');
        $this->orderRepository->expects($this->never())->method('save');
        $this->invoiceSender->expects($this->never())->method('send');

        $updater->update($order);
    }

    /**
     * @return array
     */
    public static function noChangeProvider(): array
    {
        return [
            'waiting, days not yet passed' => [OrderStatusUpdater::STATUS_WAITING, '2026-11-06 12:00:01', '3', true],
            'waiting, cancellation disabled' => [OrderStatusUpdater::STATUS_WAITING, '2026-01-01 00:00:00', '0', true],
            'waiting, cancellation not configured' => [OrderStatusUpdater::STATUS_WAITING, '2026-01-01 00:00:00', null, true],
            'waiting, order cannot be cancelled' => [OrderStatusUpdater::STATUS_WAITING, '2026-01-01 00:00:00', '3', false],
            'reservation, nothing collected' => ['PAYMENT_NOT_COMPLETED', '2026-01-01 00:00:00', '3', true],
            'no status' => ['', '2026-01-01 00:00:00', '3', true],
        ];
    }

    #[DataProvider('noChangeProvider')]
    public function testNoChange(string $paymentStatus, string $createdAt, ?string $cancelAfterDays, bool $canCancel): void
    {
        $updater = $this->createUpdater(
            $paymentStatus === '' ? new ArrayResult(['checkoutId' => 'CK1']) : self::checkoutDetails($paymentStatus)
        );
        [$order, $payment] = $this->createOrderWithPayment($createdAt, $cancelAfterDays);
        $order->method('canCancel')->willReturn($canCancel);
        $order->expects($this->never())->method('cancel');
        $payment->expects($this->never())->method('registerCaptureNotification');
        $this->orderRepository->expects($this->never())->method('save');
        $this->invoiceSender->expects($this->never())->method('send');

        $updater->update($order);
    }

    public function testApiErrorIsPassedOn(): void
    {
        $updater = $this->createUpdater(new CommandException(__('API error')));
        [$order, $payment] = $this->createOrderWithPayment('2026-01-01 00:00:00');
        $order->expects($this->never())->method('cancel');
        $payment->expects($this->never())->method('registerCaptureNotification');
        $this->orderRepository->expects($this->never())->method('save');
        $this->invoiceSender->expects($this->never())->method('send');

        $this->expectException(CommandException::class);
        $updater->update($order);
    }
}
