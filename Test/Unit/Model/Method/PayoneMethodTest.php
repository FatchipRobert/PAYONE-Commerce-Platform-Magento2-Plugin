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

namespace Payone\CommercePlatform\Test\Unit\Model\Method;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Model\Method\PayoneMethod;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use Payone\CommercePlatform\Test\Unit\Helper\PaymentMethodTrait;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour of the base class, with a minimal method class
 */
class PayoneMethodTest extends TestCase
{
    use OrderMockTrait;
    use PaymentMethodTrait;

    private function createMethod(array $configData = [], ?ManagerInterface $eventManager = null): PayoneMethod
    {
        return new class(...$this->methodArguments($configData, $eventManager)) extends PayoneMethod {
            protected const CODE = 'payone_cp_test';
            protected const PAYONE_METHOD_TYPE = 'redirectPaymentMethod';
            protected const PAYONE_PRODUCT_ID = 840;
            protected const ADDITIONAL_DATA_KEYS = ['iban', 'accountholder', 'bic'];
            protected const REQUEST_ONLY_DATA_KEYS = ['payment_token'];
            protected const MASKED_DATA_KEYS = ['iban', 'pin', 'missing'];
        };
    }

    public function testIdentityAndDefaults(): void
    {
        $method = $this->createMethod();

        $this->assertSame('payone_cp_test', $method->getCode());
        $this->assertSame('redirectPaymentMethod', $method->getPayoneMethodType());
        $this->assertSame(840, $method->getPayoneProductId());
        $this->assertSame([], $method->getFrontendConfig());
        $this->assertFalse($method->requiresCancellationReason());
        $this->assertFalse($method->requiresCustomerDevice());
        $this->assertSame('', $method->getDeviceToken());
        $this->assertTrue($method->canUseForCurrency('USD'), 'no currency restriction');
    }

    public function testAssignDataCopiesConfiguredNonEmptyKeysAndDispatchesTheEvents(): void
    {
        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->expects($this->exactly(2))->method('dispatch');
        $method = $this->createMethod([], $eventManager);
        $payment = $this->createPartialStub(QuotePayment::class, []);
        $payment->setAdditionalInformation('iban', 'OLD');
        $method->setInfoInstance($payment);

        $result = $method->assignData(new DataObject([PaymentInterface::KEY_ADDITIONAL_DATA => [
            'iban' => 'DE02120300000000202051',
            'accountholder' => '',
            'bic' => 'BYLADEM1001',
            'other' => 'ignored',
        ]]));

        $this->assertSame($method, $result);
        $this->assertSame(
            ['iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001'],
            $payment->getAdditionalInformation()
        );
    }

    public function testAssignDataWithoutAdditionalData(): void
    {
        $method = $this->createMethod();
        $payment = $this->createPartialStub(QuotePayment::class, []);
        $method->setInfoInstance($payment);

        $method->assignData(new DataObject());

        $this->assertSame([], $payment->getAdditionalInformation());
    }

    public function testReduceCheckoutInputRemovesRequestOnlyDataAndMasksTheRest(): void
    {
        $payment = $this->createPayment($this->createOrder(), [
            'payment_token' => '{"data":"x"}',
            'iban' => 'DE02120300000000202051',
            'pin' => '1234',
            'bic' => 'BYLADEM1001',
        ]);

        $this->createMethod()->reduceCheckoutInput($payment);

        $this->assertSame(
            ['iban' => '******************2051', 'pin' => '1234', 'bic' => 'BYLADEM1001'],
            $payment->getAdditionalInformation()
        );
    }

    public function testAutomaticCaptureFromConfig(): void
    {
        $this->assertTrue($this->createMethod(['automatic_capture' => '1'])->isAutomaticCapture(1));
        $this->assertFalse($this->createMethod(['automatic_capture' => '0'])->isAutomaticCapture());
    }

    public function testNoSpecificInputByDefault(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Payment method payone_cp_test has no payment method specific input');
        $this->createMethod()->getSpecificInput($this->createPayment($this->createOrder()));
    }

    public function testCancelDoesNothingByDefault(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->expects($this->never())->method($this->anything());

        $this->createMethod()->onCancel($payment);
    }
}
