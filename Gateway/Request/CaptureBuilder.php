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
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Observer\OrderPaymentCapture;

/**
 * Payload of the capture of the current invoice
 */
class CaptureBuilder implements BuilderInterface
{
    /**
     * Reason for a final capture below the authorized amount (mandatory for Buy Now Pay Later)
     */
    public const CANCELLATION_REASON_FINAL_PARTIAL = 'ORDER_SHIPPED_IN_FULL';

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
        $order = $payment->getOrder();
        // Final when this capture covers the remaining amount due of the order. The invoice is not paid yet
        // at this point (Invoice::pay() is called after the capture), so the total due still contains it
        $isFinal = (bool)$payment->isCaptureFinal($amount);

        $body = [
            'amount' => $this->amountConverter->toMinorUnits($amount),
            'isFinal' => $isFinal,
            'references' => [
                'merchantReference' => $this->merchantReference->getForCapture($order, $invoice),
            ],
            'delivery' => [
                'items' => $this->lineItemsBuilder->build($invoice),
            ],
        ];

        // Buy Now Pay Later needs a reason when the last of several partial captures closes the payment
        $isFinancing = $payment->getMethodInstance()->getConfigData('payone_method_type', $order->getStoreId())
            === Payone::METHOD_TYPE_FINANCING;
        if ($isFinancing && $isFinal && $amount < (float)$order->getBaseGrandTotal()) {
            $body['cancellationReason'] = self::CANCELLATION_REASON_FINAL_PARTIAL;
        }

        return ['body' => $body];
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
