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

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection\ConnectionFactory;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\SubjectReader;

class ApiLog extends AbstractDb
{
    /**
     * Values of the response column
     */
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_ERROR = 'ERROR';

    /**
     * Masking modes for $maskedFields
     */
    public const MASK_FULL = 'full';
    public const MASK_PARTIAL = 'partial';

    /**
     * Fields which are masked in request_details and response_details before they are written to the log
     * Matched by key on every level of the JSON - PAYONE echoes the request input in the response, so one list covers both
     * MASK_PARTIAL keeps the last 4 characters (to identify the value for support), MASK_FULL replaces the whole value
     *
     * @var array
     */
    private array $maskedFields = [
        'iban' => self::MASK_PARTIAL,
        'paymentProcessingToken' => self::MASK_PARTIAL,
        'encryptedPaymentData' => self::MASK_FULL,
        'token' => self::MASK_FULL, // JWT from GetJWT
    ];

    /**
     * Separate connection for log entries written during an open transaction
     *
     * @var AdapterInterface|null
     */
    private ?AdapterInterface $logConnection = null;

    /**
     * @param Context $context
     * @param GeneralConfig $generalConfig
     * @param DeploymentConfig $deploymentConfig
     * @param ConnectionFactory $connectionFactory
     * @param SubjectReader $subjectReader
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly GeneralConfig $generalConfig,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly ConnectionFactory $connectionFactory,
        private readonly SubjectReader $subjectReader,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * Initialize connection and table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('payone_cp_protocol_api', 'entity_id');
    }

    /**
     * Save Api-log entry to database
     *
     * @param int|string|null $storeId
     * @param string $requestName
     * @param string|null $orderIncrementId
     * @param string|null $paymentMethod
     * @param string|null $requestRaw
     * @param string $responseStatus one of the STATUS_* constants
     * @param string|null $responseRaw
     * @param array|null $responseBody decoded response, source of the PAYONE ids
     * @return $this
     */
    public function addApiLogEntry(
        int|string|null $storeId,
        string $requestName,
        ?string $orderIncrementId,
        ?string $paymentMethod,
        ?string $requestRaw,
        string $responseStatus,
        ?string $responseRaw = null,
        ?array $responseBody = null
    ): self {
        $this->getLogConnection()->insert(
            $this->getMainTable(),
            [
                'order_increment_id' => $orderIncrementId,
                'store_id' => $storeId,
                'payment_method' => $paymentMethod,
                'merchant_id' => $this->generalConfig->getMerchantId($storeId),
                'request' => $requestName,
                'response' => $responseStatus,
                'request_details' => $this->maskDetails($requestRaw),
                'response_details' => $this->maskDetails($responseRaw),
                'commerce_case_id' => $this->subjectReader->findValue($responseBody, 'commerceCaseId'),
                'checkout_id' => $this->subjectReader->findValue($responseBody, 'checkoutId'),
                'payment_id' => $this->subjectReader->findValue($responseBody, 'paymentId'),
                'payment_execution_id' => $this->subjectReader->findValue($responseBody, 'paymentExecutionId'),
            ]
        );
        return $this;
    }

    /**
     * Returns the connection for writing log entries
     * API calls from the admin (capture, refund) run inside the transaction of the invoice/creditmemo save.
     * If the call fails, that transaction is rolled back - including the log entry on the same connection.
     * The API call happened anyway, so the entry is written on a separate connection in that case.
     *
     * @return AdapterInterface
     */
    private function getLogConnection(): AdapterInterface
    {
        $connection = $this->getConnection();
        if ($connection->getTransactionLevel() === 0) {
            return $connection;
        }

        if ($this->logConnection === null) {
            $configData = $this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTIONS.'/default');
            unset($configData['persistent']);
            $this->logConnection = $this->connectionFactory->create($configData);
        }
        return $this->logConnection;
    }

    /**
     * Masks a single value with the given mode
     *
     * @param mixed $value
     * @param string $mode
     * @return string
     */
    private function maskValue(mixed $value, string $mode): string
    {
        $value = (string)$value;
        if ($mode === self::MASK_PARTIAL && strlen($value) > 4) {
            return str_repeat('*', strlen($value) - 4).substr($value, -4);
        }
        return '***';
    }

    /**
     * Masks all configured fields in the given data on every level
     *
     * @param array $data
     * @param bool $masked set to true when at least one value was masked
     * @return array
     */
    private function maskFields(array $data, bool &$masked): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskFields($value, $masked);
            } elseif (isset($this->maskedFields[$key]) && $value !== null && $value !== '') {
                $data[$key] = $this->maskValue($value, $this->maskedFields[$key]);
                $masked = true;
            }
        }
        return $data;
    }

    /**
     * Masks sensitive fields in a raw JSON request or response
     *
     * Non-JSON content (e.g. exception messages) and JSON without sensitive fields is returned unchanged
     *
     * @param string|null $raw
     * @return string|null
     */
    private function maskDetails(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $raw;
        }

        $masked = false;
        $data = $this->maskFields($data, $masked);
        if ($masked === false) {
            return $raw;
        }
        return json_encode($data);
    }
}
