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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Response;

use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Payone\CommercePlatform\Block\Info\PaymentInstructions;
use Payone\CommercePlatform\Gateway\Response\CheckoutIdsHandler;
use Payone\CommercePlatform\Gateway\Response\CheckoutInputHandler;
use Payone\CommercePlatform\Gateway\Response\PaymentIdsHandler;
use Payone\CommercePlatform\Gateway\Response\PaymentInstructionsHandler;
use Payone\CommercePlatform\Gateway\Response\PendingStateHandler;
use Payone\CommercePlatform\Gateway\Response\RedirectUrlHandler;
use Payone\CommercePlatform\Gateway\Response\TransactionIdHandler;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Model\Method\PayoneMethod;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

class ResponseHandlersTest extends TestCase
{
    use MockCreationTrait;
    use OrderMockTrait;

    private const ONE_STEP_RESPONSE = [
        'commerceCaseId' => 'CC1',
        'merchantReference' => 'cc000000100',
        'checkout' => [
            'checkoutId' => 'CK1',
            'paymentResponse' => [
                'paymentId' => 'PAY1',
                'paymentExecutionId' => 'PE1',
                'merchantAction' => [
                    'actionType' => 'REDIRECT',
                    'redirectData' => ['redirectURL' => 'https://payone.test/3ds'],
                ],
            ],
        ],
    ];

    private function createSubject(?Order &$order = null): array
    {
        $order = $this->createOrder();
        $payment = $this->createPayment($order);
        return ['payment' => $this->createPaymentDataObject($payment)];
    }

    public function testPaymentIdsHandlerStoresIdsAndAuthorizationTransaction(): void
    {
        $subject = $this->createSubject($order);
        $order->setCanSendNewEmailFlag(true);

        (new PaymentIdsHandler(new SubjectReader()))->handle($subject, ['body' => self::ONE_STEP_RESPONSE]);

        $this->assertFalse($order->getCanSendNewEmailFlag());
        $this->assertSame('CC1', $order->getData('payone_cp_commerce_case_id'));
        $this->assertSame('CK1', $order->getData('payone_cp_checkout_id'));
        $this->assertSame('PAY1', $order->getData('payone_cp_payment_id'));
        $this->assertSame('PE1', $order->getData('payone_cp_payment_execution_id'));
        $payment = $order->getPayment();
        $this->assertSame('PAY1', $payment->getTransactionId());
        $this->assertFalse($payment->getIsTransactionClosed());
    }

    public function testPaymentIdsHandlerWithoutPaymentId(): void
    {
        $subject = $this->createSubject($order);

        (new PaymentIdsHandler(new SubjectReader()))->handle($subject, ['body' => ['commerceCaseId' => 'CC1']]);

        $this->assertFalse($order->getCanSendNewEmailFlag());
        $this->assertNull($order->getData('payone_cp_commerce_case_id'));
        $this->assertNull($order->getPayment()->getTransactionId());
    }

    public function testCheckoutInputHandlerLetsThePaymentMethodReduceTheInput(): void
    {
        $method = $this->createMock(PayoneMethod::class);
        $payment = $this->createPaymentWithMethod($this->createOrder(), $method);
        $method->expects($this->once())->method('reduceCheckoutInput')->with($payment);

        (new CheckoutInputHandler(new SubjectReader()))->handle(
            ['payment' => $this->createPaymentDataObject($payment)],
            ['body' => self::ONE_STEP_RESPONSE]
        );
    }

    public function testCheckoutInputHandlerIgnoresOtherPaymentMethods(): void
    {
        $payment = $this->createPaymentWithMethod(
            $this->createOrder(),
            $this->createStub(MethodInterface::class),
            ['iban' => 'DE02120300000000202051']
        );

        (new CheckoutInputHandler(new SubjectReader()))->handle(
            ['payment' => $this->createPaymentDataObject($payment)],
            ['body' => self::ONE_STEP_RESPONSE]
        );

        $this->assertSame(['iban' => 'DE02120300000000202051'], $payment->getAdditionalInformation());
    }

    public function testCheckoutIdsHandlerStoresIdsWithoutTransaction(): void
    {
        $subject = $this->createSubject($order);
        $order->setCanSendNewEmailFlag(true);
        $response = ['commerceCaseId' => 'CC1', 'checkout' => ['checkoutId' => 'CK1']];

        (new CheckoutIdsHandler(new SubjectReader()))->handle($subject, ['body' => $response]);

        $this->assertSame('CC1', $order->getData('payone_cp_commerce_case_id'));
        $this->assertSame('CK1', $order->getData('payone_cp_checkout_id'));
        $this->assertTrue($order->getCanSendNewEmailFlag(), 'the order mail is sent right away');
        $this->assertNull($order->getPayment()->getTransactionId());
    }

    public function testPaymentInstructionsHandlerStoresBankDetails(): void
    {
        $subject = $this->createSubject($order);
        $response = ['body' => ['payment' => ['paymentOutput' => ['paymentInstructions' => [
            'payee' => ['name' => 'PAYONE GmbH', 'iban' => 'DE00123', 'bic' => ''],
            'referenceNumber' => 'REF1',
            'dueDate' => 20261203,
        ]]]]];

        (new PaymentInstructionsHandler(new SubjectReader()))->handle($subject, $response);

        $this->assertSame([
            PaymentInstructions::KEY_PAYEE_NAME => 'PAYONE GmbH',
            PaymentInstructions::KEY_PAYEE_IBAN => 'DE00123',
            PaymentInstructions::KEY_REFERENCE_NUMBER => 'REF1',
            PaymentInstructions::KEY_DUE_DATE => '20261203',
        ], $order->getPayment()->getAdditionalInformation());
    }

    public function testPaymentInstructionsHandlerIgnoresResponsesWithoutPayee(): void
    {
        $handler = new PaymentInstructionsHandler(new SubjectReader());
        $subject = $this->createSubject($order);

        $handler->handle($subject, ['body' => ['paymentInstructions' => ['referenceNumber' => 'REF1']]]);
        $handler->handle($subject, ['body' => ['paymentInstructions' => 'x']]);
        $handler->handle($subject, []);

        $this->assertSame([], $order->getPayment()->getAdditionalInformation());
    }

    public function testPendingStateHandlerSetsPendingPayment(): void
    {
        $stateObject = new DataObject();

        (new PendingStateHandler())->handle(['stateObject' => $stateObject], []);
        (new PendingStateHandler())->handle([], []);

        $this->assertSame(['state' => Order::STATE_PENDING_PAYMENT, 'status' => 'pending_payment'], $stateObject->getData());
    }

    public function testRedirectUrlHandlerStoresUrlInCheckoutSession(): void
    {
        $session = $this->createPartialMockWithReflection(Session::class, ['unsPayoneCpRedirectUrl', 'setPayoneCpRedirectUrl']);
        $session->expects($this->once())->method('unsPayoneCpRedirectUrl');
        $session->expects($this->once())->method('setPayoneCpRedirectUrl')->with('https://payone.test/3ds');

        (new RedirectUrlHandler(new SubjectReader(), $session))->handle([], ['body' => self::ONE_STEP_RESPONSE]);
    }

    public static function noRedirectProvider(): array
    {
        return [
            'payment without redirect' => [['paymentId' => 'PAY1']],
            'redirect without created payment' => [['redirectURL' => 'https://payone.test/3ds']],
            'no body' => [null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('noRedirectProvider')]
    public function testRedirectUrlHandlerOnlyClearsOldUrl(?array $body): void
    {
        $session = $this->createPartialMockWithReflection(Session::class, ['unsPayoneCpRedirectUrl', 'setPayoneCpRedirectUrl']);
        $session->expects($this->once())->method('unsPayoneCpRedirectUrl');
        $session->expects($this->never())->method('setPayoneCpRedirectUrl');

        (new RedirectUrlHandler(new SubjectReader(), $session))->handle([], $body === null ? [] : ['body' => $body]);
    }

    public function testTransactionIdHandler(): void
    {
        $handler = new TransactionIdHandler(new SubjectReader());

        $subject = $this->createSubject($order);
        $handler->handle($subject, ['body' => ['id' => 'CAPTURE1', 'payment' => ['id' => 'PAY1']]]);
        $this->assertSame('CAPTURE1', $order->getPayment()->getTransactionId());

        $subject = $this->createSubject($order);
        $handler->handle($subject, ['body' => ['payment' => ['id' => 'PAY1']]]);
        $this->assertSame('PAY1', $order->getPayment()->getTransactionId());

        $subject = $this->createSubject($order);
        $handler->handle($subject, ['body' => []]);
        $this->assertNull($order->getPayment()->getTransactionId());
    }
}
