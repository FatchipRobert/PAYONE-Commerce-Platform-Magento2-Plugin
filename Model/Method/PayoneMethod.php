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

namespace Payone\CommercePlatform\Model\Method;

use Magento\Sales\Model\Order;
use Magento\Framework\DataObject;

abstract class PayoneMethod extends BaseMethod
{
    /**
     * Can be used to assign data from frontend to info instance
     *
     * @var array
     */
    protected $assignKeys;

    /**
     * Hook for extension by the real payment method classes
     *
     * @return array
     */
    public function getFrontendConfig()
    {
        return []; // filled in child classes
    }

    /**
     * Add the checkout-form-data to the checkout session
     *
     * @param  DataObject $data
     * @return $this
     */
    public function assignData(DataObject $data)
    {
        parent::assignData($data);

        if (!empty($this->assignKeys)) {
            $infoInstance = $this->getInfoInstance();
            $additionalData = $data->getAdditionalData();
            foreach ($this->assignKeys as $key) {
                if (!empty($additionalData[$key])) {
                    $infoInstance->setAdditionalInformation($key, $additionalData[$key]);
                }
            }
        }
        return $this;
    }

    /**
     * Returns a config param for this payment type
     *
     * @param  string $param
     * @param  string $storeCode
     * @return string
     */
    public function getPaymentConfigParam($param, $storeCode = null)
    {
        if ($storeCode === null) {
            $storeCode = $this->getStoreCode();
        }
        return $this->generalConfig->getPaymentValue($this->getCode(), $param, $storeCode);
    }

    /**
     * Returns operationmode live or test for this payment method
     *
     * @return string
     */
    public function getOperationMode()
    {
        return $this->generalConfig->getEnvironment();
    }

    /**
     * Trys to retrieve the storecode from the order
     *
     * @return string|null
     */
    protected function getStoreCode()
    {
        try {
            $infoInstance = $this->getInfoInstance();
            if (empty($infoInstance)) {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }

        $order = $infoInstance->getOrder();
        if (empty($order)) {
            $order = $infoInstance->getQuote();
            if (empty($order)) {
                return null;
            }
        }

        $store = $order->getStore();
        if (empty($store)) {
            return null;
        }
        return $store->getCode();
    }

    /**
     * Hook for method specific webhook handling - extended by the concrete methods
     *
     * @param Order $order
     * @param array $webhookMessage
     * @return void
     */
    public function handleWebhookSpecific(Order $order, $webhookMessage)
    {
        // hook for extention by child methods
    }
}
