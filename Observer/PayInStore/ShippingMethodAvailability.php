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

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Payone\CommercePlatform\Model\Payone;

/**
 * Pay in Store is only available with Magento's In-Store Pickup shipping method
 *
 * Event payment_method_is_active - used for the method list of the checkout and when the method is set on the quote.
 */
class ShippingMethodAvailability implements ObserverInterface
{
    /**
     * Shipping method of Magento In-Store Pickup (MSI, carrier "instore")
     */
    public const SHIPPING_METHOD_IN_STORE_PICKUP = 'instore_pickup';

    /**
     * Hides Pay in Store unless In-Store Pickup is the selected shipping method
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        /** @var MethodInterface $method */
        $method = $event->getData('method_instance');
        /** @var DataObject $result */
        $result = $event->getData('result');
        if ($method->getCode() !== Payone::METHOD_PAY_IN_STORE || !$result->getData('is_available')) {
            return;
        }

        /** @var Quote|null $quote */
        $quote = $event->getData('quote');
        if (!$quote || !$this->isInStorePickup($quote)) {
            $result->setData('is_available', false);
        }
    }

    /**
     * Whether In-Store Pickup is the shipping method of the quote
     *
     * @param Quote $quote
     * @return bool
     */
    private function isInStorePickup(Quote $quote): bool
    {
        if ($quote->isVirtual()) {
            return false;
        }
        return $quote->getShippingAddress()->getShippingMethod() === self::SHIPPING_METHOD_IN_STORE_PICKUP;
    }
}
