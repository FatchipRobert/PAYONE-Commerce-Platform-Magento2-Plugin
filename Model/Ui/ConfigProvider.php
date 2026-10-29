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

namespace Payone\CommercePlatform\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\Payone;

/**
 * Checkout config shared by all PAYONE methods: window.checkoutConfig.payment.payone_cp.* and the instructions
 *
 * The method specific values (payone_cp.<method code>) come from the ConfigProviders of the methods.
 */
class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     * @param Session $checkoutSession
     * @param GeneralConfig $generalConfig
     */
    public function __construct(
        private readonly PaymentHelper $paymentHelper,
        private readonly Escaper $escaper,
        private readonly Session $checkoutSession,
        private readonly GeneralConfig $generalConfig,
    ) {
    }

    /**
     * Returns the checkout config
     *
     * @return array
     */
    public function getConfig(): array
    {
        $config = ['payment' => [
            'payone_cp' => [
                'mode' => $this->generalConfig->getEnvironment(),
                'fullLocale' => $this->generalConfig->getLocale(),
                // set by Controller/Onepage/Returned, read once and cleared
                'checkoutErrorMessage' => $this->checkoutSession->getData('payone_cp_checkout_error_message', true),
            ],
        ]];

        foreach (Payone::METHODS as $methodCode) {
            $method = $this->paymentHelper->getMethodInstance($methodCode);
            if ($method->isAvailable()) {
                $config['payment']['instructions'][$methodCode] = nl2br(
                    $this->escaper->escapeHtml((string)$method->getConfigData('instructions'))
                );
            }
        }

        return $config;
    }
}
