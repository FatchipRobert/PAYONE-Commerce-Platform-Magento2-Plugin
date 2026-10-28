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

namespace Payone\CommercePlatform\Model\ResourceModel;

use Payone\CommercePlatform\Model\Api\Request\BaseRequest;
use Payone\CommercePlatform\Model\Api\Response;

class WebhookMessage extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * @var \Payone\CommercePlatform\Gateway\Config\GeneralConfig
     */
    protected $generalConfig;

    /**
     * @var \Payone\CommercePlatform\Helper\Json
     */
    protected $jsonHelper;

    /**
     * @var \Payone\CommercePlatform\Helper\Order
     */
    protected $orderHelper;

    /**
     * Class constructor
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
     * @param \Payone\CommercePlatform\Helper\Json $jsonHelper
     * @param \Payone\CommercePlatform\Helper\Order $orderHelper
     * @param string $connectionName
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig,
        \Payone\CommercePlatform\Helper\Json $jsonHelper,
        \Payone\CommercePlatform\Helper\Order $orderHelper,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->generalConfig = $generalConfig;
        $this->jsonHelper = $jsonHelper;
        $this->orderHelper = $orderHelper;
    }

    /**
     * Initialize connection and table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('payone_cp_protocol_webhook', 'entity_id');
    }

    /**
     * Returns the order belonging to the payment of the webhook message
     *
     * @param array $message
     * @return \Magento\Sales\Model\Order|null
     */
    public function getOrderByMessage($message)
    {
        if (empty($message['payment']['id'])) {
            return null;
        }

        return $this->orderHelper->getOrderByPayonePaymentId($message['payment']['id']);
    }

    /**
     * Save webhook message to database
     *
     * @param  array $message
     * @return int entity_id of the new entry
     */
    public function addWebhookMessage($message)
    {
        $order = $this->getOrderByMessage($message);

        $orderIncrementId = $order !== null ? $order->getIncrementId() : null;
        $paymentMethod = $order !== null ? $order->getPayment()->getMethod() : null;
        $storeId = $order !== null ? $order->getStoreId() : null;

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
     * @param  string $eventId
     * @return bool
     */
    public function isEventProcessed($eventId)
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
     * @param  int $entityId
     * @return $this
     */
    public function markProcessed($entityId)
    {
        $this->getConnection()->update($this->getMainTable(), ['processed' => 1], ['entity_id = ?' => $entityId]);
        return $this;
    }
}
