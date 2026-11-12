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

use Magento\Framework\App\ResourceConnection;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;

/**
 * Deletes API and webhook log entries older than the configured number of days (daily)
 */
class CleanLogs
{
    /**
     * Log tables with their timestamp column "date"
     */
    private const TABLES = ['payone_cp_protocol_api', 'payone_cp_protocol_webhook'];

    /**
     * @param ResourceConnection $resourceConnection
     * @param GeneralConfig $generalConfig
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly GeneralConfig $generalConfig,
    ) {
    }

    /**
     * Deletes the old entries, nothing if the retention is 0 (keep forever)
     *
     * @return void
     */
    public function execute(): void
    {
        $days = $this->generalConfig->getLogRetentionDays();
        if ($days <= 0) {
            return;
        }

        // Compared in the database: "date" is a TIMESTAMP in the time zone of the connection, like NOW()
        $connection = $this->resourceConnection->getConnection();
        foreach (self::TABLES as $table) {
            $connection->delete(
                $this->resourceConnection->getTableName($table),
                ['date < NOW() - INTERVAL ? DAY' => $days]
            );
        }
    }
}
