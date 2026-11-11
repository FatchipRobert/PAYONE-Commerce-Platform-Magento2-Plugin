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

namespace Payone\CommercePlatform\Observer\PayInStore;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Model\Payone;

/**
 * Pay in Store: deletes the PAYONE checkout when the order is cancelled (admin or cron), the commerce case stays
 *
 * Event sales_order_payment_cancel - Magento calls no cancel/void command for the method, because the order has no
 * authorization transaction. If the call fails (e.g. the checkout is already paid), the order is not cancelled.
 */
class DeleteCheckout implements ObserverInterface
{
    /**
     * Payment flag: the checkout no longer exists at PAYONE (deleted in the store), no API call
     */
    public const FLAG_CHECKOUT_DELETED = 'payone_cp_checkout_deleted';

    /**
     * @param CommandPoolInterface $commandPool PayoneCPServiceCommandPool
     */
    public function __construct(
        private readonly CommandPoolInterface $commandPool,
    ) {
    }

    /**
     * Deletes the checkout of a cancelled Pay in Store order
     *
     * @param Observer $observer
     * @return void
     * @throws CommandException
     */
    public function execute(Observer $observer): void
    {
        /** @var Payment $payment */
        $payment = $observer->getEvent()->getData('payment');
        if ($payment->getMethod() !== Payone::METHOD_PAY_IN_STORE || $payment->getData(self::FLAG_CHECKOUT_DELETED)) {
            return;
        }

        $order = $payment->getOrder();
        if (!$order->getData('payone_cp_checkout_id')) {
            return;
        }

        $this->commandPool->get('delete_checkout')->execute(['order' => $order]);
    }
}
