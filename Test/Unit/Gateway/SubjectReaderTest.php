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

namespace Payone\CommercePlatform\Test\Unit\Gateway;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Payone\CommercePlatform\Gateway\SubjectReader;
use PHPUnit\Framework\TestCase;

class SubjectReaderTest extends TestCase
{
    /**
     * @var SubjectReader
     */
    private SubjectReader $subjectReader;

    protected function setUp(): void
    {
        $this->subjectReader = new SubjectReader();
    }

    public function testReadPayment(): void
    {
        $paymentDO = $this->createStub(PaymentDataObjectInterface::class);
        $this->assertSame($paymentDO, $this->subjectReader->readPayment(['payment' => $paymentDO]));
    }

    public function testReadPaymentThrowsWithoutPayment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->subjectReader->readPayment([]);
    }

    public function testReadAmountReturnsFloat(): void
    {
        $this->assertSame(12.5, $this->subjectReader->readAmount(['amount' => '12.50']));
    }

    public function testReadResponse(): void
    {
        $this->assertSame(['status' => 200], $this->subjectReader->readResponse(['response' => ['status' => 200]]));
    }

    public function testReadOrder(): void
    {
        $order = $this->createStub(Order::class);
        $this->assertSame($order, $this->subjectReader->readOrder(['order' => $order]));
    }

    public function testReadOrderThrowsWithoutOrder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->subjectReader->readOrder(['order' => new \stdClass()]);
    }

    public function testReadStoreId(): void
    {
        $this->assertSame(3, $this->subjectReader->readStoreId(['store_id' => '3']));
        $this->assertNull($this->subjectReader->readStoreId([]));
    }

    public function testFindValueSearchesNestedArrays(): void
    {
        $data = [
            'commerceCaseId' => 'case',
            'checkout' => [
                'checkoutId' => 'checkout',
                'paymentResponse' => ['payment' => ['id' => 'pay', 'paymentId' => 'nested']],
            ],
        ];

        $this->assertSame('case', $this->subjectReader->findValue($data, 'commerceCaseId'));
        $this->assertSame('checkout', $this->subjectReader->findValue($data, 'checkoutId'));
        $this->assertSame('nested', $this->subjectReader->findValue($data, 'paymentId'));
        $this->assertSame(['id' => 'pay', 'paymentId' => 'nested'], $this->subjectReader->findValue($data, 'payment'));
        $this->assertNull($this->subjectReader->findValue($data, 'missing'));
    }

    public function testFindValueReturnsFirstMatchInDocumentOrder(): void
    {
        $data = ['a' => ['id' => 'first'], 'b' => ['id' => 'second']];
        $this->assertSame('first', $this->subjectReader->findValue($data, 'id'));
    }

    public function testFindValueOnNonArray(): void
    {
        $this->assertNull($this->subjectReader->findValue(null, 'key'));
        $this->assertNull($this->subjectReader->findValue('string', 'key'));
    }

    public function testFindValueDoesNotMatchIntegerKeysLoosely(): void
    {
        $this->assertNull($this->subjectReader->findValue([0 => 'zero'], '0'));
    }
}
