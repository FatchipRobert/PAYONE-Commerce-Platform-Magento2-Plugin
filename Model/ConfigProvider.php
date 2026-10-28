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

namespace Payone\CommercePlatform\Model;

use Payone\CommercePlatform\Model\Method\BaseMethod;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * Payment helper object
     *
     * @var \Magento\Payment\Helper\Data
     */
    protected $dataHelper;

    /**
     * Payone payment helper
     *
     * @var \Payone\CommercePlatform\Helper\Payment
     */
    protected $paymentHelper;

    /**
     * Escaper object
     *
     * @var \Magento\Framework\Escaper
     */
    protected $escaper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Payone\CommercePlatform\Gateway\Config\GeneralConfig
     */
    protected $generalConfig;

    /**
     * Constructor
     *
     * @param \Magento\Payment\Helper\Data                          $dataHelper
     * @param \Payone\CommercePlatform\Helper\Payment               $paymentHelper
     * @param \Magento\Framework\Escaper                            $escaper
     * @param \Magento\Checkout\Model\Session                       $checkoutSession
     * @param \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
     */
    public function __construct(
        \Magento\Payment\Helper\Data $dataHelper,
        \Payone\CommercePlatform\Helper\Payment $paymentHelper,
        \Magento\Framework\Escaper $escaper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
    ) {
        $this->dataHelper = $dataHelper;
        $this->paymentHelper = $paymentHelper;
        $this->escaper = $escaper;
        $this->checkoutSession = $checkoutSession;
        $this->generalConfig = $generalConfig;
    }

    /**
     * Returns Payone custom config for config params not specifically tied to a payment method
     *
     * @return array
     */
    protected function getPayoneCustomConfig()
    {
        $config = [
            'mode' => $this->generalConfig->getEnvironment(),
            'fullLocale' => $this->generalConfig->getLocale(),
            'checkoutErrorMessage' => $this->checkoutSession->getData('payone_cp_checkout_error_message', true), // read once and clear
        ];
        return $config;
    }

    /**
     * Get the payment instruction text
     *
     * @param  BaseMethod $methodInstance
     * @return string
     */
    protected function getInstructionByCode($methodInstance)
    {
        return nl2br($this->escaper->escapeHtml($methodInstance->getConfigData('instructions')));
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $config = ['payment' => [
            'payone_cp' => $this->getPayoneCustomConfig(),
        ]];

        foreach ($this->paymentHelper->getAvailablePaymentTypes() as $methodCode) {
            $methodInstance = $this->dataHelper->getMethodInstance($methodCode);
            if ($methodInstance instanceof BaseMethod && $methodInstance->isAvailable()) {
                $config['payment']['payone_cp'][$methodCode] = $methodInstance->getFrontendConfig();
                $config['payment']['instructions'][$methodCode] = $this->getInstructionByCode($methodInstance);
            }
        }
        return $config;
    }
}
