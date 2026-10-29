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

namespace Payone\CommercePlatform\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Copies the input of the checkout JS (additional_data) into the additional_information of the payment
 *
 * One virtualType per payment method in etc/di.xml with the keys as argument,
 * registered for payment_method_assign_data_<method code> in etc/events.xml.
 */
class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * @param string[] $additionalInformationKeys
     */
    public function __construct(
        private readonly array $additionalInformationKeys = [],
    ) {
    }

    /**
     * Copies the configured keys, empty values are skipped
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $additionalData = $this->readDataArgument($observer)->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $payment = $this->readPaymentModelArgument($observer);
        foreach ($this->additionalInformationKeys as $key) {
            if (!empty($additionalData[$key])) {
                $payment->setAdditionalInformation($key, $additionalData[$key]);
            }
        }
    }
}
