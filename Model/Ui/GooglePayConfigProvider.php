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
use Magento\Payment\Helper\Data as PaymentHelper;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\Payone;

/**
 * Checkout config of the Google Pay method: gateway merchant id and store name for the Google Pay JS
 */
class GooglePayConfigProvider implements ConfigProviderInterface
{
    private const DEFAULT_STORE_NAME = 'Online Store';

    /**
     * @param PaymentHelper $paymentHelper
     * @param GeneralConfig $generalConfig
     * @param Session $checkoutSession
     */
    public function __construct(
        private readonly PaymentHelper $paymentHelper,
        private readonly GeneralConfig $generalConfig,
        private readonly Session $checkoutSession,
    ) {
    }

    /**
     * Returns window.checkoutConfig.payment.payone_cp.payone_cp_googlepay
     *
     * @return array
     */
    public function getConfig(): array
    {
        $code = Payone::METHOD_GOOGLEPAY;
        if (!$this->paymentHelper->getMethodInstance($code)->isAvailable()) {
            return [];
        }

        return ['payment' => ['payone_cp' => [$code => [
            'merchantId' => $this->generalConfig->getMerchantId(),
            'storeName' => $this->getStoreName(),
            'googlePayMerchantId' => $this->generalConfig->getPaymentValue($code, 'google_pay_merchant_id'),
            'operationMode' => $this->generalConfig->getEnvironment(),
        ]]]];
    }

    /**
     * Returns the configured store name, the frontend name of the store as fallback
     *
     * @return string
     */
    private function getStoreName(): string
    {
        $storeName = (string)$this->generalConfig->getPaymentValue(Payone::METHOD_GOOGLEPAY, 'store_name');
        if ($storeName === '') {
            $storeName = (string)$this->checkoutSession->getQuote()->getStore()->getFrontendName();
        }
        return $storeName !== '' ? $storeName : self::DEFAULT_STORE_NAME;
    }
}
