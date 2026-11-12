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

namespace Payone\CommercePlatform\Test\Unit\Helper;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Model\Method\PayoneMethod;
use PHPUnit\Framework\MockObject\Stub;

/**
 * Order, payment and payment method doubles for the gateway tests
 *
 * Order, Payment and Address are partial stubs without constructor: the data getters/setters of
 * DataObject work on real data, only the methods that need other services are stubbed.
 */
trait OrderMockTrait
{
    /**
     * Partial test double without expectations (PHPUnit has createPartialMock only for mock objects)
     *
     * @param string $className
     * @param string[] $methods
     * @return Stub
     */
    protected function createPartialStub(string $className, array $methods): Stub
    {
        return static::getStubBuilder($className)
            ->disableOriginalConstructor()
            ->onlyMethods(array_values(array_unique($methods)))
            ->getStub();
    }

    /**
     * @param array $data
     * @param string[] $methods additional methods to stub
     * @return Order&Stub
     */
    protected function createOrder(array $data = [], array $methods = []): Order
    {
        $order = $this->createPartialStub(Order::class, array_merge([
            'getPayment',
            'getStore',
            'getBillingAddress',
            'getShippingAddress',
            'getInvoiceCollection',
            'getAllItems',
        ], $methods));
        $order->setData(array_merge(['store_id' => 1, 'increment_id' => '000000100'], $data));
        return $order;
    }

    /**
     * Payment of the order; getPayment() of an order created by createOrder() returns it
     *
     * @param Order $order
     * @param array $additionalInformation
     * @param array $configData config values of the payment method (automatic_capture, ...)
     * @param string $methodCode
     * @param string[] $methods additional methods to stub
     * @return Payment&Stub
     */
    protected function createPayment(
        Order $order,
        array $additionalInformation = [],
        array $configData = [],
        string $methodCode = 'payone_cp_creditcard',
        array $methods = []
    ): Payment {
        return $this->createPaymentWithMethod(
            $order,
            $this->createMethodInstance($configData, $methodCode),
            $additionalInformation,
            $methods
        );
    }

    /**
     * Payment of the order with the given payment method instance, e.g. a stub with requiresCancellationReason()
     *
     * @param Order $order
     * @param MethodInterface $method
     * @param array $additionalInformation
     * @param string[] $methods additional methods to stub
     * @return Payment&Stub
     */
    protected function createPaymentWithMethod(
        Order $order,
        MethodInterface $method,
        array $additionalInformation = [],
        array $methods = []
    ): Payment {
        $payment = $this->createPartialStub(Payment::class, array_merge(['getOrder', 'getMethodInstance'], $methods));
        $payment->setData('method', $method->getCode());
        $payment->setData('additional_information', $additionalInformation);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethodInstance')->willReturn($method);
        if ($order instanceof Stub) {
            $order->method('getPayment')->willReturn($payment);
        }
        return $payment;
    }

    /**
     * PAYONE payment method stub: config values via getConfigData(), isAutomaticCapture() from automatic_capture
     *
     * @param array $configData
     * @param string $methodCode
     * @return PayoneMethod&Stub
     */
    protected function createMethodInstance(array $configData = [], string $methodCode = 'payone_cp_creditcard'): PayoneMethod
    {
        $method = $this->createStub(PayoneMethod::class);
        $method->method('getCode')->willReturn($methodCode);
        $method->method('getConfigData')->willReturnCallback(
            static fn (string $field) => $configData[$field] ?? null
        );
        $method->method('isAutomaticCapture')->willReturn((bool)($configData['automatic_capture'] ?? false));
        return $method;
    }

    /**
     * @param Payment $payment
     * @return PaymentDataObjectInterface&Stub
     */
    protected function createPaymentDataObject(Payment $payment): PaymentDataObjectInterface
    {
        $paymentDO = $this->createStub(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);
        return $paymentDO;
    }

    /**
     * @param array $data
     * @param string[] $methods additional methods to stub
     * @return Address&Stub
     */
    protected function createAddress(array $data = [], array $methods = []): Address
    {
        $address = $this->createPartialStub(Address::class, array_merge(['getRegionCode'], $methods));
        $address->setData($data);
        $address->method('getRegionCode')->willReturn($data['region_code'] ?? null);
        return $address;
    }
}
