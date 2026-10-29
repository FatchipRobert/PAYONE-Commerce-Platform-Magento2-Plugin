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
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Gateway\AmountConverter;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Observer\OrderPaymentCapture;

/**
 * Payload of the capture of the current invoice
 */
class CaptureBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param AmountConverter $amountConverter
     * @param MerchantReference $merchantReference
     * @param LineItemsBuilder $lineItemsBuilder
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly AmountConverter $amountConverter,
        private readonly MerchantReference $merchantReference,
        private readonly LineItemsBuilder $lineItemsBuilder,
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
        /** @var Payment $payment */
        $payment = $this->subjectReader->readPayment($buildSubject)->getPayment();
        $amount = $this->subjectReader->readAmount($buildSubject);
        $invoice = $this->getCaptureInvoice($payment);

        return [
            'body' => [
                'amount' => $this->amountConverter->toMinorUnits($amount),
                // Final when this capture covers the remaining amount due of the order. The invoice is not paid yet
                // at this point (Invoice::pay() is called after the capture), so the total due still contains it
                'isFinal' => (bool)$payment->isCaptureFinal($amount),
                'references' => [
                    'merchantReference' => $this->merchantReference->getForCapture($payment->getOrder(), $invoice),
                ],
                'delivery' => [
                    'items' => $this->lineItemsBuilder->build($invoice),
                ],
            ],
        ];
    }

    /**
     * Returns the invoice which is currently captured - handed over by the OrderPaymentCapture observer
     *
     * Fallback for captures without the sales_order_payment_capture event: last invoice of the order
     *
     * @param Payment $payment
     * @return Invoice
     */
    private function getCaptureInvoice(Payment $payment): Invoice
    {
        $invoice = $payment->getData(OrderPaymentCapture::PAYMENT_KEY_CAPTURE_INVOICE);
        if ($invoice instanceof Invoice) {
            return $invoice;
        }
        return $payment->getOrder()->getInvoiceCollection()->getLastItem();
    }
}
