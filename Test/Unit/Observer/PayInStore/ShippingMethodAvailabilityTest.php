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

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Observer\PayInStore\ShippingMethodAvailability;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShippingMethodAvailabilityTest extends TestCase
{
    use OrderMockTrait;

    private function createQuote(string $shippingMethod, bool $isVirtual = false): Quote
    {
        $address = $this->createPartialStub(Address::class, []);
        $address->setData('shipping_method', $shippingMethod);
        $quote = $this->createPartialStub(Quote::class, ['isVirtual', 'getShippingAddress']);
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('getShippingAddress')->willReturn($address);
        return $quote;
    }

    private function isAvailable(string $methodCode, ?Quote $quote, bool $isAvailable = true): bool
    {
        $result = new DataObject(['is_available' => $isAvailable]);
        $event = new Event([
            'method_instance' => $this->createMethodInstance([], $methodCode),
            'quote' => $quote,
            'result' => $result,
        ]);

        (new ShippingMethodAvailability())->execute(new Observer(['event' => $event]));

        return (bool)$result->getData('is_available');
    }

    /**
     * @return array
     */
    public static function availabilityProvider(): array
    {
        return [
            'in-store pickup' => [Payone::METHOD_PAY_IN_STORE, 'instore_pickup', false, true, true],
            'other shipping method' => [Payone::METHOD_PAY_IN_STORE, 'flatrate_flatrate', false, true, false],
            'no shipping method yet' => [Payone::METHOD_PAY_IN_STORE, '', false, true, false],
            'virtual quote' => [Payone::METHOD_PAY_IN_STORE, 'instore_pickup', true, true, false],
            'already unavailable' => [Payone::METHOD_PAY_IN_STORE, 'instore_pickup', false, false, false],
            'other payment method' => ['payone_cp_creditcard', 'flatrate_flatrate', false, true, true],
        ];
    }

    #[DataProvider('availabilityProvider')]
    public function testAvailability(
        string $methodCode,
        string $shippingMethod,
        bool $isVirtual,
        bool $isAvailable,
        bool $expected
    ): void {
        $this->assertSame(
            $expected,
            $this->isAvailable($methodCode, $this->createQuote($shippingMethod, $isVirtual), $isAvailable)
        );
    }

    public function testNotAvailableWithoutQuote(): void
    {
        $this->assertFalse($this->isAvailable(Payone::METHOD_PAY_IN_STORE, null));
    }
}
