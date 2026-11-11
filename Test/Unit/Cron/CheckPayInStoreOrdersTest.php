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

use Magento\Framework\DB\Select;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Payone\CommercePlatform\Cron\CheckPayInStoreOrders;
use Payone\CommercePlatform\Model\PayInStore\OrderStatusUpdater;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CheckPayInStoreOrdersTest extends TestCase
{
    use OrderMockTrait;

    public function testUpdatesOpenPayInStoreOrdersAndLogsErrors(): void
    {
        $failingOrder = $this->createOrder(['increment_id' => '000000101']);
        $paidOrder = $this->createOrder(['increment_id' => '000000102']);

        $filters = [];
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            static function ($field, $condition) use (&$filters, $collection) {
                $filters[$field] = $condition;
                return $collection;
            }
        );
        $collection->method('getTable')->willReturnCallback(static fn ($table) => 'prefix_'.$table);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$failingOrder, $paidOrder]));
        $select = $this->createMock(Select::class);
        $select->expects($this->once())->method('join')->with(
            ['payment' => 'prefix_sales_order_payment'],
            'payment.parent_id = main_table.entity_id',
            []
        )->willReturnSelf();
        $select->expects($this->once())->method('where')
            ->with('payment.method = ?', 'payone_cp_payinstore')->willReturnSelf();
        $collection->method('getSelect')->willReturn($select);
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $updated = [];
        $orderStatusUpdater = $this->createMock(OrderStatusUpdater::class);
        $orderStatusUpdater->expects($this->exactly(2))->method('update')->willReturnCallback(
            static function (Order $order) use (&$updated, $failingOrder) {
                $updated[] = $order->getIncrementId();
                if ($order === $failingOrder) {
                    throw new CommandException(__('API error'));
                }
            }
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'PAYONE CP Pay in Store status check failed: API error',
            ['orderIncrementId' => '000000101']
        );

        (new CheckPayInStoreOrders($collectionFactory, $orderStatusUpdater, $logger))->execute();

        $this->assertSame(['state' => Order::STATE_NEW, 'payone_cp_checkout_id' => ['notnull' => true]], $filters);
        $this->assertSame(['000000101', '000000102'], $updated);
    }
}
