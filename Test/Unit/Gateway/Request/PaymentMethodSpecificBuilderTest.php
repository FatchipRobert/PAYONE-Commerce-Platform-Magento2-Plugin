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

use Magento\Payment\Gateway\Request\BuilderInterface;
use Payone\CommercePlatform\Gateway\Request\PaymentMethodSpecificBuilder;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

class PaymentMethodSpecificBuilderTest extends TestCase
{
    use OrderMockTrait;

    private function build(array $methodInput, string $methodCode = 'payone_cp_paypal'): array
    {
        $order = $this->createOrder();
        $payment = $this->createPayment(
            $order,
            [],
            ['payone_method_type' => 'redirectPaymentMethod', 'payone_product_id' => '840'],
            $methodCode
        );
        $methodBuilder = $this->createStub(BuilderInterface::class);
        $methodBuilder->method('build')->willReturn($methodInput);

        $builder = new PaymentMethodSpecificBuilder(new SubjectReader(), [
            'payone_cp_paypal' => $methodBuilder,
            'payone_cp_broken' => 'not a builder',
        ]);
        return $builder->build(['payment' => $this->createPaymentDataObject($payment)]);
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

    public function testProductIdOfTheMethodBuilderTakesPrecedence(): void
    {
        $input = $this->build(['paymentProductId' => 3, 'productSpecificInput' => ['x' => 1]])
            ['body']['checkout']['orderRequest']['paymentMethodSpecificInput']['redirectPaymentMethodSpecificInput'];

        $this->assertSame(['paymentProductId' => 3, 'paymentProduct3SpecificInput' => ['x' => 1]], $input);
    }

    public function testThrowsWithoutBuilderForTheMethod(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No PaymentMethodSpecificBuilder configured for payone_cp_broken');
        $this->build([], 'payone_cp_broken');
    }
}
