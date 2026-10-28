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

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote;
use Payone\CommercePlatform\Helper\Country;

class Checkout extends LineItemRequest
{
    /**
     * @var string
     */
    protected $endpoint = '/v1/{merchantId}/commerce-cases/{commerceCaseId}/checkouts';

    /**
     * @var string
     */
    protected $httpMethod = self::METHOD_POST;

    /**
     * Builds the payload for a new checkout in an existing commerce case
     *
     * @param InfoInterface $payment
     * @param float         $amount
     * @param string|null   $commerceCaseId
     * @return array
     */
    public function populateParameters(InfoInterface $payment, $amount, $commerceCaseId = null)
    {
        if (!empty($commerceCaseId)) {
            $this->setEndpointParameters([
                'commerceCaseId' => $commerceCaseId
            ]);
        }

        $this->setOrder($payment->getOrder());

        $parameters = $this->getCheckoutParameters($payment, $amount);

        $this->setParameters($parameters);

        return $parameters;
    }

    /**
     * Adds the shipping address of the order to the checkout payload
     *
     * @param array $parameters
     * @param Order $order
     * @return void
     */
    protected function addShippingAddress(array &$parameters, Order $order)
    {
        $shippingAddress = $order->getShippingAddress(); // get shipping address from order
        if ($shippingAddress) {
            $street = $shippingAddress->getStreet();

            $parameters['shipping']['address'] = [
                'street' => trim((is_array($street) ? implode(' ', $street) : $street) ?? ''),
                #'houseNumber' => '', // Magento2 does not provide this field separately - does this have to be split manually?
                'zip' => $shippingAddress->getPostcode(),
                'city' => $shippingAddress->getCity(),
                'countryCode' => $shippingAddress->getCountryId(),
                #'additionalInfo' => '', // Magento2 does not provide this field separately
                'name' => [
                    'firstName' => $shippingAddress->getFirstname(),
                    'surname' => $shippingAddress->getLastname(),
                ]
            ];

            if (!empty($shippingAddress->getRegionCode()) && Country::isStateNeeded($shippingAddress->getCountryId()) === true) {
                $parameters['shipping']['address']['state'] = $this->customerHelper->getRegionCode($shippingAddress);
            }

            if (!empty($shippingAddress->getPrefix())) {
                $parameters['shipping']['address']['name']['title'] = $shippingAddress->getPrefix();
            }
        }
    }

    /**
     * Returns the checkout part of the payload (amount, references, cart, shipping)
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return array
     */
    protected function getCheckoutParameters(InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();

        $parameters = [
            'amountOfMoney' => [
                'amount' => $this->apiHelper->formatAmount($amount),
                'currencyCode' => $order->getOrderCurrencyCode(),
            ],
            'references' => [
                'merchantReference' => $this->getMerchantReference($payment, self::REFERENCE_PREFIX_CHECKOUT),
                'merchantShopReference' => $order->getStore()->getName(),
            ],
            'autoExecuteOrder' => false,
            'shoppingCart' => [
                'items' => $this->getLineItems($order)
            ],
        ];

        $this->addShippingAddress($parameters, $order);

        return $parameters;
    }
}
