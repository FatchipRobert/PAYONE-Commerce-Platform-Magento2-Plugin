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
use Payone\CommercePlatform\Model\Method\Wero;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use Payone\CommercePlatform\Test\Unit\Helper\PaymentMethodTrait;
use PHPUnit\Framework\TestCase;

class WeroTest extends TestCase
{
    use OrderMockTrait;
    use PaymentMethodTrait;

    private const RETURN_URL = 'https://shop.test/payone_cp/onepage/returned/?incrementId=000000100';

    private function createMethod(): Wero
    {
        $returnUrl = $this->createStub(ReturnUrl::class);
        $returnUrl->method('get')->willReturn(self::RETURN_URL);

        return new Wero(...$this->methodArguments(['automatic_capture' => '0']), returnUrl: $returnUrl);
    }

    public function testIdentity(): void
    {
        $method = $this->createMethod();

        $this->assertSame(Payone::METHOD_WERO, $method->getCode());
        $this->assertSame('redirectPaymentMethod', $method->getPayoneMethodType());
        $this->assertSame(900, $method->getPayoneProductId());
    }

    public function testOnlyEur(): void
    {
        $method = $this->createMethod();

        $this->assertTrue($method->canUseForCurrency('EUR'));
        $this->assertFalse($method->canUseForCurrency('CHF'));
    }

    public function testAlwaysDirectSale(): void
    {
        $method = $this->createMethod();

        $this->assertTrue($method->isAutomaticCapture(), 'regardless of the config value');
        $this->assertSame([
            'requiresApproval' => false,
            'tokenize' => false,
            'redirectionData' => ['returnUrl' => self::RETURN_URL],
        ], $method->getSpecificInput($this->createPayment($this->createOrder())));
    }
}
