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
use Magento\Payment\Helper\Data as PaymentHelper;
use Payone\CommercePlatform\Gateway\Config\ApplePayConfig;
use Payone\CommercePlatform\Model\Payone;

/**
 * Checkout config of the Apple Pay method: payment request data for the ApplePaySession
 *
 * No config without merchant identifier, certificate and private key - the JS removes the method then.
 */
class ApplePayConfigProvider implements ConfigProviderInterface
{
    /**
     * @param PaymentHelper $paymentHelper
     * @param ApplePayConfig $applePayConfig
     */
    public function __construct(
        private readonly PaymentHelper $paymentHelper,
        private readonly ApplePayConfig $applePayConfig,
    ) {
    }

    /**
     * Returns window.checkoutConfig.payment.payone_cp.payone_cp_applepay
     *
     * @return array
     */
    public function getConfig(): array
    {
        $code = Payone::METHOD_APPLEPAY;
        if (!$this->paymentHelper->getMethodInstance($code)->isAvailable()
            || !$this->applePayConfig->isConfigurationComplete()
        ) {
            return [];
        }

        return ['payment' => ['payone_cp' => [$code => [
            'displayName' => $this->applePayConfig->getDisplayName(),
            'countryCode' => $this->applePayConfig->getCountryCode(),
            'supportedNetworks' => $this->applePayConfig->getSupportedNetworks(),
        ]]]];
    }
}
