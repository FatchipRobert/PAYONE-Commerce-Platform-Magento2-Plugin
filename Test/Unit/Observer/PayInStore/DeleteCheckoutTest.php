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

namespace Payone\CommercePlatform\Test\Unit\Observer\PayInStore;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Observer\PayInStore\DeleteCheckout;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeleteCheckoutTest extends TestCase
{
    use OrderMockTrait;

    /**
     * @var CommandInterface&MockObject
     */
    private MockObject $command;

    private function createObserver(): DeleteCheckout
    {
        $this->command = $this->createMock(CommandInterface::class);
        $commandPool = $this->createStub(CommandPoolInterface::class);
        $commandPool->method('get')->willReturnCallback(
            fn (string $code) => $code === 'delete_checkout' ? $this->command : null
        );
        return new DeleteCheckout($commandPool);
    }

    private static function createEvent(Payment $payment): Observer
    {
        return new Observer(['event' => new Event(['payment' => $payment])]);
    }

    public function testDeletesCheckoutOfPayInStoreOrder(): void
    {
        $observer = $this->createObserver();
        $order = $this->createOrder(['payone_cp_checkout_id' => 'CK1']);
        $payment = $this->createPayment($order, [], [], Payone::METHOD_PAY_IN_STORE);
        $this->command->expects($this->once())->method('execute')->with(['order' => $order]);

        $observer->execute(self::createEvent($payment));
    }

    public function testApiErrorIsPassedOn(): void
    {
        $observer = $this->createObserver();
        $payment = $this->createPayment(
            $this->createOrder(['payone_cp_checkout_id' => 'CK1']),
            [],
            [],
            Payone::METHOD_PAY_IN_STORE
        );
        $this->command->expects($this->once())->method('execute')
            ->willThrowException(new CommandException(__('not in the status OPEN')));

        $this->expectException(CommandException::class);
        $observer->execute(self::createEvent($payment));
    }

    /**
     * @return array
     */
    public static function noCallProvider(): array
    {
        return [
            'other payment method' => ['payone_cp_creditcard', 'CK1', false],
            'no checkout id' => [Payone::METHOD_PAY_IN_STORE, null, false],
            'checkout already deleted in store' => [Payone::METHOD_PAY_IN_STORE, 'CK1', true],
        ];
    }

    #[DataProvider('noCallProvider')]
    public function testNoCall(string $methodCode, ?string $checkoutId, bool $checkoutDeleted): void
    {
        $observer = $this->createObserver();
        $payment = $this->createPayment(
            $this->createOrder(['payone_cp_checkout_id' => $checkoutId]),
            [],
            [],
            $methodCode
        );
        $payment->setData(DeleteCheckout::FLAG_CHECKOUT_DELETED, $checkoutDeleted);
        $this->command->expects($this->never())->method('execute');

        $observer->execute(self::createEvent($payment));
    }
}
