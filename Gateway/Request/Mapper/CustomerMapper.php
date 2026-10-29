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

namespace Payone\CommercePlatform\Gateway\Request\Mapper;

use Magento\Sales\Model\Order;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;

/**
 * Maps the customer of an order (billing address, contact and personal data) to the PAYONE customer
 */
class CustomerMapper
{
    /**
     * @param AddressMapper $addressMapper
     * @param GeneralConfig $generalConfig
     */
    public function __construct(
        private readonly AddressMapper $addressMapper,
        private readonly GeneralConfig $generalConfig,
    ) {
    }

    /**
     * Returns the customer part of the commerce case
     *
     * @param Order $order
     * @return array
     */
    public function map(Order $order): array
    {
        $billingAddress = $order->getBillingAddress();

        $customer = [
            'billingAddress' => $this->addressMapper->map($billingAddress),
            'contactDetails' => [
                'emailAddress' => $order->getCustomerEmail(),
            ],
            'personalInformation' => [
                'name' => [
                    'firstName' => $billingAddress->getFirstname(),
                    'surname' => $billingAddress->getLastname(),
                ],
            ],
            'locale' => $this->generalConfig->getLanguage($order->getStoreId()),
            'businessRelation' => !empty($billingAddress->getCompany()) ? 'B2B' : 'B2C',
        ];

        if (!empty($billingAddress->getCompany())) {
            $customer['companyInformation']['name'] = $billingAddress->getCompany();
        }
        if (!empty($billingAddress->getTelephone())) {
            $customer['contactDetails']['phoneNumber'] = $billingAddress->getTelephone();
        }
        if (!empty($order->getCustomerDob()) && $order->getCustomerDob() != '0000-00-00 00:00:00') {
            $customer['personalInformation']['dateOfBirth'] = date('Ymd', strtotime($order->getCustomerDob()));
        }
        if (!empty($order->getCustomerGender())) {
            $customer['personalInformation']['gender'] = $this->getGender((string)$order->getCustomerGender());
        }
        if (!empty($billingAddress->getPrefix())) {
            $customer['personalInformation']['name']['title'] = $billingAddress->getPrefix();
        }

        return $customer;
    }

    /**
     * Maps the Magento gender to the PAYONE gender
     *
     * @param string $gender
     * @return string
     */
    private function getGender(string $gender): string
    {
        return match ($gender) {
            '1' => 'MALE',
            '2' => 'FEMALE',
            default => 'UNKNOWN',
        };
    }
}
