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

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Payone\CommercePlatform\Model\PayInStore\OrderStatusUpdater;
use Payone\CommercePlatform\Model\Payone;
use Psr\Log\LoggerInterface;

/**
 * Pay in Store: checks the PAYONE checkout status of all open orders (marks them as paid or cancels them)
 */
class CheckPayInStoreOrders
{
    /**
     * @param CollectionFactory $orderCollectionFactory
     * @param OrderStatusUpdater $orderStatusUpdater
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CollectionFactory $orderCollectionFactory,
        private readonly OrderStatusUpdater $orderStatusUpdater,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Updates the open Pay in Store orders, an error of one order does not stop the others
     *
     * @return void
     */
    public function execute(): void
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('state', Order::STATE_NEW)
            ->addFieldToFilter('payone_cp_checkout_id', ['notnull' => true]);
        $collection->getSelect()
            ->join(
                ['payment' => $collection->getTable('sales_order_payment')],
                'payment.parent_id = main_table.entity_id',
                []
            )
            ->where('payment.method = ?', Payone::METHOD_PAY_IN_STORE);

        /** @var Order $order */
        foreach ($collection as $order) {
            try {
                $this->orderStatusUpdater->update($order);
            } catch (\Exception $e) {
                $this->logger->error('PAYONE CP Pay in Store status check failed: '.$e->getMessage(), [
                    'orderIncrementId' => $order->getIncrementId(),
                ]);
            }
        }
    }
}
