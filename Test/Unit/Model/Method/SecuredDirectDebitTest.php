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
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\Method\SecuredDirectDebit;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use Payone\CommercePlatform\Test\Unit\Helper\PaymentMethodTrait;
use PHPUnit\Framework\TestCase;

/**
 * Specifics of Secured Direct Debit, the shared Buy Now Pay Later behaviour (and B2B) is tested in BuyNowPayLaterTest
 */
class SecuredDirectDebitTest extends TestCase
{
    use OrderMockTrait;
    use PaymentMethodTrait;

    public function testIdentityAndBankAccount(): void
    {
        $method = new SecuredDirectDebit(
            ...$this->methodArguments(['automatic_capture' => '0']),
            generalConfig: $this->createStub(GeneralConfig::class),
            checkoutSession: $this->createStub(Session::class),
            identityGenerator: $this->createStub(IdentityGeneratorInterface::class),
            escaper: $this->createStub(Escaper::class)
        );
        $payment = $this->createPayment(
            $this->createOrder(),
            ['iban' => 'DE02120300000000202051', 'accountholder' => str_repeat('a', 55)]
        );

        $this->assertSame(Payone::METHOD_SECURED_DIRECT_DEBIT, $method->getCode());
        $this->assertSame(3392, $method->getPayoneProductId());
        $this->assertSame([
            'requiresApproval' => true,
            'productSpecificInput' => ['bankAccountInformation' => [
                'iban' => 'DE02120300000000202051',
                'accountHolder' => str_repeat('a', 50),
            ]],
        ], $method->getSpecificInput($payment));
    }
}
