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

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Payone\CommercePlatform\Model\Method\PayInStore;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use Payone\CommercePlatform\Test\Unit\Helper\PaymentMethodTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PayInStoreTest extends TestCase
{
    use OrderMockTrait;
    use PaymentMethodTrait;

    /**
     * @var CommandInterface&MockObject
     */
    private MockObject $deleteCommand;

    private function createMethod(bool $active = true): PayInStore
    {
        $this->deleteCommand = $this->createMock(CommandInterface::class);
        $serviceCommandPool = $this->createStub(CommandPoolInterface::class);
        $serviceCommandPool->method('get')->willReturnCallback(
            fn (string $code) => $code === 'delete_checkout' ? $this->deleteCommand : null
        );

        return new PayInStore(
            ...$this->methodArguments(['active' => $active ? '1' : '0']),
            serviceCommandPool: $serviceCommandPool
        );
    }

    private function createQuote(string $shippingMethod, bool $isVirtual = false): Quote
    {
        $address = $this->createPartialStub(Address::class, []);
        $address->setData('shipping_method', $shippingMethod);
        $quote = $this->createPartialStub(Quote::class, ['getStoreId', 'isVirtual', 'getShippingAddress']);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('getShippingAddress')->willReturn($address);
        return $quote;
    }

    public function testIdentity(): void
    {
        $this->assertSame(Payone::METHOD_PAY_IN_STORE, $this->createMethod()->getCode());
        $this->deleteCommand->expects($this->never())->method('execute');
    }

    /**
     * @return array
     */
    public static function availabilityProvider(): array
    {
        return [
            'in-store pickup' => ['instore_pickup', false, true, true],
            'other shipping method' => ['flatrate_flatrate', false, true, false],
            'no shipping method yet' => ['', false, true, false],
            'virtual quote' => ['instore_pickup', true, true, false],
            'inactive' => ['instore_pickup', false, false, false],
        ];
    }

    #[DataProvider('availabilityProvider')]
    public function testOnlyAvailableWithInStorePickup(
        string $shippingMethod,
        bool $isVirtual,
        bool $active,
        bool $expected
    ): void {
        $method = $this->createMethod($active);
        $this->deleteCommand->expects($this->never())->method('execute');

        $this->assertSame($expected, $method->isAvailable($this->createQuote($shippingMethod, $isVirtual)));
    }

    public function testNotAvailableWithoutQuote(): void
    {
        $method = $this->createMethod();
        $this->deleteCommand->expects($this->never())->method('execute');

        $this->assertFalse($method->isAvailable());
        $this->assertFalse($method->isAvailable($this->createStub(CartInterface::class)), 'no quote model');
    }

    public function testCancelDeletesTheCheckout(): void
    {
        $method = $this->createMethod();
        $order = $this->createOrder(['payone_cp_checkout_id' => 'CK1']);
        $payment = $this->createPayment($order, [], [], Payone::METHOD_PAY_IN_STORE);
        $this->deleteCommand->expects($this->once())->method('execute')->with(['order' => $order]);

        $method->onCancel($payment);
    }

    public function testApiErrorStopsTheCancellation(): void
    {
        $method = $this->createMethod();
        $payment = $this->createPayment($this->createOrder(['payone_cp_checkout_id' => 'CK1']));
        $this->deleteCommand->expects($this->once())->method('execute')
            ->willThrowException(new CommandException(__('not in the status OPEN')));

        $this->expectException(CommandException::class);
        $method->onCancel($payment);
    }

    /**
     * @return array
     */
    public static function noDeletionProvider(): array
    {
        return [
            'no checkout id' => [null, false],
            'checkout already deleted in store' => ['CK1', true],
        ];
    }

    #[DataProvider('noDeletionProvider')]
    public function testCancelWithoutDeletion(?string $checkoutId, bool $checkoutDeleted): void
    {
        $method = $this->createMethod();
        $payment = $this->createPayment($this->createOrder(['payone_cp_checkout_id' => $checkoutId]));
        $payment->setData(PayInStore::FLAG_CHECKOUT_DELETED, $checkoutDeleted);
        $this->deleteCommand->expects($this->never())->method('execute');

        $method->onCancel($payment);
    }
}
