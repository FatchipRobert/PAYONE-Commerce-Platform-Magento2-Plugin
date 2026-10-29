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
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\Request\Mapper\CustomerMapper;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * Top level of POST /commerce-cases: the commerce case with the customer
 */
class CommerceCaseBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param MerchantReference $merchantReference
     * @param CustomerMapper $customerMapper
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly MerchantReference $merchantReference,
        private readonly CustomerMapper $customerMapper,
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

        return [
            'body' => [
                'merchantReference' => $this->merchantReference->get($order, MerchantReference::PREFIX_COMMERCE_CASE),
                'customer' => $this->customerMapper->map($order),
            ],
        ];
    }
}
