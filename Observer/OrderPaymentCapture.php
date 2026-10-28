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

namespace Payone\CommercePlatform\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Payone\CommercePlatform\Model\Method\BaseMethod;

/**
 * Event class to make the invoice which is currently captured available to the capture request
 * The invoice is not saved yet during an online capture, so it can't be read from the order invoice collection
 */
class OrderPaymentCapture implements ObserverInterface
{
    public const PAYMENT_KEY_CAPTURE_INVOICE = 'payone_cp_capture_invoice';

    /**
     * Executed in ProcessInvoiceOperation right before the capture method of the payment method is called
     *
     * @param  Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $payment = $observer->getPayment();
        $invoice = $observer->getInvoice();
        if (empty($payment) || empty($invoice) || !$payment->getMethodInstance() instanceof BaseMethod) {
            return;
        }

        $payment->setData(self::PAYMENT_KEY_CAPTURE_INVOICE, $invoice);
    }
}
