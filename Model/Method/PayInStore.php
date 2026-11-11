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

namespace Payone\CommercePlatform\Model\Method;

use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Model\Payone;
use Psr\Log\LoggerInterface;

/**
 * Pay in Store: offline method, the customer pays when picking up the order in the store
 *
 * Only available with Magento's In-Store Pickup. The own command pool (etc/di.xml) only has "initialize": a commerce
 * case with checkout but without order. The payment in the store is polled by Cron/CheckPayInStoreOrders; when the
 * order is cancelled in Magento the checkout is deleted at PAYONE.
 */
class PayInStore extends PayoneMethod
{
    protected const CODE = Payone::METHOD_PAY_IN_STORE;

    /**
     * Shipping method of Magento In-Store Pickup (MSI, carrier "instore")
     */
    public const SHIPPING_METHOD_IN_STORE_PICKUP = 'instore_pickup';

    /**
     * Payment flag: the checkout no longer exists at PAYONE (deleted in the store), no API call on cancellation
     */
    public const FLAG_CHECKOUT_DELETED = 'payone_cp_checkout_deleted';

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
     * @param CommandPoolInterface $serviceCommandPool PayoneCPServiceCommandPool
     * @param ValidatorPoolInterface|null $validatorPool
     */
    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        string $formBlockType,
        string $infoBlockType,
        CommandPoolInterface $commandPool,
        LoggerInterface $logger,
        private readonly CommandPoolInterface $serviceCommandPool,
        ?ValidatorPoolInterface $validatorPool = null,
    ) {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $logger,
            $validatorPool
        );
    }

    /**
     * Only available if In-Store Pickup is the shipping method of the quote
     *
     * Checked for the method list of the checkout and when the method is set on the quote.
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(?CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote)
            && $quote instanceof Quote
            && !$quote->isVirtual()
            && $quote->getShippingAddress()->getShippingMethod() === self::SHIPPING_METHOD_IN_STORE_PICKUP;
    }

    /**
     * Deletes the PAYONE checkout of the cancelled order (admin or cron), the commerce case stays
     *
     * Only possible while the checkout is OPEN - if it is already paid, the call fails and the order is not cancelled.
     *
     * @param Payment $payment
     * @return void
     * @throws CommandException
     */
    public function onCancel(Payment $payment): void
    {
        $order = $payment->getOrder();
        if ($payment->getData(self::FLAG_CHECKOUT_DELETED) || !$order->getData('payone_cp_checkout_id')) {
            return;
        }

        $this->serviceCommandPool->get('delete_checkout')->execute(['order' => $order]);
    }
}
