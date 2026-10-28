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
use Payone\CommercePlatform\Helper\Country;

class CommerceCase extends BaseRequest
{
    /**
     * @var string
     */
    protected $endpoint = '/v1/{merchantId}/commerce-cases';

    /**
     * @var string
     */
    protected $httpMethod = self::METHOD_POST;

    /**
     * Builds the payload for a new commerce case
     *
     * @param InfoInterface $payment
     * @return array
     */
    public function populateParameters(InfoInterface $payment)
    {
        $this->setOrder($payment->getOrder());

        $parameters = [
            'merchantReference' => $this->getMerchantReference($payment, self::REFERENCE_PREFIX_COMMERCECASE),
            'customer' => $this->getCustomerParameters($payment),
        ];

        $this->setParameters($parameters);

        return $parameters;
    }

    /**
     * Checks if the order is a business order (billing address with company)
     *
     * @param Order $order
     * @return bool
     */
    protected function isB2BOrder(Order $order)
    {
        if (!empty($order->getBillingAddress()->getCompany())) {
            return true;
        }
        return false;
    }

    /**
     * Returns the customer part of the payload (addresses, contact and personal data)
     *
     * @param InfoInterface $payment
     * @return array
     */
    protected function getCustomerParameters(InfoInterface $payment)
    {
        $order = $payment->getOrder();

        $billingAddress = $order->getBillingAddress();
        $street = $billingAddress->getStreet();

        $parameters = [
            'billingAddress' => [
                'street' => trim((is_array($street) ? implode(' ', $street) : $street) ?? ''),
                #'houseNumber' => '', // Magento2 does not provide this field separately - does this have to be split manually?
                'zip' => $billingAddress->getPostcode(),
                'city' => $billingAddress->getCity(),
                'countryCode' => $billingAddress->getCountryId(),
                #'additionalInfo' => '', // Magento2 does not provide this field separately
            ],
            'contactDetails' => [
                'emailAddress' => $order->getCustomerEmail(),
            ],
            'personalInformation' => [
                'name' => [
                    'firstName' => $billingAddress->getFirstname(),
                    'surname' => $billingAddress->getLastname(),
                ]
            ],
            'locale' => $this->generalConfig->getLanguage($this->storeId),
            'businessRelation' => $this->isB2BOrder($order) === true ? 'B2B' : 'B2C',
        ];

        if (!empty($billingAddress->getRegionCode()) && Country::isStateNeeded($billingAddress->getCountryId()) === true) {
            $parameters['billingAddress']['state'] = $this->customerHelper->getRegionCode($billingAddress);
        }
        if (!empty($billingAddress->getCompany())) {
            $parameters['companyInformation']['name'] = $billingAddress->getCompany();
        }
        if (!empty($billingAddress->getTelephone())) {
            $parameters['contactDetails']['phoneNumber'] = $billingAddress->getTelephone();
        }
        if (!empty($order->getCustomerDob()) && $order->getCustomerDob() != '0000-00-00 00:00:00') {
            $parameters['personalInformation']['dateOfBirth'] = date('Ymd', strtotime($order->getCustomerDob()));
        }
        if (!empty($order->getCustomerGender())) {
            $parameters['personalInformation']['gender'] = $this->customerHelper->getGenderParameter($order->getCustomerGender());
        }
        if (!empty($billingAddress->getPrefix())) {
            $parameters['personalInformation']['name']['title'] = $billingAddress->getPrefix();
        }

        return $parameters;
    }
}
