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

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Payone\CommercePlatform\Cron\CleanLogs;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use PHPUnit\Framework\TestCase;

class CleanLogsTest extends TestCase
{
    private function execute(int $retentionDays, AdapterInterface $connection): void
    {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnCallback(static fn (string $table) => 'pre_'.$table);
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getLogRetentionDays')->willReturn($retentionDays);

        (new CleanLogs($resourceConnection, $generalConfig))->execute();
    }

    public function testDeletesOldEntriesOfBothLogs(): void
    {
        $deleted = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->exactly(2))->method('delete')->willReturnCallback(
            static function (string $table, array $where) use (&$deleted) {
                $deleted[$table] = $where;
                return 1;
            }
        );

        $this->execute(180, $connection);

        $this->assertSame([
            'pre_payone_cp_protocol_api' => ['date < NOW() - INTERVAL ? DAY' => 180],
            'pre_payone_cp_protocol_webhook' => ['date < NOW() - INTERVAL ? DAY' => 180],
        ], $deleted);
    }

    public function testRetentionZeroKeepsEverything(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('delete');

        $this->execute(0, $connection);
    }
}
