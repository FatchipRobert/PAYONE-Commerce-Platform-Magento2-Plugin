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

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Model\Country;
use Payone\CommercePlatform\Model\Method\DirectDebit;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use Payone\CommercePlatform\Test\Unit\Helper\PaymentMethodTrait;
use PHPUnit\Framework\TestCase;

class DirectDebitTest extends TestCase
{
    use OrderMockTrait;
    use PaymentMethodTrait;

    private function createMethod(string $refPrefix = '', bool $requestBic = false): DirectDebit
    {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getRefPrefix')->willReturn($refPrefix);
        $generalConfig->method('getPaymentValue')->willReturnCallback(
            static fn ($code, $field) => $code !== Payone::METHOD_DIRECT_DEBIT ? null : match ($field) {
                'request_bic' => $requestBic ? '1' : '0',
                'creditor_id' => 'DE98ZZZ09999999999',
                default => null,
            }
        );
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('date')->willReturn(new \DateTime('2026-11-03 23:30:00'));
        $country = $this->createStub(Country::class);
        $country->method('getEnabledCountries')->willReturnCallback(
            static fn ($code) => $code === Payone::METHOD_DIRECT_DEBIT ? [['id' => 'DE', 'title' => 'Deutschland']] : []
        );

        return new DirectDebit(
            ...$this->methodArguments(['automatic_capture' => '0']),
            generalConfig: $generalConfig,
            merchantReference: new MerchantReference($generalConfig),
            timezone: $timezone,
            country: $country
        );
    }

    private function buildInput(string $refPrefix, bool $requestBic, string $accountHolder = 'Max Mustermann'): array
    {
        $payment = $this->createPayment(
            $this->createOrder(['increment_id' => '000000100']),
            ['iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'accountholder' => $accountHolder],
            [],
            Payone::METHOD_DIRECT_DEBIT
        );
        return $this->createMethod($refPrefix, $requestBic)->getSpecificInput($payment);
    }

    public function testIdentityAndAlwaysAutomaticCapture(): void
    {
        $method = $this->createMethod();

        $this->assertSame(Payone::METHOD_DIRECT_DEBIT, $method->getCode());
        $this->assertSame('sepaDirectDebitPaymentMethod', $method->getPayoneMethodType());
        $this->assertSame(771, $method->getPayoneProductId());
        $this->assertTrue($method->isAutomaticCapture(), 'regardless of the config value');
    }

    public function testFrontendConfig(): void
    {
        $this->assertSame([
            'sepaCountries' => [['id' => 'DE', 'title' => 'Deutschland']],
            'requestBic' => true,
        ], $this->createMethod('', true)->getFrontendConfig());
    }

    public function testSendsUniqueMandate(): void
    {
        $this->assertSame(['productSpecificInput' => ['mandate' => [
            'recurrenceType' => 'UNIQUE',
            'bankAccountIban' => ['iban' => 'DE02120300000000202051', 'accountHolder' => 'Max Mustermann'],
            'dateOfSignature' => '20261103',
            'creditorId' => 'DE98ZZZ09999999999',
            'uniqueMandateReference' => 'mddev000000100',
        ]]], $this->buildInput('dev', false));
    }

    public function testWithBic(): void
    {
        $bankAccount = $this->buildInput('', true)['productSpecificInput']['mandate']['bankAccountIban'];

        $this->assertSame('BYLADEM1001', $bankAccount['bic']);
    }

    public function testMandateReferenceIsCleanedAndTruncatedTo35Characters(): void
    {
        $reference = $this->buildInput('shop_ä#'.str_repeat('x', 40), false)
            ['productSpecificInput']['mandate']['uniqueMandateReference'];

        $this->assertSame('mdshop'.str_repeat('x', 29), $reference);
    }

    public function testAccountHolderIsTruncatedTo50Characters(): void
    {
        $bankAccount = $this->buildInput('', false, str_repeat('Ü', 60))['productSpecificInput']['mandate']['bankAccountIban'];

        $this->assertSame(str_repeat('Ü', 50), $bankAccount['accountHolder']);
    }

    public function testIbanIsMaskedAfterThePaymentRequest(): void
    {
        $payment = $this->createPayment($this->createOrder(), ['iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001']);

        $this->createMethod()->reduceCheckoutInput($payment);

        $this->assertSame('******************2051', $payment->getAdditionalInformation('iban'));
        $this->assertSame('BYLADEM1001', $payment->getAdditionalInformation('bic'));
    }
}
