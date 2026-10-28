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

namespace Payone\CommercePlatform\Model\Api\Request;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Payment\Model\InfoInterface;

class LineItemRequest extends BaseRequest
{
    public const TYPE_GOODS = 'GOODS';
    public const TYPE_SHIPMENT = 'SHIPMENT';
    public const TYPE_HANDLING = 'HANDLING_FEE';
    public const TYPE_DISCOUNT = 'DISCOUNT';

    /**
     * Returns the merchantReference of the capture of the given invoice
     * Invoices are numbered by creation: 1st invoice cp<increment_id>, 2nd cp<increment_id>-2, ...
     * Works for the invoice which is currently captured (not saved yet) and for saved invoices (refund)
     *
     * @param InfoInterface $payment
     * @param Invoice $invoice
     * @return string
     */
    protected function getCaptureMerchantReference(InfoInterface $payment, Invoice $invoice)
    {
        $position = 1;
        foreach ($payment->getOrder()->getInvoiceCollection() as $orderInvoice) {
            if (empty($orderInvoice->getId()) || $orderInvoice->getId() == $invoice->getId()) {
                continue;
            }
            if (empty($invoice->getId()) || $orderInvoice->getId() < $invoice->getId()) {
                $position++; // invoice created before the given one
            }
        }

        $referenceSuffix = $position > 1 ? '-'.$position : '';
        return $this->getMerchantReference($payment, self::REFERENCE_PREFIX_CAPTURE).$referenceSuffix;
    }

    /**
     * Builds a single cart line item
     *
     * @param string $name
     * @param string $sku
     * @param int $quantity
     * @param float $price
     * @param float $totalTax
     * @param string $type
     * @return array[]
     */
    protected function getLineItem($name, $sku, $quantity, $price, $totalTax, $type)
    {
        $lineItem = [
            'invoiceData' => [
                'description' => $name,
            ],
            'orderLineDetails' => [
                'productCode' => $sku,
                'productPrice' => (int)$this->apiHelper->formatAmount($price),
                'productType' => $type,
                'quantity' => (int)$quantity,
            ],
        ];

        if ($totalTax !== null) { // for some elements magento has no getter for the tax amount and in the API it is optional
            $lineItem['orderLineDetails']['taxAmount'] = (int)$this->apiHelper->formatAmount($totalTax); // amount in cents like productPrice
        }

        return $lineItem;
    }

    /**
     * Builds the cart line item for an order, invoice or creditmemo item
     *
     * @param \Magento\Sales\Model\Order\Item|\Magento\Sales\Model\Order\Invoice\Item|\Magento\Sales\Model\Order\Creditmemo\Item $item
     * @return array[]
     */
    protected function getProductLineItem($item)
    {
        $qty = null;

        if ($item instanceof \Magento\Sales\Model\Order\Item) {
            $qty = $item->getQtyOrdered();
        } else {
            $qty = $item->getQty();
        }

        // qty is different for OrderItem, InvoiceItem and CreditmemoItem
        return $this->getLineItem(
            $item->getName(),
            $item->getSku(),
            (int)$qty,
            (float)$item->getPriceInclTax(),
            (float)$item->getTaxAmount(),
            self::TYPE_GOODS
        );
    }

    /**
     * Builds the cart line item for the shipping costs
     *
     * @param Order $order
     * @return array[]
     */
    protected function addShippingLineItem($order)
    {
        return $this->getLineItem(
            __('Shipping Costs'),
            'shipping',
            1,
            (float)$order->getShippingInclTax(),
            (float)$order->getShippingTaxAmount(),
            self::TYPE_SHIPMENT
        );
    }

    /**
     * Builds the cart line item for the discount
     *
     * @param Order $order
     * @return array[]
     */
    protected function getDiscountLineItem($order)
    {
        $desc = __('Discount');
        if ($order->getCouponCode()) {
            $desc = __('Coupon').' - '.$order->getCouponCode(); // add counpon code to description
        }

        return $this->getLineItem(
            $desc,
            'discount',
            1,
            (float)($order->getDiscountAmount() * -1),
            null, // There is no getter for the discount tax - we would have to calculate it manually which can only be inaccurate
            self::TYPE_DISCOUNT
        );
    }

    /**
     * Checks if the object is an instance of at least one of the given classes
     *
     * @param mixed $entity
     * @param string[] $classes
     * @return bool
     */
    protected function isInstanceOfAny($entity, array $classes)
    {
        foreach ($classes as $class) {
            if ($entity instanceof $class) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns all cart line items of the given quote, order, invoice or creditmemo
     *
     * @param mixed $entity
     * @return array
     */
    protected function getLineItems($entity)
    {
        $lineItems = [];

        if (!$this->isInstanceOfAny($entity, [Quote::class, Order::class, Invoice::class, Creditmemo::class])) {
            return $lineItems;
        }

        foreach ($entity->getAllItems() as $item) {
            if (($entity instanceof Order && $item->isDummy() === false) ||
                ($entity instanceof Quote && $item->getParentItemId() === null) ||
                ($this->isInstanceOfAny($entity, [Invoice::class, Creditmemo::class]) && $item->getOrderItem()->isDummy() === false)
            ) { // prevent variant-products of adding 2 items
                $lineItems[] = $this->getProductLineItem($item);
            }
        }

        if ((float)$entity->getShippingInclTax() != 0) { // amounts are strings like "0.0000" which would be truthy
            $lineItems[] = $this->addShippingLineItem($entity);
        }

        if ((float)$entity->getDiscountAmount() != 0) {
            $lineItems[] = $this->getDiscountLineItem($entity);
        }

        return $lineItems;
    }
}
