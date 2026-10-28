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
use Payone\CommercePlatform\Helper\Order as OrderHelper;

class Refund extends LineItemRequest
{
    /**
     * @var string
     */
    protected $endpoint = '/v1/{merchantId}/commerce-cases/{commerceCaseId}/checkouts/{checkoutId}/payment-executions/{paymentExecutionId}/refund';

    /**
     * @var string
     */
    protected $httpMethod = self::METHOD_POST;

    /**
     * Returns the merchantReference of the capture this refund belongs to
     * Invoices created by the payment.captured webhook (automatic capture, SALE in the payment execution)
     * carry "<payment id>-sale" (older invoices: the payment id) as transaction id
     * No separate capture event exists for them, so no reference is sent
     *
     * @param InfoInterface $payment
     * @return string|null
     */
    protected function getCaptureReference(InfoInterface $payment)
    {
        $creditmemo = $payment->getCreditmemo();
        $invoice = $creditmemo ? $creditmemo->getInvoice() : null;
        if (!$invoice) {
            return $this->getMerchantReference($payment, self::REFERENCE_PREFIX_CAPTURE);
        }
        $paymentId = $payment->getOrder()->getData('payone_cp_payment_id');
        if (in_array($invoice->getTransactionId(), [$paymentId, $paymentId.OrderHelper::TRANSACTION_SUFFIX_SALE], true)) {
            return null;
        }
        return $this->getCaptureMerchantReference($payment, $invoice);
    }

    /**
     * Builds the payload for the refund of the current creditmemo
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return array
     */
    public function populateParameters(InfoInterface $payment, $amount)
    {
        $this->setOrder($payment->getOrder());

        $references = [
            'merchantReference' => $this->getMerchantReference($payment, self::REFERENCE_PREFIX_REFUND),
        ];
        $captureReference = $this->getCaptureReference($payment);
        if ($captureReference !== null) {
            $references['captureReference'] = $captureReference;
        }

        $parameters = [
            'amountOfMoney' => [
                'amount' => $this->apiHelper->formatAmount($amount),
                'currencyCode' => $payment->getOrder()->getOrderCurrencyCode(),
            ],
            'references' => $references,
            'return' => [
                'items' => $this->getLineItems($payment->getCreditmemo()),
            ]
        ];

        $this->setParameters($parameters);

        return $parameters;
    }
}
