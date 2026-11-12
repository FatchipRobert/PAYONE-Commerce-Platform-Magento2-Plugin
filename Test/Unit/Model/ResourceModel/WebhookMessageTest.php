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

namespace Payone\CommercePlatform\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\OrderLocator;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WebhookMessageTest extends TestCase
{
    use OrderMockTrait;

    private const TABLE = 'payone_cp_protocol_webhook';

    /**
     * @var Mysql&MockObject
     */
    private MockObject $connection;
    /**
     * @var array
     */
    private array $orders = [];
    /**
     * @var array
     */
    private array $wheres = [];
    /**
     * @var WebhookMessage
     */
    private WebhookMessage $resource;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Mysql::class);
        $this->connection->method('select')->willReturnCallback(function () {
            $select = $this->createStub(Select::class);
            $select->method('from')->willReturnSelf();
            $select->method('where')->willReturnCallback(function ($condition, $value) use ($select) {
                $this->wheres[] = [$condition, $value];
                return $select;
            });
            $select->method('limit')->willReturnSelf();
            $select->method('order')->willReturnSelf();
            return $select;
        });
        $resources = $this->createStub(ResourceConnection::class);
        $resources->method('getConnection')->willReturn($this->connection);
        $resources->method('getTableName')->willReturnArgument(0);
        $context = $this->createStub(Context::class);
        $context->method('getResources')->willReturn($resources);

        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getMerchantId')->willReturnCallback(static fn ($store) => 'P1_'.($store ?? 'default'));
        $orderLocator = $this->createStub(OrderLocator::class);
        $orderLocator->method('getByPayonePaymentId')->willReturnCallback(fn ($paymentId) => $this->orders[$paymentId] ?? null);

        $this->resource = new WebhookMessage($context, $generalConfig, $orderLocator);
    }

    public function testGetOrderByMessage(): void
    {
        $order = $this->createOrder();
        $this->orders['PAY1'] = $order;
        $this->connection->expects($this->never())->method('insert');

        $this->assertSame($order, $this->resource->getOrderByMessage(['payment' => ['id' => 'PAY1']]));
        $this->assertNull($this->resource->getOrderByMessage(['payment' => ['id' => 'PAY2']]));
        $this->assertNull($this->resource->getOrderByMessage(['type' => 'payment.created']));
    }

    public function testAddWebhookMessageOfKnownOrder(): void
    {
        $order = $this->createOrder(['store_id' => '3', 'increment_id' => '000000100']);
        $this->createPayment($order, [], [], 'payone_cp_wero');
        $this->orders['PAY1'] = $order;
        $message = ['id' => 'EV1', 'type' => 'payment.captured', 'payment' => ['id' => 'PAY1', 'status' => 'CAPTURED']];
        $this->connection->expects($this->once())->method('insert')->with(self::TABLE, [
            'event_id' => 'EV1',
            'type' => 'payment.captured',
            'order_increment_id' => '000000100',
            'store_id' => 3,
            'payment_id' => 'PAY1',
            'payment_method' => 'payone_cp_wero',
            'merchant_id' => 'P1_3',
            'status' => 'CAPTURED',
            'message' => json_encode($message),
        ]);
        $this->connection->method('lastInsertId')->with(self::TABLE)->willReturn('42');

        $this->assertSame(42, $this->resource->addWebhookMessage($message));
    }

    public function testAddWebhookMessageWithoutOrder(): void
    {
        $this->connection->expects($this->once())->method('insert')->with(self::TABLE, [
            'event_id' => null,
            'type' => null,
            'order_increment_id' => null,
            'store_id' => null,
            'payment_id' => null,
            'payment_method' => null,
            'merchant_id' => 'P1_default',
            'status' => null,
            'message' => '[]',
        ]);
        $this->connection->method('lastInsertId')->willReturn('7');

        $this->assertSame(7, $this->resource->addWebhookMessage([]));
    }

    public function testEventLookups(): void
    {
        $this->connection->expects($this->exactly(4))->method('fetchOne')->willReturnOnConsecutiveCalls('1', false, '5', null);

        $this->assertTrue($this->resource->isEventReceived('EV1'));
        $this->assertFalse($this->resource->isEventReceived('EV2'));
        $this->assertTrue($this->resource->isEventProcessed('EV1'));
        $this->assertFalse($this->resource->isEventProcessed('EV2'));
        $this->assertContains(['processed = ?', 1], $this->wheres);
        $this->assertContains(['event_id = ?', 'EV2'], $this->wheres);
    }

    public function testGetEntry(): void
    {
        $entry = ['entity_id' => '5', 'event_id' => 'EV1', 'processed' => '0', 'processing_attempts' => '1', 'message' => '{}'];
        $this->connection->expects($this->exactly(2))->method('fetchRow')->willReturnOnConsecutiveCalls($entry, false);

        $this->assertSame($entry, $this->resource->getEntry(5));
        $this->assertNull($this->resource->getEntry(6));
    }

    public function testAddProcessingAttemptIncrementsInTheDatabase(): void
    {
        $this->connection->expects($this->once())->method('update')->with(
            self::TABLE,
            $this->callback(static fn (array $data) => $data['processing_attempts'] instanceof Expression
                && (string)$data['processing_attempts'] === 'processing_attempts + 1'),
            ['entity_id = ?' => 5]
        );

        $this->resource->addProcessingAttempt(5);
    }

    public function testMarkProcessed(): void
    {
        $this->connection->expects($this->once())->method('update')->with(self::TABLE, ['processed' => 1], ['entity_id = ?' => 5]);

        $this->assertSame($this->resource, $this->resource->markProcessed(5));
    }

    public function testGetIdsForRetry(): void
    {
        $this->connection->expects($this->once())->method('fetchCol')->willReturn(['3', '9']);

        $this->assertSame([3, 9], $this->resource->getIdsForRetry(5));
        $this->assertSame([['processed = ?', 0], ['processing_attempts > ?', 0], ['processing_attempts < ?', 5]], $this->wheres);
    }
}
