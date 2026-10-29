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
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Gateway\AmountConverter;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Model\Payone;

/**
 * Payload of the refund of the current creditmemo
 */
class RefundBuilder implements BuilderInterface
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
        $order = $payment->getOrder();

        $references = [
            'merchantReference' => $this->merchantReference->get($order, MerchantReference::PREFIX_REFUND),
        ];
        $captureReference = $this->getCaptureReference($payment);
        if ($captureReference !== null) {
            $references['captureReference'] = $captureReference;
        }

        $creditmemo = $payment->getCreditmemo();
        return [
            'body' => [
                'amountOfMoney' => [
                    'amount' => $this->amountConverter->toMinorUnits($this->subjectReader->readAmount($buildSubject)),
                    'currencyCode' => (string)$order->getOrderCurrencyCode(),
                ],
                'references' => $references,
                'return' => [
                    'items' => $creditmemo ? $this->lineItemsBuilder->build($creditmemo) : [],
                ],
            ],
        ];
    }

    /**
     * Returns the merchantReference of the capture this refund belongs to
     *
     * Invoices created by the payment.captured webhook (automatic capture, SALE in the payment execution)
     * carry "<payment id>-sale" (older invoices: the payment id) as transaction id.
     * No separate capture event exists for them, so no reference is sent.
     *
     * @param Payment $payment
     * @return string|null
     */
    private function getCaptureReference(Payment $payment): ?string
    {
        $order = $payment->getOrder();
        $creditmemo = $payment->getCreditmemo();
        $invoice = $creditmemo ? $creditmemo->getInvoice() : null;
        if (!$invoice) {
            return $this->merchantReference->get($order, MerchantReference::PREFIX_CAPTURE);
        }

        $paymentId = (string)$order->getData('payone_cp_payment_id');
        if (in_array($invoice->getTransactionId(), [$paymentId, $paymentId.Payone::TRANSACTION_SUFFIX_SALE], true)) {
            return null;
        }
        return $this->merchantReference->getForCapture($order, $invoice);
    }
}
