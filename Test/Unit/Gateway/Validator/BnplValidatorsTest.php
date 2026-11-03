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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Validator;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Validator\Result;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Payone\CommercePlatform\Gateway\Request\Mapper\CustomerMapper;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Gateway\Validator\BnplAvailabilityValidator;
use Payone\CommercePlatform\Gateway\Validator\BnplCustomerDataValidator;
use Payone\CommercePlatform\Gateway\Validator\CurrencyValidator;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BnplValidatorsTest extends TestCase
{
    use OrderMockTrait;

    private const ADDRESS = [
        'firstname' => 'Max',
        'lastname' => 'Mustermann',
        'company' => '',
        'street' => 'Main Street 1',
        'postcode' => '12345',
        'city' => 'Berlin',
        'country_id' => 'DE',
    ];

    private function createResultFactory(): ResultInterfaceFactory
    {
        $resultFactory = $this->createStub(ResultInterfaceFactory::class);
        $resultFactory->method('create')->willReturnCallback(
            static fn (array $data) => new Result($data['isValid'], $data['failsDescription'], $data['errorCodes'])
        );
        return $resultFactory;
    }

    public function testCurrencyValidator(): void
    {
        $validator = new CurrencyValidator($this->createResultFactory(), ['EUR']);

        $this->assertTrue($validator->validate(['currency' => 'EUR'])->isValid());
        $this->assertFalse($validator->validate(['currency' => 'CHF'])->isValid());
        $this->assertFalse($validator->validate([])->isValid());
        $this->assertFalse((new CurrencyValidator($this->createResultFactory()))->validate(['currency' => 'EUR'])->isValid());
    }

    private function createQuoteAddress(array $data, bool $sameAsBilling = false): QuoteAddress
    {
        $address = $this->createPartialStub(QuoteAddress::class, []);
        $address->setData(array_merge($data, ['same_as_billing' => $sameAsBilling ? 1 : 0]));
        return $address;
    }

    private function validateAvailability(
        array $billing,
        array $shipping,
        bool $b2bAllowed = true,
        bool $sameAsBilling = false,
        bool $isVirtual = false
    ): bool {
        $quote = $this->createPartialStub(Quote::class, ['getBillingAddress', 'getShippingAddress', 'isVirtual']);
        $quote->method('getBillingAddress')->willReturn($this->createQuoteAddress($billing));
        $quote->method('getShippingAddress')->willReturn($this->createQuoteAddress($shipping, $sameAsBilling));
        $quote->method('isVirtual')->willReturn($isVirtual);
        $payment = $this->createPartialStub(QuotePayment::class, ['getQuote']);
        $payment->method('getQuote')->willReturn($quote);
        $paymentDO = $this->createStub(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        return (new BnplAvailabilityValidator($this->createResultFactory(), new SubjectReader(), $b2bAllowed))
            ->validate(['payment' => $paymentDO])
            ->isValid();
    }

    public function testSameAddressesAreAvailable(): void
    {
        $shipping = array_merge(self::ADDRESS, ['city' => ' BERLIN ', 'telephone' => 'other fields are not compared']);
        $this->assertTrue($this->validateAvailability(self::ADDRESS, $shipping));
    }

    public function testDifferentShippingAddressIsNotAvailable(): void
    {
        $this->assertFalse($this->validateAvailability(self::ADDRESS, array_merge(self::ADDRESS, ['street' => 'Other Street 2'])));
    }

    public function testSameAsBillingVirtualOrMissingBillingAddressAreAvailable(): void
    {
        $other = array_merge(self::ADDRESS, ['street' => 'Other Street 2']);
        $this->assertTrue($this->validateAvailability(self::ADDRESS, $other, true, true));
        $this->assertTrue($this->validateAvailability(self::ADDRESS, $other, true, false, true));
        $this->assertTrue($this->validateAvailability(array_merge(self::ADDRESS, ['country_id' => null]), $other));
    }

    public function testCompanyIsOnlyRejectedWhenB2bIsNotAllowed(): void
    {
        $company = array_merge(self::ADDRESS, ['company' => 'ACME GmbH']);
        $this->assertTrue($this->validateAvailability($company, $company, true));
        $this->assertFalse($this->validateAvailability($company, $company, false));
        $this->assertTrue($this->validateAvailability(array_merge(self::ADDRESS, ['company' => ' ']), self::ADDRESS, false));
    }

    public function testAvailabilityWithoutQuotePayment(): void
    {
        $validator = new BnplAvailabilityValidator($this->createResultFactory(), new SubjectReader(), false);
        $orderPayment = $this->createPayment($this->createOrder());
        $this->assertTrue($validator->validate(['payment' => $this->createPaymentDataObject($orderPayment)])->isValid());

        $quotePayment = $this->createPartialStub(QuotePayment::class, ['getQuote']);
        $quotePayment->method('getQuote')->willReturn(null);
        $paymentDO = $this->createStub(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($quotePayment);
        $this->assertTrue($validator->validate(['payment' => $paymentDO])->isValid());
    }

    private function validateCustomerData(array $additionalInformation, ?string $customerDob, ?string $addressPhone, bool $withAddress = true): array
    {
        $order = $this->createOrder(['customer_dob' => $customerDob]);
        $order->method('getBillingAddress')->willReturn($withAddress ? $this->createAddress(['telephone' => $addressPhone]) : null);
        $payment = $this->createPayment($order, $additionalInformation);

        $result = (new BnplCustomerDataValidator($this->createResultFactory()))->validate(['payment' => $payment]);
        return array_map('strval', $result->getFailsDescription());
    }

    public function testCustomerDataValid(): void
    {
        $this->assertSame([], $this->validateCustomerData(
            [CustomerMapper::KEY_DATE_OF_BIRTH => '1980-01-15', CustomerMapper::KEY_TELEPHONE => '0301234'],
            null,
            null
        ));
        $this->assertSame([], $this->validateCustomerData([], '1980-01-15 00:00:00', '0301234'), 'account data is enough');
    }

    public function testCustomerDataMissing(): void
    {
        $this->assertSame([
            'Please enter a valid date of birth. You must be at least 18 years old to use this payment method.',
            'Please enter your phone number.',
        ], $this->validateCustomerData([], null, ' ', false));
    }

    public static function dateOfBirthProvider(): array
    {
        return [
            'exactly 18 today' => [(new \DateTime('-18 years'))->format('Y-m-d'), true],
            '18 tomorrow' => [(new \DateTime('-18 years +1 day'))->format('Y-m-d'), false],
            'invalid date' => ['1980-02-30', false],
            'wrong format' => ['15.01.1980', false],
            'before 1900' => ['1899-12-31', false],
            'empty dob of old Magento versions' => ['0000-00-00 00:00:00', false],
        ];
    }

    #[DataProvider('dateOfBirthProvider')]
    public function testDateOfBirth(string $dateOfBirth, bool $isValid): void
    {
        $errors = $this->validateCustomerData([CustomerMapper::KEY_DATE_OF_BIRTH => $dateOfBirth], null, '0301234');
        $this->assertSame($isValid, $errors === []);
    }

    public function testCustomerDataValidatorIgnoresQuotePayments(): void
    {
        $validator = new BnplCustomerDataValidator($this->createResultFactory());
        $this->assertTrue($validator->validate(['payment' => $this->createPartialStub(QuotePayment::class, [])])->isValid());
        $this->assertTrue($validator->validate([])->isValid());
    }
}
