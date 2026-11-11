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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\Request\Mapper\CustomerMapper;
use Payone\CommercePlatform\Model\Method\BuyNowPayLater;
use Payone\CommercePlatform\Model\Method\SecuredDirectDebit;
use Payone\CommercePlatform\Model\Method\SecuredInvoice;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use Payone\CommercePlatform\Test\Unit\Helper\PaymentMethodTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour shared by the Buy Now Pay Later methods (with Secured Invoice, B2B with Secured Direct Debit)
 */
class BuyNowPayLaterTest extends TestCase
{
    use MockCreationTrait;
    use OrderMockTrait;
    use PaymentMethodTrait;

    private const ADDRESS = [
        'firstname' => 'Max',
        'lastname' => 'Mustermann',
        'company' => '',
        'street' => 'Main Street 1',
        'postcode' => '12345',
        'city' => 'Berlin',
        'country_id' => 'DE',
    ];

    /**
     * @var array
     */
    private array $paymentValues = [];

    private function createMethod(
        string $class = SecuredInvoice::class,
        array $configData = ['active' => '1'],
        ?Session $session = null,
        ?IdentityGeneratorInterface $identityGenerator = null
    ): BuyNowPayLater {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('isLiveMode')->willReturn(false);
        $generalConfig->method('getMerchantId')->willReturn('P1_123');
        $generalConfig->method('getPaymentValue')->willReturnCallback(
            fn ($code, $field) => $this->paymentValues[$code.'/'.$field] ?? null
        );
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeUrl')->willReturnCallback(static fn ($value) => htmlspecialchars((string)$value));

        return new $class(
            ...$this->methodArguments($configData),
            generalConfig: $generalConfig,
            checkoutSession: $session ?? $this->createStub(Session::class),
            identityGenerator: $identityGenerator ?? $this->createStub(IdentityGeneratorInterface::class),
            escaper: $escaper
        );
    }

    public function testIdentityAndRequirements(): void
    {
        $method = $this->createMethod();

        $this->assertSame('financingPaymentMethod', $method->getPayoneMethodType());
        $this->assertTrue($method->requiresCancellationReason());
        $this->assertTrue($method->requiresCustomerDevice());
        $this->assertTrue($method->canUseForCurrency('EUR'));
        $this->assertFalse($method->canUseForCurrency('CHF'));
    }

    public function testSpecificInputIsTheCaptureMode(): void
    {
        $payment = $this->createPayment($this->createOrder());

        $this->assertSame(
            ['requiresApproval' => true],
            $this->createMethod(SecuredInvoice::class, ['automatic_capture' => '0'])->getSpecificInput($payment)
        );
        $this->assertSame(
            ['requiresApproval' => false],
            $this->createMethod(SecuredInvoice::class, ['automatic_capture' => '1'])->getSpecificInput($payment)
        );
    }

    public function testFrontendConfigCreatesDeviceTokenOncePerSession(): void
    {
        $sessionData = [];
        $session = $this->createPartialMockWithReflection(Session::class, ['getData', 'setData']);
        $session->method('getData')->willReturnCallback(static function ($key) use (&$sessionData) {
            return $sessionData[$key] ?? null;
        });
        $session->expects($this->once())->method('setData')->willReturnCallback(
            static function ($key, $value) use (&$sessionData, $session) {
                $sessionData[$key] = $value;
                return $session;
            }
        );
        $identityGenerator = $this->createMock(IdentityGeneratorInterface::class);
        $identityGenerator->expects($this->once())->method('generateId')->willReturn('uuid-1');
        $code = Payone::METHOD_SECURED_INVOICE;
        $this->paymentValues = [
            $code.'/payla_partner_id' => 'e7yeryF2of8X',
            $code.'/terms_url' => 'https://payone.test/terms?a=1&b=2',
            $code.'/privacy_url' => 'https://payone.test/privacy',
        ];
        $method = $this->createMethod(SecuredInvoice::class, [], $session, $identityGenerator);

        $config = $method->getFrontendConfig();

        $this->assertSame('e7yeryF2of8X', $config['paylaPartnerId']);
        $this->assertSame('P1_123', $config['paylaMerchantId']);
        $this->assertSame('t', $config['paylaEnvironment']);
        $this->assertSame('e7yeryF2of8X_P1_123_uuid-1', $config['deviceToken']);
        $this->assertStringContainsString('href="https://payone.test/terms?a=1&amp;b=2"', $config['legalText']);
        $this->assertStringContainsString('href="https://payone.test/privacy"', $config['legalText']);
        $this->assertSame(
            'e7yeryF2of8X_P1_123_uuid-1',
            $method->getFrontendConfig()['deviceToken'],
            'same token for the whole checkout session'
        );
    }

    private function createQuoteAddress(array $data, bool $sameAsBilling = false): QuoteAddress
    {
        $address = $this->createPartialStub(QuoteAddress::class, []);
        $address->setData(array_merge($data, ['same_as_billing' => $sameAsBilling ? 1 : 0]));
        return $address;
    }

    private function isAvailable(
        array $billing,
        array $shipping,
        string $class = SecuredInvoice::class,
        bool $sameAsBilling = false,
        bool $isVirtual = false
    ): bool {
        $quote = $this->createPartialStub(Quote::class, ['getBillingAddress', 'getShippingAddress', 'isVirtual']);
        $quote->method('getBillingAddress')->willReturn($this->createQuoteAddress($billing));
        $quote->method('getShippingAddress')->willReturn($this->createQuoteAddress($shipping, $sameAsBilling));
        $quote->method('isVirtual')->willReturn($isVirtual);
        $payment = $this->createPartialStub(QuotePayment::class, ['getQuote']);
        $payment->method('getQuote')->willReturn($quote);

        $method = $this->createMethod($class);
        $method->setInfoInstance($payment);
        return $method->isAvailable();
    }

    public function testSameAddressesAreAvailable(): void
    {
        $shipping = array_merge(self::ADDRESS, ['city' => ' BERLIN ', 'telephone' => 'other fields are not compared']);
        $this->assertTrue($this->isAvailable(self::ADDRESS, $shipping));
    }

    public function testDifferentShippingAddressIsNotAvailable(): void
    {
        $this->assertFalse($this->isAvailable(self::ADDRESS, array_merge(self::ADDRESS, ['street' => 'Other Street 2'])));
    }

    public function testSameAsBillingVirtualOrMissingBillingAddressAreAvailable(): void
    {
        $other = array_merge(self::ADDRESS, ['street' => 'Other Street 2']);
        $this->assertTrue($this->isAvailable(self::ADDRESS, $other, SecuredInvoice::class, true));
        $this->assertTrue($this->isAvailable(self::ADDRESS, $other, SecuredInvoice::class, false, true));
        $this->assertTrue($this->isAvailable(array_merge(self::ADDRESS, ['country_id' => null]), $other));
    }

    public function testCompanyIsOnlyRejectedWhenB2bIsNotAllowed(): void
    {
        $company = array_merge(self::ADDRESS, ['company' => 'ACME GmbH']);
        $this->assertTrue($this->isAvailable($company, $company, SecuredInvoice::class));
        $this->assertFalse($this->isAvailable($company, $company, SecuredDirectDebit::class));
        $this->assertTrue($this->isAvailable(array_merge(self::ADDRESS, ['company' => ' ']), self::ADDRESS, SecuredDirectDebit::class));
    }

    public function testAvailabilityWithoutQuotePayment(): void
    {
        $this->assertTrue($this->createMethod(SecuredDirectDebit::class)->isAvailable(), 'method list: no info instance');
        $this->assertFalse($this->createMethod(SecuredInvoice::class, ['active' => '0'])->isAvailable(), 'inactive');

        $method = $this->createMethod(SecuredDirectDebit::class);
        $method->setInfoInstance($this->createPayment($this->createOrder()));
        $this->assertTrue($method->isAvailable(), 'order payment');

        $quotePayment = $this->createPartialStub(QuotePayment::class, ['getQuote']);
        $quotePayment->method('getQuote')->willReturn(null);
        $method->setInfoInstance($quotePayment);
        $this->assertTrue($method->isAvailable(), 'quote payment without quote');
    }

    /**
     * Returns the error message of validate(), '' if valid
     *
     * @param array $additionalInformation
     * @param string|null $customerDob
     * @param string|null $addressPhone
     * @param bool $withAddress
     * @return string
     */
    private function validateCustomerData(
        array $additionalInformation,
        ?string $customerDob,
        ?string $addressPhone,
        bool $withAddress = true
    ): string {
        $order = $this->createOrder(['customer_dob' => $customerDob]);
        $order->method('getBillingAddress')->willReturn($withAddress ? $this->createAddress(['telephone' => $addressPhone]) : null);
        $method = $this->createMethod();
        $method->setInfoInstance($this->createPayment($order, $additionalInformation));

        try {
            $this->assertSame($method, $method->validate());
        } catch (LocalizedException $e) {
            return $e->getMessage();
        }
        return '';
    }

    public function testCustomerDataValid(): void
    {
        $this->assertSame('', $this->validateCustomerData(
            [CustomerMapper::KEY_DATE_OF_BIRTH => '1980-01-15', CustomerMapper::KEY_TELEPHONE => '0301234'],
            null,
            null
        ));
        $this->assertSame('', $this->validateCustomerData([], '1980-01-15 00:00:00', '0301234'), 'account data is enough');
    }

    public function testCustomerDataMissing(): void
    {
        $this->assertSame(
            "Please enter a valid date of birth. You must be at least 18 years old to use this payment method.\n"
            .'Please enter your phone number.',
            $this->validateCustomerData([], null, ' ', false)
        );
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
        $error = $this->validateCustomerData([CustomerMapper::KEY_DATE_OF_BIRTH => $dateOfBirth], null, '0301234');
        $this->assertSame($isValid, $error === '');
    }

    public function testQuotePaymentsAreNotValidated(): void
    {
        $method = $this->createMethod();
        $this->assertSame($method, $method->validate(), 'no info instance');

        $method->setInfoInstance($this->createPartialStub(QuotePayment::class, []));
        $this->assertSame($method, $method->validate());
    }
}
