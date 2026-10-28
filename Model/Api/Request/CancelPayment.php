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

class CancelPayment extends BaseRequest
{
    /**
     * @var string
     */
    protected $endpoint = '/v1/{merchantId}/commerce-cases/{commerceCaseId}/checkouts/{checkoutId}/payment-executions/{paymentExecutionId}/cancel';

    /**
     * @var string
     */
    protected $httpMethod = self::METHOD_POST;

    /**
     * Builds the payload for the cancellation of the given amount
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return array
     */
    public function populateParameters(InfoInterface $payment, $amount)
    {
        $this->setOrder($payment->getOrder());

        $parameters = [
            'amount' => $this->apiHelper->formatAmount($amount),
        ];

        $this->setParameters($parameters);

        return $parameters;
    }
}
