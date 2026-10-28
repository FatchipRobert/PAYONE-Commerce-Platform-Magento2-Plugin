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
use Magento\Sales\Model\Order\Invoice;
use Payone\CommercePlatform\Observer\OrderPaymentCapture;

class Capture extends LineItemRequest
{
    /**
     * @var string
     */
    protected $endpoint = '/v1/{merchantId}/commerce-cases/{commerceCaseId}/checkouts/{checkoutId}/payment-executions/{paymentExecutionId}/capture';

    /**
     * @var string
     */
    protected $httpMethod = self::METHOD_POST;

    /**
     * Final when this capture covers the remaining amount due of the order
     *
     * The invoice is not paid yet at this point (Invoice::pay() is called after the capture), so the total due still contains it
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return bool
     */
    protected function isFinal(InfoInterface $payment, $amount)
    {
        return $payment->isCaptureFinal($amount);
    }

    /**
     * Returns the invoice which is currently captured - handed over by the OrderPaymentCapture observer
     *
     * Fallback for captures without the sales_order_payment_capture event: last invoice of the order
     *
     * @param InfoInterface $payment
     * @return Invoice
     */
    protected function getCaptureInvoice(InfoInterface $payment)
    {
        $invoice = $payment->getData(OrderPaymentCapture::PAYMENT_KEY_CAPTURE_INVOICE);
        if ($invoice instanceof Invoice) {
            return $invoice;
        }
        return $payment->getOrder()->getInvoiceCollection()->getLastItem();
    }

    /**
     * Builds the payload for the capture of the current invoice
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return array
     */
    public function populateParameters(InfoInterface $payment, $amount)
    {
        $this->setOrder($payment->getOrder());

        $invoice = $this->getCaptureInvoice($payment);

        $parameters = [
            'amount' => $this->apiHelper->formatAmount($amount),
            'isFinal' => $this->isFinal($payment, $amount),
            #'cancellationReason' => 'WHAT IS NEEDED HERE? WHY CANCELLATION IN CAPTURE?',
            'references' => [
                'merchantReference' => $this->getCaptureMerchantReference($payment, $invoice),
            ],
            'delivery' => [
                'items' => $this->getLineItems($invoice),
            ]
        ];

        $this->setParameters($parameters);

        return $parameters;
    }
}
