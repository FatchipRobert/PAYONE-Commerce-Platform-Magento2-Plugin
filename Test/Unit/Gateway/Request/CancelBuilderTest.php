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

use Payone\CommercePlatform\Gateway\AmountConverter;
use Payone\CommercePlatform\Gateway\Request\CancelBuilder;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

class CancelBuilderTest extends TestCase
{
    use OrderMockTrait;

    private function build(bool $requiresReason): array
    {
        $order = $this->createOrder(['base_grand_total' => '100.0000', 'base_total_paid' => '30.0000']);
        $method = $this->createMethodInstance();
        $method->method('requiresCancellationReason')->willReturn($requiresReason);
        $payment = $this->createPaymentWithMethod($order, $method);

        return (new CancelBuilder(new SubjectReader(), new AmountConverter()))
            ->build(['payment' => $this->createPaymentDataObject($payment)]);
    }

    public function testCancelsTheAmountNotCapturedYet(): void
    {
        $this->assertSame(['body' => ['amount' => 7000]], $this->build(false));
    }

    public function testBnplNeedsCancellationReason(): void
    {
        $this->assertSame(
            ['body' => ['amount' => 7000, 'cancellationReason' => CancelBuilder::CANCELLATION_REASON]],
            $this->build(true)
        );
    }
}
