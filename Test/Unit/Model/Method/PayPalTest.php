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

use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Model\Method\PayPal;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use Payone\CommercePlatform\Test\Unit\Helper\PaymentMethodTrait;
use PHPUnit\Framework\TestCase;

class PayPalTest extends TestCase
{
    use OrderMockTrait;
    use PaymentMethodTrait;

    private const RETURN_URL = 'https://shop.test/payone_cp/onepage/returned/?incrementId=000000100';

    private function createMethod(bool $automaticCapture): PayPal
    {
        $returnUrl = $this->createStub(ReturnUrl::class);
        $returnUrl->method('get')->willReturn(self::RETURN_URL);

        return new PayPal(
            ...$this->methodArguments(['automatic_capture' => $automaticCapture ? '1' : '0']),
            returnUrl: $returnUrl
        );
    }

    public function testIdentity(): void
    {
        $method = $this->createMethod(false);

        $this->assertSame(Payone::METHOD_PAYPAL, $method->getCode());
        $this->assertSame('redirectPaymentMethod', $method->getPayoneMethodType());
        $this->assertSame(840, $method->getPayoneProductId());
    }

    public function testSpecificInput(): void
    {
        $payment = $this->createPayment($this->createOrder());

        $this->assertSame([
            'requiresApproval' => true,
            'tokenize' => false,
            'productSpecificInput' => ['addressSelectionAtPayPal' => false],
            'redirectionData' => ['returnUrl' => self::RETURN_URL],
        ], $this->createMethod(false)->getSpecificInput($payment));

        $this->assertFalse($this->createMethod(true)->getSpecificInput($payment)['requiresApproval']);
    }
}
