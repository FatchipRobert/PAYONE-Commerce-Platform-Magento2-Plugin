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

namespace Payone\CommercePlatform\Test\Unit\Model\Webhook;

use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\OrderLocator;
use Payone\CommercePlatform\Model\Webhook\ShopFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShopFilterTest extends TestCase
{
    /**
     * @param array $refPrefixes reference prefix per store id
     * @param OrderLocator $orderLocator
     * @return ShopFilter
     */
    private function createFilter(array $refPrefixes, OrderLocator $orderLocator): ShopFilter
    {
        $stores = [];
        foreach (array_keys($refPrefixes) as $storeId) {
            $store = $this->createStub(StoreInterface::class);
            $store->method('getId')->willReturn((string)$storeId);
            $stores[] = $store;
        }
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getRefPrefix')->willReturnCallback(fn ($storeId) => $refPrefixes[$storeId]);

        return new ShopFilter($storeManager, $generalConfig, $orderLocator);
    }

    private function message(string $merchantReference, string $paymentId = 'PAY1'): array
    {
        return [
            'type' => 'payment.captured',
            'payment' => [
                'id' => $paymentId,
                'paymentOutput' => ['references' => ['merchantReference' => $merchantReference]],
            ],
        ];
    }

    public static function ownMessageProvider(): array
    {
        return [
            'no prefix configured' => [[1 => ''], 'od000000123'],
            'prefix of the store' => [[1 => 'ab-'], 'odab-000000123'],
            'prefix of another store view' => [[1 => 'ab-', 2 => 'cd-'], 'odcd-2000000123'],
            'one store view without prefix' => [[1 => 'ab-', 2 => ''], 'odxy-000000123'],
        ];
    }

    #[DataProvider('ownMessageProvider')]
    public function testMessageOfThisShop(array $refPrefixes, string $merchantReference): void
    {
        $orderLocator = $this->createMock(OrderLocator::class);
        $orderLocator->expects($this->never())->method('getByPayonePaymentId');

        $this->assertTrue($this->createFilter($refPrefixes, $orderLocator)->isForThisShop($this->message($merchantReference)));
    }

    public function testMessageWithoutMerchantReferenceIsKept(): void
    {
        $orderLocator = $this->createMock(OrderLocator::class);
        $orderLocator->expects($this->never())->method('getByPayonePaymentId');

        $filter = $this->createFilter([1 => 'ab-'], $orderLocator);

        $this->assertTrue($filter->isForThisShop(['type' => 'payment.captured', 'payment' => ['id' => 'PAY1']]));
    }

    public function testMessageOfAnotherShop(): void
    {
        $orderLocator = $this->createMock(OrderLocator::class);
        $orderLocator->expects($this->once())->method('getByPayonePaymentId')->with('PAY1')->willReturn(null);

        $this->assertFalse($this->createFilter([1 => 'ab-'], $orderLocator)->isForThisShop($this->message('odxy-000000123')));
    }

    public function testOrderPlacedBeforeThePrefixWasChanged(): void
    {
        $orderLocator = $this->createMock(OrderLocator::class);
        $orderLocator->expects($this->once())->method('getByPayonePaymentId')->with('PAY1')
            ->willReturn($this->createStub(Order::class));

        $this->assertTrue($this->createFilter([1 => 'ab-'], $orderLocator)->isForThisShop($this->message('od000000123')));
    }

    public function testMessageOfAnotherShopWithoutPaymentId(): void
    {
        $orderLocator = $this->createMock(OrderLocator::class);
        $orderLocator->expects($this->never())->method('getByPayonePaymentId');

        $filter = $this->createFilter([1 => 'ab-'], $orderLocator);

        $this->assertFalse($filter->isForThisShop($this->message('odxy-000000123', '')));
    }
}
