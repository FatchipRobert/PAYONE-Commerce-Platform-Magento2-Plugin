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
use Magento\Checkout\Model\Session;

/**
 * Cleans up the checkout session after the order has been submitted successfully
 */
class CheckoutSubmitAllAfter implements ObserverInterface
{
    /**
     * Checkout session object
     *
     * @var Session
     */
    protected $checkoutSession;

    /**
     * Array of keys to remove from session after payment was successful
     *
     * @var array
     */
    protected $checkoutSessionClearList = [];

    /**
     * Constructor
     *
     * @param Session       $checkoutSession
     */
    public function __construct(Session $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Removes the keys of $checkoutSessionClearList from the checkout session
     *
     * @return void
     */
    protected function clearCheckoutSession()
    {
        foreach ($this->checkoutSessionClearList as $cleanParam) {
            $this->checkoutSession->unsetData($cleanParam);
        }
    }

    /**
     * Execute certain tasks after the order has been saved and the quote has been deactivated
     *
     * @param  Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $this->clearCheckoutSession();
    }
}
