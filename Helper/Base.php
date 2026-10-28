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

namespace Payone\CommercePlatform\Helper;

use Magento\Store\Model\ScopeInterface;

/**
 * Helper base class
 */
class Base extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Store manager object
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * PAYONE shop helper
     *
     * @var \Payone\CommercePlatform\Helper\Shop
     */
    protected $shopHelper;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context      $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Payone\CommercePlatform\Helper\Shop       $shopHelper
     * @param \Magento\Framework\App\State               $state
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Payone\CommercePlatform\Helper\Shop $shopHelper,
        \Magento\Framework\App\State $state
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->shopHelper = $shopHelper;
        $this->state = $state;
    }

    /**
     * Helper method to get parameter from the config divided by the config path elements
     *
     * @param  string $key
     * @param  string $group
     * @param  string $section
     * @param  string $storeCode
     * @return string
     */
    public function getConfigParam($key, $group = 'global', $section = 'payone_cp_general', $storeCode = null)
    {
        $path = $section."/".$group."/".$key;
        return $this->getConfigParamByPath($path, $storeCode);
    }

    /**
     * Helper method to get parameter from the config by path
     *
     * @param  string $path
     * @param  string $storeCode
     * @return string
     */
    public function getConfigParamByPath($path, $storeCode = null)
    {
        $scopeCode = ScopeInterface::SCOPE_STORES;
        if (!$storeCode) {
            list($storeCode, $scopeCode) = $this->fetchCurrentStoreCode();
        }
        return $this->scopeConfig->getValue($path, $scopeCode, $storeCode);
    }

    /**
     * Returns the configured PAYONE merchant id
     *
     * @param  string|int|null $storeCode
     * @return string
     */
    public function getMerchantId($storeCode = null)
    {
        return $this->shopHelper->getConfigParam('merchant_id', 'global', 'payone_cp_general', $storeCode);
    }

    /**
     * Returns operationmode live or test for this payment method
     *
     * @param  string|int|null $storeCode
     * @return string
     */
    public function getOperationMode($storeCode = null)
    {
        return $this->shopHelper->getConfigParam('environment', 'global', 'payone_cp_general', $storeCode);
    }

    /**
     * Trying to fetch the current storeCode
     *
     * Fetching the correct storeCode in the Magento2 backend is very inaccurate
     *
     * @return array
     */
    protected function fetchCurrentStoreCode()
    {
        $scopeCode = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
        $storeCode = $this->storeManager->getStore()->getCode();
        if ($this->state->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML) {
            $storeCode = 0; // 0 = default config, which should be used when neither website nor store parameter are present, storeManager returns default STORE though, which would be wrong
            if (!empty($this->getRequestParameter('website'))) {
                $storeCode = $this->getRequestParameter('website');
                $scopeCode = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES;
            }
            if (!empty($this->getRequestParameter('store'))) {
                $storeCode = $this->getRequestParameter('store');
            }
        }

        return [$storeCode, $scopeCode];
    }

    /**
     * Get parameter from the request
     *
     * @param  string $parameter
     * @return mixed
     */
    public function getRequestParameter($parameter)
    {
        return $this->_getRequest()->getParam($parameter);
    }

    /**
     * Checks if the object is an instance of at least one of the given classes
     *
     * @param object $obj
     * @param array $classes
     * @return bool
     */
    public function instanceOfAny(object $obj, array $classes)
    {
        foreach ($classes as $class) {
            if ($obj instanceof $class) {
                return true;
            }
        }
        return false;
    }
}
