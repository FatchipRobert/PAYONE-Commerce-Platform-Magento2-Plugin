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
declare(strict_types=1);

namespace Payone\CommercePlatform\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Payone\CommercePlatform\Model\Method\PayoneMethod;

/**
 * Reduces the checkout input of the quote payment like the one of the order payment (PayoneMethod::reduceCheckoutInput())
 *
 * Event sales_model_service_quote_submit_success - dispatched after the order was placed, the quote is saved afterwards.
 */
class QuoteSubmitSuccess implements ObserverInterface
{
    /**
     * Executed in QuoteManagement::submit() for every placed order, only successful payment requests get here
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getData('quote');
        $payment = $quote->getPayment();
        $method = $payment->getMethodInstance();
        if ($method instanceof PayoneMethod) {
            $method->reduceCheckoutInput($payment);
        }
    }
}
