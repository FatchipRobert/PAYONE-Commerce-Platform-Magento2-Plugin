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

use Payone\CommercePlatform\Model\Payone;

class GooglePay extends RedirectPaymentMethod
{
    /**
     * Method identifier of this payment method
     *
     * @var string
     */
    protected $methodCode = Payone::METHOD_GOOGLEPAY;

    /**
     * Can be used to assign data from frontend to info instance
     *
     * @var array
     */
    protected $assignKeys = [
        'payment_token',
    ];

    /**
     * Return store name from general config
     *
     * @return string|null
     */
    protected function getGeneralConfigStoreName()
    {
        $quote = $this->checkoutSession->getQuote();
        if (!empty($quote)) {
            $store = $quote->getStore();
            if (!empty($store)) {
                return $store->getFrontendName();
            }
        }
        return null;
    }

    /**
     * Return store name for Google Pay JS
     *
     * @return string
     */
    protected function getStoreName()
    {
        $storeName = $this->getPaymentConfigParam('store_name');
        if (empty($storeName)) {
            $storeName = $this->getGeneralConfigStoreName();
        }
        if (empty($storeName)) {
            $storeName = 'Online Store'; // default
        }
        return $storeName;
    }

    /**
     * Returns the PAYONE merchant id for the Google Pay gateway config
     *
     * @return string
     */
    protected function getMerchantId()
    {
        return $this->generalConfig->getMerchantId();
    }

    /**
     * Hook for extension by the real payment method classes
     *
     * @return array
     */
    public function getFrontendConfig()
    {
        $frontendConfig = parent::getFrontendConfig();
        $frontendConfig['merchantId'] = $this->getMerchantId();
        $frontendConfig['storeName'] = $this->getStoreName();
        $frontendConfig['googlePayMerchantId'] = $this->getPaymentConfigParam('google_pay_merchant_id');
        $frontendConfig['operationMode'] = $this->getOperationMode();
        return $frontendConfig;
    }
}
