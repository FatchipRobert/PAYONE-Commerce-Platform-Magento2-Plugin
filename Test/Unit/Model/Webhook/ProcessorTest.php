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

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage;
use Payone\CommercePlatform\Model\Webhook\Processor;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Processing of a stored webhook message
 */
class ProcessorTest extends TestCase
{
    use OrderMockTrait;

    private const MESSAGE = ['id' => 'EV1', 'type' => 'payment.capture_requested', 'payment' => ['id' => 'PAY1']];

    /**
     * @var WebhookMessage&MockObject
     */
    private MockObject $webhookMessage;
    /**
     * @var ManagerInterface&MockObject
     */
    private MockObject $eventManager;
    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;
    /**
     * @var Processor
     */
    private Processor $processor;

    protected function setUp(): void
    {
        $this->webhookMessage = $this->createMock(WebhookMessage::class);
        $this->eventManager = $this->createMock(ManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->processor = new Processor($this->webhookMessage, new Json(), $this->eventManager, $this->logger);
    }

    private function entry(array $data = []): array
    {
        return array_merge([
            'entity_id' => '5',
            'event_id' => 'EV1',
            'processed' => '0',
            'processing_attempts' => '0',
            'message' => json_encode(self::MESSAGE),
        ], $data);
    }

    public function testDispatchesGenericAndTypeSpecificEvent(): void
    {
        $order = $this->createOrder();
        $this->webhookMessage->method('getEntry')->with(5)->willReturn($this->entry());
        $this->webhookMessage->method('isEventProcessed')->with('EV1')->willReturn(false);
        $this->webhookMessage->method('getOrderByMessage')->with(self::MESSAGE)->willReturn($order);
        $this->webhookMessage->expects($this->once())->method('addProcessingAttempt')->with(5);
        $this->webhookMessage->expects($this->once())->method('markProcessed')->with(5);
        $dispatched = [];
        $this->eventManager->expects($this->exactly(2))->method('dispatch')->willReturnCallback(
            function (string $event, array $params) use (&$dispatched, $order) {
                $this->assertSame(['order' => $order, 'webhook_message' => self::MESSAGE], $params);
                $dispatched[] = $event;
            }
        );
        $this->logger->expects($this->never())->method('error');

        $this->assertTrue($this->processor->process(5));
        $this->assertSame(['payone_cp_webhook_all', 'payone_cp_webhook_payment_capture_requested'], $dispatched);
    }

    public function testMissingEntry(): void
    {
        $this->webhookMessage->method('getEntry')->willReturn(null);
        $this->webhookMessage->expects($this->never())->method('markProcessed');
        $this->eventManager->expects($this->never())->method('dispatch');
        $this->logger->expects($this->once())->method('warning');

        $this->assertFalse($this->processor->process(5));
    }

    public function testAlreadyProcessedEntryIsSkipped(): void
    {
        $this->webhookMessage->method('getEntry')->willReturn($this->entry(['processed' => '1']));
        $this->webhookMessage->expects($this->never())->method('addProcessingAttempt');
        $this->webhookMessage->expects($this->never())->method('markProcessed');
        $this->eventManager->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('error');

        $this->assertTrue($this->processor->process(5));
    }

    public function testEventProcessedViaOtherEntryIsOnlyMarked(): void
    {
        $this->webhookMessage->method('getEntry')->willReturn($this->entry());
        $this->webhookMessage->method('isEventProcessed')->willReturn(true);
        $this->webhookMessage->expects($this->never())->method('addProcessingAttempt');
        $this->webhookMessage->expects($this->once())->method('markProcessed')->with(5);
        $this->eventManager->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('error');

        $this->assertTrue($this->processor->process(5));
    }

    public function testEntryWithoutEventIdIsProcessed(): void
    {
        $this->webhookMessage->method('getEntry')->willReturn($this->entry(['event_id' => null]));
        $this->webhookMessage->expects($this->never())->method('isEventProcessed');
        $this->webhookMessage->expects($this->once())->method('markProcessed');
        $this->eventManager->expects($this->exactly(2))->method('dispatch');
        $this->logger->expects($this->never())->method('error');

        $this->assertTrue($this->processor->process(5));
    }

    public function testObserverErrorLeavesEntryUnprocessed(): void
    {
        $this->webhookMessage->method('getEntry')->willReturn($this->entry(['processing_attempts' => '2']));
        $this->webhookMessage->method('isEventProcessed')->willReturn(false);
        $this->webhookMessage->expects($this->once())->method('addProcessingAttempt');
        $this->webhookMessage->expects($this->never())->method('markProcessed');
        $this->eventManager->expects($this->once())->method('dispatch')
            ->willThrowException(new \Exception('Invoice could not be created'));
        $this->logger->expects($this->once())->method('error')->with(
            'PAYONE CP webhook processing failed: Invoice could not be created',
            ['entityId' => 5, 'eventId' => 'EV1', 'attempt' => 3]
        );

        $this->assertFalse($this->processor->process(5));
    }

    public function testInvalidStoredMessageLeavesEntryUnprocessed(): void
    {
        $this->webhookMessage->method('getEntry')->willReturn($this->entry(['message' => '{broken']));
        $this->webhookMessage->method('isEventProcessed')->willReturn(false);
        $this->webhookMessage->expects($this->never())->method('markProcessed');
        $this->eventManager->expects($this->never())->method('dispatch');
        $this->logger->expects($this->once())->method('error');

        $this->assertFalse($this->processor->process(5));
    }
}
