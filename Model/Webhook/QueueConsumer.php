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

namespace Payone\CommercePlatform\Model\Webhook;

/**
 * Handler of the queue consumer payone_cp.webhook.process (etc/queue_consumer.xml)
 */
class QueueConsumer
{
    public const TOPIC = 'payone_cp.webhook.process';

    /**
     * @param Processor $processor
     */
    public function __construct(
        private readonly Processor $processor,
    ) {
    }

    /**
     * Processes the webhook entry from the queue message
     *
     * Failures are not thrown on purpose: the entry stays unprocessed and is put back into the queue by the
     * retry cron (Cron\RetryWebhooks), a thrown exception would only mark the queue message as failed.
     *
     * @param string $entityId entity_id in payone_cp_protocol_webhook
     * @return void
     */
    public function process(string $entityId): void
    {
        $this->processor->process((int)$entityId);
    }
}
