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

namespace Payone\CommercePlatform\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Model\Method\PayoneMethod;
use Payone\CommercePlatform\Observer\OrderPaymentCancel;
use Payone\CommercePlatform\Observer\OrderPaymentCapture;
use Payone\CommercePlatform\Observer\QuoteSubmitSuccess;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

/**
 * OrderPaymentCancel, OrderPaymentCapture and QuoteSubmitSuccess
 */
class ObserversTest extends TestCase
{
    use OrderMockTrait;

    public function testOrderPaymentCancelHandsTheCancellationToThePayoneMethod(): void
    {
        $method = $this->createMock(PayoneMethod::class);
        $payment = $this->createPaymentWithMethod($this->createOrder(), $method);
        $method->expects($this->once())->method('onCancel')->with($payment);

        (new OrderPaymentCancel())->execute(new Observer(['event' => new Event(['payment' => $payment])]));
    }

    public function testOrderPaymentCancelIgnoresOtherMethods(): void
    {
        $method = $this->createMock(MethodInterface::class);
        $method->expects($this->never())->method($this->anything());
        $payment = $this->createPartialStub(Payment::class, ['getMethodInstance']);
        $payment->method('getMethodInstance')->willReturn($method);

        (new OrderPaymentCancel())->execute(new Observer(['event' => new Event(['payment' => $payment])]));
    }

    public function testOrderPaymentCaptureHandsInvoiceToPayonePayments(): void
    {
        $invoice = $this->createPartialStub(Invoice::class, []);
        $payment = $this->createPayment($this->createOrder(), [], [], 'payone_cp_paypal');

        (new OrderPaymentCapture())->execute(new Observer(['payment' => $payment, 'invoice' => $invoice]));

        $this->assertSame($invoice, $payment->getData(OrderPaymentCapture::PAYMENT_KEY_CAPTURE_INVOICE));
    }

    public function testOrderPaymentCaptureIgnoresOtherPayments(): void
    {
        $invoice = $this->createPartialStub(Invoice::class, []);
        $payment = $this->createPayment($this->createOrder(), [], [], 'checkmo');
        $observer = new OrderPaymentCapture();

        $observer->execute(new Observer(['payment' => $payment, 'invoice' => $invoice]));
        $observer->execute(new Observer(['payment' => $payment]));
        $observer->execute(new Observer(['invoice' => $invoice]));

        $this->assertNull($payment->getData(OrderPaymentCapture::PAYMENT_KEY_CAPTURE_INVOICE));
    }

    public function testQuoteSubmitSuccessLetsThePaymentMethodReduceTheQuotePayment(): void
    {
        $method = $this->createMock(PayoneMethod::class);
        $quote = $this->createQuoteWithMethod($method);
        $method->expects($this->once())->method('reduceCheckoutInput')->with($quote->getPayment());

        (new QuoteSubmitSuccess())->execute(new Observer(['event' => new Event(['quote' => $quote])]));
    }

    public function testQuoteSubmitSuccessIgnoresOtherMethods(): void
    {
        $method = $this->createMock(MethodInterface::class);
        $method->expects($this->never())->method($this->anything());

        (new QuoteSubmitSuccess())->execute(
            new Observer(['event' => new Event(['quote' => $this->createQuoteWithMethod($method)])])
        );
    }

    /**
     * @param MethodInterface $method
     * @return Quote
     */
    private function createQuoteWithMethod(MethodInterface $method): Quote
    {
        $payment = $this->createPartialStub(QuotePayment::class, ['getMethodInstance']);
        $payment->method('getMethodInstance')->willReturn($method);
        $quote = $this->createPartialStub(Quote::class, ['getPayment']);
        $quote->method('getPayment')->willReturn($payment);
        return $quote;
    }
}
