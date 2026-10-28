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
use Magento\Payment\Model\InfoInterface;

abstract class PayoneMethod extends BaseMethod
{
    /**
     * @var int
     */
    protected $paymentProductId;

    /**
     * @var string
     */
    protected $paymentMethodType;

    /**
     * Can be used to assign data from frontend to info instance
     *
     * @var array
     */
    protected $assignKeys;

    /**
     * @var bool
     */
    protected $isAutomaticCaptureMethod = false;

    /**
     * Return parameters specific to this payment type
     *
     * @param  Order|null $order
     * @return array
     */
    public function getPaymentSpecificParameters(?Order $order = null)
    {
        return []; // filled in child classes
    }

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
     * Returns the PAYONE payment product id of this method
     *
     * @return int
     */
    public function getPaymentProductId()
    {
        return $this->paymentProductId;
    }

    /**
     * Returns the API key of the method specific input, e.g. "cardPaymentMethodSpecificInput"
     *
     * @return string
     */
    public function getSpecificInputIdentifier()
    {
        return $this->paymentMethodType."SpecificInput";
    }

    /**
     * Returns the API key of the product specific input, e.g. "paymentProduct771SpecificInput"
     *
     * @return string
     */
    public function getPaymentProductInputIdentifier()
    {
        return "paymentProduct".$this->getPaymentProductId()."SpecificInput";
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
        return $this->shopHelper->getConfigParam($param, $this->getCode(), 'payone_cp_payment', $storeCode);
    }

    /**
     * Returns operationmode live or test for this payment method
     *
     * @return string
     */
    public function getOperationMode()
    {
        return $this->shopHelper->getOperationMode();
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
     * Sets the transaction id on the payment and optionally saves the payment
     *
     * @param  InfoInterface $payment
     * @param  string $transactionId
     * @param  bool $save
     * @return void
     */
    public function setTransactionId(InfoInterface $payment, $transactionId, $save = false)
    {
        $payment->setTransactionId($transactionId);
        $payment->setIsTransactionClosed(0);
        if ($save === true) {
            $payment->save();
        }
    }

    /**
     * Return returnUrl for redirect payment types
     *
     * @param  Order $order
     * @return string
     */
    public function getReturnUrl(?Order $order = null)
    {
        $addedParams = '';
        if ($order !== null) {
            $addedParams = '?incrementId='.$order->getIncrementId();
        }
        return $this->url->getUrl('payone_cp/onepage/returned').$addedParams;
    }

    /**
     * Checks if the payment is captured directly (SALE) instead of being authorized only
     *
     * @param string $storeCode
     * @return bool
     */
    public function isAutomaticCapture($storeCode = null)
    {
        if ($this->isAutomaticCaptureMethod === true) {
            return true;
        }
        return (bool)$this->getPaymentConfigParam('automatic_capture');
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
