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
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\Country;
use Payone\CommercePlatform\Model\Payone;

/**
 * Checkout config of the SEPA direct debit method: allowed bank countries, BIC field
 */
class DirectDebitConfigProvider implements ConfigProviderInterface
{
    /**
     * @param PaymentHelper $paymentHelper
     * @param GeneralConfig $generalConfig
     * @param Country $country
     */
    public function __construct(
        private readonly PaymentHelper $paymentHelper,
        private readonly GeneralConfig $generalConfig,
        private readonly Country $country,
    ) {
    }

    /**
     * Returns window.checkoutConfig.payment.payone_cp.payone_cp_directdebit
     *
     * @return array
     */
    public function getConfig(): array
    {
        $code = Payone::METHOD_DIRECT_DEBIT;
        if (!$this->paymentHelper->getMethodInstance($code)->isAvailable()) {
            return [];
        }

        return ['payment' => ['payone_cp' => [$code => [
            'sepaCountries' => $this->country->getEnabledCountries($code),
            'requestBic' => (bool)$this->generalConfig->getPaymentValue($code, 'request_bic'),
        ]]]];
    }
}
