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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Request;

use Magento\Payment\Model\MethodInterface;
use Payone\CommercePlatform\Gateway\Request\PaymentMethodSpecificBuilder;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

class PaymentMethodSpecificBuilderTest extends TestCase
{
    use OrderMockTrait;

    private function build(array $methodInput): array
    {
        $order = $this->createOrder();
        $method = $this->createMethodInstance([], 'payone_cp_paypal');
        $method->method('getPayoneMethodType')->willReturn('redirectPaymentMethod');
        $method->method('getPayoneProductId')->willReturn(840);
        $payment = $this->createPaymentWithMethod($order, $method);
        $method->method('getSpecificInput')->willReturnCallback(
            static fn ($inputPayment) => $inputPayment === $payment ? $methodInput : []
        );

        return (new PaymentMethodSpecificBuilder(new SubjectReader()))
            ->build(['payment' => $this->createPaymentDataObject($payment)]);
    }

    public function testMethodInputIsWrappedAndProductSpecificInputRenamed(): void
    {
        $result = $this->build(['requiresApproval' => true, 'productSpecificInput' => ['addressSelectionAtPayPal' => false]]);

        $this->assertSame(['body' => ['checkout' => ['orderRequest' => ['paymentMethodSpecificInput' => [
            'redirectPaymentMethodSpecificInput' => [
                'requiresApproval' => true,
                'paymentProductId' => 840,
                'paymentProduct840SpecificInput' => ['addressSelectionAtPayPal' => false],
            ],
        ]]]]], $result);
    }

    public function testProductIdOfTheMethodInputTakesPrecedence(): void
    {
        $input = $this->build(['paymentProductId' => 3, 'productSpecificInput' => ['x' => 1]])
            ['body']['checkout']['orderRequest']['paymentMethodSpecificInput']['redirectPaymentMethodSpecificInput'];

        $this->assertSame(['paymentProductId' => 3, 'paymentProduct3SpecificInput' => ['x' => 1]], $input);
    }

    public function testThrowsForOtherPaymentMethods(): void
    {
        $method = $this->createStub(MethodInterface::class);
        $method->method('getCode')->willReturn('checkmo');
        $payment = $this->createPaymentWithMethod($this->createOrder(), $method);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Payment method checkmo is no PAYONE payment method');
        (new PaymentMethodSpecificBuilder(new SubjectReader()))->build(['payment' => $this->createPaymentDataObject($payment)]);
    }
}
