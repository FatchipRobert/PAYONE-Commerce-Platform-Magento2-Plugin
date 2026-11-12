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

namespace Payone\CommercePlatform\Test\Unit\Observer\Webhook;

use Magento\Framework\Event\Observer;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Model\Webhook\InvoiceCreator;
use Payone\CommercePlatform\Observer\Webhook\All;
use Payone\CommercePlatform\Observer\Webhook\Captured;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * All (authorization / cancellation of redirect payments) and Captured (invoice)
 */
class WebhookObserversTest extends TestCase
{
    /**
     * @var OrderRepositoryInterface&MockObject
     */
    private MockObject $orderRepository;

    /**
     * @return array{0: Order&MockObject, 1: Payment&MockObject}
     */
    private function createOrderMock(string $state, bool $isInitializeNeeded, bool $canCancel = true): array
    {
        $method = $this->createStub(MethodInterface::class);
        $method->method('isInitializeNeeded')->willReturn($isInitializeNeeded);
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethodInstance', 'registerAuthorizationNotification'])
            ->getMock();
        $payment->method('getMethodInstance')->willReturn($method);
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'canCancel', 'cancel', 'addCommentToStatusHistory', 'getBaseTotalDue'])
            ->getMock();
        $order->setState($state);
        $order->method('getPayment')->willReturn($payment);
        $order->method('canCancel')->willReturn($canCancel);
        $order->method('getBaseTotalDue')->willReturn(49.99);
        return [$order, $payment];
    }

    public static function authorizedStatusProvider(): array
    {
        return [['PENDING_CAPTURE'], ['CAPTURED']];
    }

    #[DataProvider('authorizedStatusProvider')]
    public function testRedirectPaymentIsAuthorized(string $status): void
    {
        [$order, $payment] = $this->createOrderMock(Order::STATE_PENDING_PAYMENT, true);
        $payment->expects($this->once())->method('registerAuthorizationNotification')->with(49.99);
        $order->expects($this->never())->method('cancel');
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->orderRepository->expects($this->once())->method('save')->with($order);

        (new All($this->orderRepository))->execute(new Observer([
            'order' => $order,
            'webhook_message' => ['payment' => ['id' => 'PAY1', 'status' => $status]],
        ]));

        $this->assertSame('PAY1', $payment->getTransactionId());
        $this->assertFalse($payment->getIsTransactionClosed());
    }

    public static function failedStatusProvider(): array
    {
        return [['REJECTED'], ['CANCELLED']];
    }

    #[DataProvider('failedStatusProvider')]
    public function testFailedRedirectPaymentCancelsOrder(string $status): void
    {
        [$order, $payment] = $this->createOrderMock(Order::STATE_PENDING_PAYMENT, true);
        $payment->expects($this->never())->method('registerAuthorizationNotification');
        $order->expects($this->once())->method('cancel');
        $order->expects($this->once())->method('addCommentToStatusHistory')->with(
            $this->callback(static fn ($comment) => (string)$comment === "Payment failed at PAYONE (status $status), order cancelled.")
        );
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->orderRepository->expects($this->once())->method('save')->with($order);

        (new All($this->orderRepository))->execute(new Observer([
            'order' => $order,
            'webhook_message' => ['payment' => ['id' => 'PAY1', 'status' => $status]],
        ]));
    }

    public static function nothingToDoProvider(): array
    {
        return [
            'intermediate status' => [Order::STATE_PENDING_PAYMENT, true, true, 'AUTHORIZATION_REQUESTED'],
            'no status' => [Order::STATE_PENDING_PAYMENT, true, true, null],
            'already authorized' => [Order::STATE_PROCESSING, true, true, 'CAPTURED'],
            'cancelled meanwhile' => [Order::STATE_CANCELED, true, true, 'REJECTED'],
            'direct method (authorized in checkout)' => [Order::STATE_PENDING_PAYMENT, false, true, 'CAPTURED'],
            'order cannot be cancelled' => [Order::STATE_PENDING_PAYMENT, true, false, 'REJECTED'],
        ];
    }

    #[DataProvider('nothingToDoProvider')]
    public function testNothingToDo(string $state, bool $isInitializeNeeded, bool $canCancel, ?string $status): void
    {
        [$order, $payment] = $this->createOrderMock($state, $isInitializeNeeded, $canCancel);
        $payment->expects($this->never())->method('registerAuthorizationNotification');
        $order->expects($this->never())->method('cancel');
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->orderRepository->expects($this->never())->method('save');

        (new All($this->orderRepository))->execute(new Observer([
            'order' => $order,
            'webhook_message' => ['payment' => ['id' => 'PAY1', 'status' => $status]],
        ]));
    }

    public function testAllWithoutOrderOrMessage(): void
    {
        [$order, $payment] = $this->createOrderMock(Order::STATE_PENDING_PAYMENT, true);
        $order->expects($this->never())->method('cancel');
        $payment->expects($this->never())->method('registerAuthorizationNotification');
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects($this->never())->method('save');
        $all = new All($orderRepository);

        $all->execute(new Observer(['order' => null, 'webhook_message' => ['payment' => ['status' => 'REJECTED']]]));
        $all->execute(new Observer(['order' => $order, 'webhook_message' => []]));
    }

    private function executeCaptured(mixed $order, ?array $webhookMessage, bool $expectInvoice): void
    {
        $invoiceCreator = $this->createMock(InvoiceCreator::class);
        $invoiceCreator->expects($expectInvoice ? $this->once() : $this->never())->method('createInvoice')->with($order);

        (new Captured($invoiceCreator))->execute(new Observer(['order' => $order, 'webhook_message' => $webhookMessage]));
    }

    private function createCapturedOrder(string $state, bool $canInvoice): Order
    {
        $order = static::getStubBuilder(Order::class)->disableOriginalConstructor()->onlyMethods(['canInvoice'])->getStub();
        $order->setState($state);
        $order->method('canInvoice')->willReturn($canInvoice);
        return $order;
    }

    public function testCapturedCreatesInvoice(): void
    {
        $this->executeCaptured($this->createCapturedOrder(Order::STATE_PROCESSING, true), ['type' => 'payment.captured'], true);
    }

    public function testCapturedSkipsPendingPaymentAndInvoicedOrders(): void
    {
        $message = ['type' => 'payment.captured'];
        $this->executeCaptured($this->createCapturedOrder(Order::STATE_PENDING_PAYMENT, true), $message, false);
        $this->executeCaptured($this->createCapturedOrder(Order::STATE_PROCESSING, false), $message, false);
        $this->executeCaptured($this->createCapturedOrder(Order::STATE_PROCESSING, true), [], false);
        $this->executeCaptured(null, $message, false);
    }
}
