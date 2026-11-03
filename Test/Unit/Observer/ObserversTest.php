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

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order\Invoice;
use Payone\CommercePlatform\Observer\DataAssignObserver;
use Payone\CommercePlatform\Observer\OrderPaymentCapture;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

/**
 * DataAssignObserver and OrderPaymentCapture
 */
class ObserversTest extends TestCase
{
    use OrderMockTrait;

    private function createAssignObserver(?array $additionalData, InfoInterface $payment): Observer
    {
        $data = new DataObject([PaymentInterface::KEY_ADDITIONAL_DATA => $additionalData]);
        return new Observer(['event' => new Event([
            AbstractDataAssignObserver::DATA_CODE => $data,
            AbstractDataAssignObserver::MODEL_CODE => $payment,
        ])]);
    }

    public function testDataAssignObserverCopiesConfiguredNonEmptyKeys(): void
    {
        $payment = $this->createPartialStub(QuotePayment::class, []);
        $payment->setAdditionalInformation('iban', 'OLD');
        $observer = $this->createAssignObserver(
            ['iban' => 'DE02120300000000202051', 'accountholder' => '', 'bic' => 'BYLADEM1001', 'other' => 'ignored'],
            $payment
        );

        (new DataAssignObserver(['iban', 'accountholder', 'bic']))->execute($observer);

        $this->assertSame(
            ['iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001'],
            $payment->getAdditionalInformation()
        );
    }

    public function testDataAssignObserverWithoutAdditionalData(): void
    {
        $payment = $this->createPartialStub(QuotePayment::class, []);

        (new DataAssignObserver(['iban']))->execute($this->createAssignObserver(null, $payment));

        $this->assertNull($payment->getAdditionalInformation('iban'));
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
}
