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

use Magento\Framework\Url;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\Request\CommerceCaseBuilder;
use Payone\CommercePlatform\Gateway\Request\GetCheckoutDetailsBuilder;
use Payone\CommercePlatform\Gateway\Request\GetJwtBuilder;
use Payone\CommercePlatform\Gateway\Request\Mapper\CustomerMapper;
use Payone\CommercePlatform\Gateway\Request\OrderContextBuilder;
use Payone\CommercePlatform\Gateway\Request\PaymentReferencesBuilder;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

/**
 * Builders that only copy order data into the request
 */
class SimpleBuildersTest extends TestCase
{
    use OrderMockTrait;

    private const PAYONE_IDS = [
        'payone_cp_commerce_case_id' => 'CC1',
        'payone_cp_checkout_id' => 'CK1',
        'payone_cp_payment_id' => 'PAY1',
        'payone_cp_payment_execution_id' => 'PE1',
    ];

    public function testCommerceCaseBuilder(): void
    {
        $order = $this->createOrder(['increment_id' => '000000100']);
        $payment = $this->createPayment($order);
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getRefPrefix')->willReturn('x');
        $customerMapper = $this->createStub(CustomerMapper::class);
        $customerMapper->method('map')->willReturnCallback(fn ($mappedOrder) => $mappedOrder === $order ? ['customer'] : []);

        $builder = new CommerceCaseBuilder(new SubjectReader(), new MerchantReference($generalConfig), $customerMapper);

        $this->assertSame(
            ['body' => ['merchantReference' => 'ccx000000100', 'customer' => ['customer']]],
            $builder->build(['payment' => $this->createPaymentDataObject($payment)])
        );
    }

    public function testGetCheckoutDetailsBuilderReadsOrderFromSubject(): void
    {
        $order = $this->createOrder(array_merge(self::PAYONE_IDS, ['store_id' => '2', 'increment_id' => '000000100']));
        $this->createPayment($order, [], [], 'payone_cp_wero');

        $this->assertSame([
            'uri' => ['commerceCaseId' => 'CC1', 'checkoutId' => 'CK1'],
            'body' => [],
            'store_id' => 2,
            'order_increment_id' => '000000100',
            'payment_method' => 'payone_cp_wero',
        ], (new GetCheckoutDetailsBuilder(new SubjectReader()))->build(['order' => $order]));
    }

    public function testGetJwtBuilder(): void
    {
        $builder = new GetJwtBuilder(new SubjectReader());

        $this->assertSame(['uri' => [], 'body' => [], 'store_id' => 3], $builder->build(['store_id' => '3']));
        $this->assertSame(['uri' => [], 'body' => [], 'store_id' => null], $builder->build([]));
    }

    public function testOrderContextBuilder(): void
    {
        $order = $this->createOrder(['store_id' => '4', 'increment_id' => '000000100']);
        $payment = $this->createPayment($order, [], [], 'payone_cp_paypal');

        $this->assertSame(
            ['store_id' => 4, 'order_increment_id' => '000000100', 'payment_method' => 'payone_cp_paypal'],
            (new OrderContextBuilder(new SubjectReader()))->build(['payment' => $this->createPaymentDataObject($payment)])
        );
    }

    public function testPaymentReferencesBuilder(): void
    {
        $order = $this->createOrder(self::PAYONE_IDS);
        $payment = $this->createPayment($order);

        $this->assertSame(
            ['uri' => ['commerceCaseId' => 'CC1', 'checkoutId' => 'CK1', 'paymentExecutionId' => 'PE1']],
            (new PaymentReferencesBuilder(new SubjectReader()))->build(['payment' => $this->createPaymentDataObject($payment)])
        );
    }

    public function testReturnUrlContainsIncrementId(): void
    {
        $url = $this->createStub(Url::class);
        $url->method('getUrl')->willReturnCallback(static fn ($route) => 'https://shop.test/'.$route.'/');

        $this->assertSame(
            'https://shop.test/payone_cp/onepage/returned/?incrementId=000000100',
            (new ReturnUrl($url))->get($this->createOrder(['increment_id' => '000000100']))
        );
    }
}
