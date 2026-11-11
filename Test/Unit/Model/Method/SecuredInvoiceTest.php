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

use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Framework\Escaper;
use Payone\CommercePlatform\Block\Info\PaymentInstructions;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\Method\SecuredInvoice;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Test\Unit\Helper\PaymentMethodTrait;
use PHPUnit\Framework\TestCase;

/**
 * Specifics of Secured Invoice, the shared Buy Now Pay Later behaviour is tested in BuyNowPayLaterTest
 */
class SecuredInvoiceTest extends TestCase
{
    use PaymentMethodTrait;

    public function testIdentityAndInfoBlock(): void
    {
        $method = new SecuredInvoice(
            ...$this->methodArguments(),
            generalConfig: $this->createStub(GeneralConfig::class),
            checkoutSession: $this->createStub(Session::class),
            identityGenerator: $this->createStub(IdentityGeneratorInterface::class),
            escaper: $this->createStub(Escaper::class)
        );

        $this->assertSame(Payone::METHOD_SECURED_INVOICE, $method->getCode());
        $this->assertSame(3390, $method->getPayoneProductId());
        $this->assertSame(PaymentInstructions::class, $method->getInfoBlockType());
    }
}
