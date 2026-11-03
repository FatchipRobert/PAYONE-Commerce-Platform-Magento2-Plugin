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

namespace Payone\CommercePlatform\Test\Unit\Cron;

use Magento\Framework\MessageQueue\PublisherInterface;
use Payone\CommercePlatform\Cron\RetryWebhooks;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage;
use Payone\CommercePlatform\Model\Webhook\Processor;
use Payone\CommercePlatform\Model\Webhook\QueueConsumer;
use PHPUnit\Framework\TestCase;

/**
 * Retry cron and queue consumer: both hand the entity id of a stored webhook to the processor
 */
class RetryWebhooksTest extends TestCase
{
    public function testRepublishesFailedEntriesBelowMaxAttempts(): void
    {
        $webhookMessage = $this->createStub(WebhookMessage::class);
        $webhookMessage->method('getIdsForRetry')->willReturnCallback(
            static fn ($maxAttempts) => $maxAttempts === Processor::MAX_ATTEMPTS ? [3, 9] : []
        );
        $publisher = $this->createMock(PublisherInterface::class);
        $published = [];
        $publisher->expects($this->exactly(2))->method('publish')->willReturnCallback(
            static function ($topic, $data) use (&$published) {
                $published[] = [$topic, $data];
                return null;
            }
        );

        (new RetryWebhooks($webhookMessage, $publisher))->execute();

        $this->assertSame([[QueueConsumer::TOPIC, '3'], [QueueConsumer::TOPIC, '9']], $published);
    }

    public function testQueueConsumerProcessesEntityId(): void
    {
        $processor = $this->createMock(Processor::class);
        $processor->expects($this->once())->method('process')->with(42)->willReturn(true);

        (new QueueConsumer($processor))->process('42');
    }
}
