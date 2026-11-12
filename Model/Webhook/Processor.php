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

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage;
use Psr\Log\LoggerInterface;

/**
 * Processes a stored webhook: dispatches the payone_cp_webhook_* events for the observers
 *
 * Runs in the queue consumer (QueueConsumer), decoupled from the webhook request, so PAYONE gets its
 * answer immediately and slow observers (invoice + mail) don't cause PAYONE retries.
 */
class Processor
{
    /**
     * After this many failed attempts the retry cron gives up (entry stays unprocessed in the protocol)
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * @param WebhookMessage $webhookMessage
     * @param Json $json
     * @param ManagerInterface $eventManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly WebhookMessage $webhookMessage,
        private readonly Json $json,
        private readonly ManagerInterface $eventManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Processes the webhook entry, errors are logged and leave the entry unprocessed for the retry cron
     *
     * @param int $entityId entity_id in payone_cp_protocol_webhook
     * @return bool true if the entry is processed (now or before)
     */
    public function process(int $entityId): bool
    {
        $entry = $this->webhookMessage->getEntry($entityId);
        if ($entry === null) {
            $this->logger->warning('PAYONE CP webhook processing: entry not found', ['entityId' => $entityId]);
            return false;
        }
        if ((bool)$entry['processed']) {
            return true;
        }

        // Same event received twice at the same time and already processed via the other entry
        if (!empty($entry['event_id']) && $this->webhookMessage->isEventProcessed((string)$entry['event_id'])) {
            $this->webhookMessage->markProcessed($entityId);
            return true;
        }

        $this->webhookMessage->addProcessingAttempt($entityId);
        try {
            $webhookMessage = $this->json->unserialize((string)$entry['message']);
            $params = [
                'order' => $this->webhookMessage->getOrderByMessage($webhookMessage),
                'webhook_message' => $webhookMessage,
            ];
            $this->eventManager->dispatch('payone_cp_webhook_all', $params);
            $this->eventManager->dispatch(
                'payone_cp_webhook_'.strtolower(str_replace('.', '_', (string)$webhookMessage['type'])),
                $params
            );
        } catch (\Exception $e) {
            $this->logger->error('PAYONE CP webhook processing failed: '.$e->getMessage(), [
                'entityId' => $entityId,
                'eventId' => $entry['event_id'],
                'attempt' => (int)$entry['processing_attempts'] + 1,
            ]);
            return false;
        }

        $this->webhookMessage->markProcessed($entityId);
        return true;
    }
}
