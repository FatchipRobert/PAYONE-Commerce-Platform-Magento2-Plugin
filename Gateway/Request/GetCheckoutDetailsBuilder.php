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
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * Request for GET /commerce-cases/{commerceCaseId}/checkouts/{checkoutId} - no payload
 *
 * Subject: ['order' => order] - uses the store of the order for the credentials
 */
class GetCheckoutDetailsBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
    ) {
    }

    /**
     * Builds the request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $order = $this->subjectReader->readOrder($buildSubject);

        return [
            'uri' => [
                'commerceCaseId' => (string)$order->getData('payone_cp_commerce_case_id'),
                'checkoutId' => (string)$order->getData('payone_cp_checkout_id'),
            ],
            'body' => [],
            'store_id' => (int)$order->getStoreId(),
            'order_increment_id' => (string)$order->getIncrementId(),
            'payment_method' => (string)$order->getPayment()->getMethod(),
        ];
    }
}
