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
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Model\Method\PayoneMethod;

/**
 * Hands the cancellation of an order to its PAYONE payment method (PayoneMethod::onCancel())
 *
 * Event sales_order_payment_cancel - Magento itself only calls the void/cancel command of a method if the order has an
 * authorization transaction.
 */
class OrderPaymentCancel implements ObserverInterface
{
    /**
     * Executed in Payment::cancel(), an exception stops the cancellation of the order
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Payment $payment */
        $payment = $observer->getEvent()->getData('payment');
        $method = $payment->getMethodInstance();
        if ($method instanceof PayoneMethod) {
            $method->onCancel($payment);
        }
    }
}
