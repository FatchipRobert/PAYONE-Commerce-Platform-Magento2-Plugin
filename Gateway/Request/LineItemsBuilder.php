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

namespace Payone\CommercePlatform\Gateway\Request;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Payone\CommercePlatform\Gateway\AmountConverter;

/**
 * Builds the cart line items of an order, invoice or creditmemo (products, shipping, discount)
 *
 * Used inside the request builders of checkout, capture and refund - not a builder of its own in a command.
 */
class LineItemsBuilder
{
    public const TYPE_GOODS = 'GOODS';
    public const TYPE_SHIPMENT = 'SHIPMENT';
    public const TYPE_HANDLING = 'HANDLING_FEE';
    public const TYPE_DISCOUNT = 'DISCOUNT';

    /**
     * @param AmountConverter $amountConverter
     */
    public function __construct(
        private readonly AmountConverter $amountConverter,
    ) {
    }

    /**
     * Returns all line items of the given order, invoice or creditmemo
     *
     * Shipping and discount are only added if their amount is not 0.
     *
     * @param Order|Invoice|Creditmemo $entity
     * @return array
     */
    public function build(Order|Invoice|Creditmemo $entity): array
    {
        $lineItems = [];
        foreach ($entity->getAllItems() as $item) {
            $orderItem = $entity instanceof Order ? $item : $item->getOrderItem();
            if ($orderItem->isDummy() === false) { // prevent variant products from adding 2 items
                $lineItems[] = $this->getProductLineItem($item, $entity instanceof Order);
            }
        }

        if ((float)$entity->getShippingInclTax() != 0) { // amounts are strings like "0.0000" which would be truthy
            $lineItems[] = $this->getLineItem(
                (string)__('Shipping Costs'),
                'shipping',
                1,
                (float)$entity->getShippingInclTax(),
                (float)$entity->getShippingTaxAmount(),
                self::TYPE_SHIPMENT
            );
        }

        if ((float)$entity->getDiscountAmount() != 0) {
            $lineItems[] = $this->getDiscountLineItem($entity);
        }

        return $lineItems;
    }

    /**
     * Builds the line item for an order, invoice or creditmemo item
     *
     * @param Order\Item|Invoice\Item|Creditmemo\Item $item
     * @param bool $isOrderItem qty is getQtyOrdered() for order items, getQty() for invoice and creditmemo items
     * @return array
     */
    private function getProductLineItem(Order\Item|Invoice\Item|Creditmemo\Item $item, bool $isOrderItem): array
    {
        return $this->getLineItem(
            (string)$item->getName(),
            (string)$item->getSku(),
            (int)($isOrderItem ? $item->getQtyOrdered() : $item->getQty()),
            (float)$item->getPriceInclTax(),
            (float)$item->getTaxAmount(),
            self::TYPE_GOODS
        );
    }

    /**
     * Builds the line item for the discount
     *
     * @param Order|Invoice|Creditmemo $entity
     * @return array
     */
    private function getDiscountLineItem(Order|Invoice|Creditmemo $entity): array
    {
        $description = (string)__('Discount');
        if ($entity->getCouponCode()) {
            $description = __('Coupon').' - '.$entity->getCouponCode();
        }

        return $this->getLineItem(
            $description,
            'discount',
            1,
            (float)$entity->getDiscountAmount() * -1,
            null, // There is no getter for the discount tax - calculating it manually can only be inaccurate
            self::TYPE_DISCOUNT
        );
    }

    /**
     * Builds a single line item
     *
     * @param string $name
     * @param string $sku
     * @param int $quantity
     * @param float $price
     * @param float|null $totalTax optional in the API, null when Magento has no getter for it
     * @param string $type
     * @return array
     */
    private function getLineItem(string $name, string $sku, int $quantity, float $price, ?float $totalTax, string $type): array
    {
        $lineItem = [
            'invoiceData' => [
                'description' => $name,
            ],
            'orderLineDetails' => [
                'productCode' => $sku,
                'productPrice' => $this->amountConverter->toMinorUnits($price),
                'productType' => $type,
                'quantity' => $quantity,
            ],
        ];

        if ($totalTax !== null) {
            $lineItem['orderLineDetails']['taxAmount'] = $this->amountConverter->toMinorUnits($totalTax);
        }

        return $lineItem;
    }
}
