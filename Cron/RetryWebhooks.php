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

namespace Payone\CommercePlatform\Cron;

use Magento\Framework\MessageQueue\PublisherInterface;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage;
use Payone\CommercePlatform\Model\Webhook\Processor;
use Payone\CommercePlatform\Model\Webhook\QueueConsumer;

/**
 * Puts webhooks whose processing failed back into the queue (every 5 minutes, max. Processor::MAX_ATTEMPTS attempts)
 */
class RetryWebhooks
{
    /**
     * @param WebhookMessage $webhookMessage
     * @param PublisherInterface $publisher
     */
    public function __construct(
        private readonly WebhookMessage $webhookMessage,
        private readonly PublisherInterface $publisher,
    ) {
    }

    /**
     * Publishes the failed entries again
     *
     * @return void
     */
    public function execute(): void
    {
        foreach ($this->webhookMessage->getIdsForRetry(Processor::MAX_ATTEMPTS) as $entityId) {
            $this->publisher->publish(QueueConsumer::TOPIC, (string)$entityId);
        }
    }
}
