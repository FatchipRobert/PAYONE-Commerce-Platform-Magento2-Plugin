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

namespace Payone\CommercePlatform\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Sales\Model\Order;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\OrderLocator;

/**
 * Webhook protocol (payone_cp_protocol_webhook), also used for the idempotency of webhook events
 */
class WebhookMessage extends AbstractDb
{
    /**
     * @param Context $context
     * @param GeneralConfig $generalConfig
     * @param OrderLocator $orderLocator
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly GeneralConfig $generalConfig,
        private readonly OrderLocator $orderLocator,
        ?string $connectionName = null,
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * Initialize connection and table
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('payone_cp_protocol_webhook', 'entity_id');
    }

    /**
     * Returns the order belonging to the payment of the webhook message
     *
     * @param array $message
     * @return Order|null
     */
    public function getOrderByMessage(array $message): ?Order
    {
        if (empty($message['payment']['id'])) {
            return null;
        }

        return $this->orderLocator->getByPayonePaymentId((string)$message['payment']['id']);
    }

    /**
     * Save webhook message to database
     *
     * @param array $message
     * @return int entity_id of the new entry
     */
    public function addWebhookMessage(array $message): int
    {
        $order = $this->getOrderByMessage($message);

        $orderIncrementId = $order !== null ? $order->getIncrementId() : null;
        $paymentMethod = $order !== null ? $order->getPayment()->getMethod() : null;
        $storeId = $order !== null ? (int)$order->getStoreId() : null;

        $this->getConnection()->insert(
            $this->getMainTable(),
            [
                'event_id' => !empty($message['id']) ? $message['id'] : null,
                'type' => !empty($message['type']) ? $message['type'] : null,
                'order_increment_id' => $orderIncrementId,
                'store_id' => $storeId,
                'payment_id' => !empty($message['payment']['id']) ? $message['payment']['id'] : null,
                'payment_method' => $paymentMethod,
                'merchant_id' => $this->generalConfig->getMerchantId($storeId),
                'status' => !empty($message['payment']['status']) ? $message['payment']['status'] : null,
                'message' => json_encode($message),
            ]
        );
        return (int)$this->getConnection()->lastInsertId($this->getMainTable());
    }

    /**
     * Checks if a webhook event with the given event id was already processed successfully
     *
     * @param string $eventId
     * @return bool
     */
    public function isEventProcessed(string $eventId): bool
    {
        $select = $this->getConnection()->select()
            ->from($this->getMainTable(), ['entity_id'])
            ->where('event_id = ?', $eventId)
            ->where('processed = ?', 1)
            ->limit(1);
        return (bool)$this->getConnection()->fetchOne($select);
    }

    /**
     * Marks the webhook entry as successfully processed
     *
     * @param int $entityId
     * @return $this
     */
    public function markProcessed(int $entityId): self
    {
        $this->getConnection()->update($this->getMainTable(), ['processed' => 1], ['entity_id = ?' => $entityId]);
        return $this;
    }
}
