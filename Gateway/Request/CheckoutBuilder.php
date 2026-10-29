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

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Payone\CommercePlatform\Gateway\AmountConverter;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\Request\Mapper\AddressMapper;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * "checkout" of POST /commerce-cases: amount, cart and shipping address, executed directly with the order
 */
class CheckoutBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param AmountConverter $amountConverter
     * @param MerchantReference $merchantReference
     * @param LineItemsBuilder $lineItemsBuilder
     * @param AddressMapper $addressMapper
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly AmountConverter $amountConverter,
        private readonly MerchantReference $merchantReference,
        private readonly LineItemsBuilder $lineItemsBuilder,
        private readonly AddressMapper $addressMapper,
    ) {
    }

    /**
     * Builds the request part
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $order = $this->subjectReader->readPayment($buildSubject)->getPayment()->getOrder();

        // initialize (redirect methods) has no amount in the subject
        $amount = isset($buildSubject['amount'])
            ? $this->subjectReader->readAmount($buildSubject)
            : (float)$order->getTotalDue();

        $checkout = [
            'amountOfMoney' => [
                'amount' => $this->amountConverter->toMinorUnits($amount),
                'currencyCode' => (string)$order->getOrderCurrencyCode(),
            ],
            'references' => [
                'merchantReference' => $this->merchantReference->get($order, MerchantReference::PREFIX_CHECKOUT),
                'merchantShopReference' => (string)$order->getStore()->getName(),
            ],
            'autoExecuteOrder' => true,
            'shoppingCart' => [
                'items' => $this->lineItemsBuilder->build($order),
            ],
        ];

        $shipping = $this->getShipping($order);
        if ($shipping !== null) {
            $checkout['shipping'] = $shipping;
        }

        return ['body' => ['checkout' => $checkout]];
    }

    /**
     * Returns the shipping address with the name of the recipient, null for orders without shipping (virtual)
     *
     * @param Order $order
     * @return array|null
     */
    private function getShipping(Order $order): ?array
    {
        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress) {
            return null;
        }

        $address = $this->addressMapper->map($shippingAddress);
        $address['name'] = [
            'firstName' => $shippingAddress->getFirstname(),
            'surname' => $shippingAddress->getLastname(),
        ];
        if (!empty($shippingAddress->getPrefix())) {
            $address['name']['title'] = $shippingAddress->getPrefix();
        }

        return ['address' => $address];
    }
}
