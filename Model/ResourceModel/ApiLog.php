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
use Magento\Framework\Config\ConfigOptionsListConstants;

class ApiLog extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
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
    protected $maskedFields = [
        'iban' => self::MASK_PARTIAL,
        'paymentProcessingToken' => self::MASK_PARTIAL,
        'encryptedPaymentData' => self::MASK_FULL,
        'token' => self::MASK_FULL, // JWT from GetJWT
    ];

    /**
     * @var \Payone\CommercePlatform\Gateway\Config\GeneralConfig
     */
    protected $generalConfig;

    /**
     * @var \Payone\CommercePlatform\Helper\Api
     */
    protected $apiHelper;

    /**
     * @var \Magento\Framework\App\DeploymentConfig
     */
    protected $deploymentConfig;

    /**
     * @var \Magento\Framework\App\ResourceConnection\ConnectionFactory
     */
    protected $connectionFactory;

    /**
     * Separate connection for log entries written during an open transaction
     *
     * @var \Magento\Framework\DB\Adapter\AdapterInterface|null
     */
    protected $logConnection = null;

    /**
     * Class constructor
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
     * @param \Payone\CommercePlatform\Helper\Api $apiHelper
     * @param \Magento\Framework\App\DeploymentConfig $deploymentConfig
     * @param \Magento\Framework\App\ResourceConnection\ConnectionFactory $connectionFactory
     * @param string $connectionName
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig,
        \Payone\CommercePlatform\Helper\Api $apiHelper,
        \Magento\Framework\App\DeploymentConfig $deploymentConfig,
        \Magento\Framework\App\ResourceConnection\ConnectionFactory $connectionFactory,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->generalConfig = $generalConfig;
        $this->apiHelper = $apiHelper;
        $this->deploymentConfig = $deploymentConfig;
        $this->connectionFactory = $connectionFactory;
    }

    /**
     * Returns the connection for writing log entries
     * API calls from the admin (capture, refund) run inside the transaction of the invoice/creditmemo save.
     * If the call fails, that transaction is rolled back - including the log entry on the same connection.
     * The API call happened anyway, so the entry is written on a separate connection in that case.
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected function getLogConnection()
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
     * Initialize connection and table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('payone_cp_protocol_api', 'entity_id');
    }

    /**
     * Masks a single value with the given mode
     *
     * @param  string $value
     * @param  string $mode
     * @return string
     */
    protected function maskValue($value, $mode)
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
     * @param  array $data
     * @param  bool  $masked set to true when at least one value was masked
     * @return array
     */
    protected function maskFields(array $data, &$masked)
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
     * @param  string|null $raw
     * @return string|null
     */
    protected function maskDetails($raw)
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

    /**
     * Save Api-log entry to database
     *
     * @param  BaseRequest   $request
     * @param  string|false  $requestRaw
     * @param  string        $responseStatus
     * @param  string|null   $responseRaw
     * @param  Response|null $responseObject
     * @return $this
     */
    public function addApiLogEntry(BaseRequest $request, $requestRaw, $responseStatus, $responseRaw = null, $responseObject = null)
    {
        $order = $request->getOrder();

        $orderIncrementId = $order !== null ? $order->getIncrementId() : null;
        $paymentMethod = $order !== null ? $order->getPayment()->getMethod() : null;

        $this->getLogConnection()->insert(
            $this->getMainTable(),
            [
                'order_increment_id' => $orderIncrementId,
                'store_id' => $request->getStoreId(),
                'payment_method' => $paymentMethod,
                'merchant_id' => $this->generalConfig->getMerchantId($request->getStoreId()),
                'request' => (new \ReflectionClass($request::class))->getShortName(),
                'response' => $responseStatus,
                'request_details' => $this->maskDetails($requestRaw),
                'response_details' => $this->maskDetails($responseRaw),
                'commerce_case_id' => $responseObject ? $responseObject->findData('commerceCaseId') : null,
                'checkout_id' => $responseObject ? $responseObject->findData('checkoutId') : null,
                'payment_id' => $responseObject ? $responseObject->findData('paymentId') : null,
                'payment_execution_id' => $responseObject ? $responseObject->findData('paymentExecutionId') : null,
            ]
        );
        return $this;
    }
}
