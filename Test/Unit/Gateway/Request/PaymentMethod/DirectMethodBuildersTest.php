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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Request\PaymentMethod;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\Request\PaymentMethod\DirectDebitBuilder;
use Payone\CommercePlatform\Gateway\Request\PaymentMethod\SecuredDirectDebitBuilder;
use Payone\CommercePlatform\Gateway\Request\PaymentMethod\SecuredInvoiceBuilder;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

/**
 * SEPA direct debit and Buy Now Pay Later builders
 */
class DirectMethodBuildersTest extends TestCase
{
    use OrderMockTrait;

    private function createDirectDebitBuilder(string $refPrefix, bool $requestBic): DirectDebitBuilder
    {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getRefPrefix')->willReturn($refPrefix);
        $generalConfig->method('getPaymentValue')->willReturnCallback(
            static fn ($code, $field) => $code === 'payone_cp_directdebit' && $field === 'request_bic' && $requestBic ? '1' : null
        );
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('date')->willReturn(new \DateTime('2026-11-03 23:30:00'));

        return new DirectDebitBuilder(new SubjectReader(), $generalConfig, new MerchantReference($generalConfig), $timezone);
    }

    private function buildDirectDebit(string $refPrefix, bool $requestBic, string $accountHolder = 'Max Mustermann'): array
    {
        $order = $this->createOrder(['increment_id' => '000000100']);
        $payment = $this->createPayment(
            $order,
            ['iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'accountholder' => $accountHolder],
            [],
            'payone_cp_directdebit'
        );
        return $this->createDirectDebitBuilder($refPrefix, $requestBic)
            ->build(['payment' => $this->createPaymentDataObject($payment)]);
    }

    public function testDirectDebitSendsUniqueMandate(): void
    {
        $this->assertSame(['productSpecificInput' => ['mandate' => [
            'recurrenceType' => 'UNIQUE',
            'bankAccountIban' => ['iban' => 'DE02120300000000202051', 'accountHolder' => 'Max Mustermann'],
            'dateOfSignature' => '20261103',
            'creditorId' => 'DE98ZZZ09999999999',
            'uniqueMandateReference' => 'mddev000000100',
        ]]], $this->buildDirectDebit('dev', false));
    }

    public function testDirectDebitWithBic(): void
    {
        $bankAccount = $this->buildDirectDebit('', true)['productSpecificInput']['mandate']['bankAccountIban'];

        $this->assertSame('BYLADEM1001', $bankAccount['bic']);
    }

    public function testMandateReferenceIsCleanedAndTruncatedTo35Characters(): void
    {
        $reference = $this->buildDirectDebit('shop_ä#'.str_repeat('x', 40), false)
            ['productSpecificInput']['mandate']['uniqueMandateReference'];

        $this->assertSame('mdshop'.str_repeat('x', 29), $reference);
    }

    public function testAccountHolderIsTruncatedTo50Characters(): void
    {
        $bankAccount = $this->buildDirectDebit('', false, str_repeat('Ü', 60))['productSpecificInput']['mandate']['bankAccountIban'];

        $this->assertSame(str_repeat('Ü', 50), $bankAccount['accountHolder']);
    }

    public function testSecuredDirectDebit(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPayment(
            $order,
            ['iban' => 'DE02120300000000202051', 'accountholder' => str_repeat('a', 55)],
            ['automatic_capture' => '0'],
            'payone_cp_secureddirectdebit'
        );

        $this->assertSame([
            'requiresApproval' => true,
            'productSpecificInput' => ['bankAccountInformation' => [
                'iban' => 'DE02120300000000202051',
                'accountHolder' => str_repeat('a', 50),
            ]],
        ], (new SecuredDirectDebitBuilder(new SubjectReader()))->build(['payment' => $this->createPaymentDataObject($payment)]));
    }

    public function testSecuredInvoice(): void
    {
        $builder = new SecuredInvoiceBuilder(new SubjectReader());
        foreach (['0' => true, '1' => false] as $automaticCapture => $requiresApproval) {
            $payment = $this->createPayment($this->createOrder(), [], ['automatic_capture' => (string)$automaticCapture]);

            $this->assertSame(
                ['requiresApproval' => $requiresApproval],
                $builder->build(['payment' => $this->createPaymentDataObject($payment)])
            );
        }
    }
}
