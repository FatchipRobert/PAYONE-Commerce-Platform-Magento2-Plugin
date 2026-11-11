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

namespace Payone\CommercePlatform\Model\Method;

use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\Request\Mapper\CustomerMapper;
use Psr\Log\LoggerInterface;

/**
 * Base of the PAYONE Buy Now Pay Later methods: Secured Invoice and Secured Direct Debit
 *
 * Direct methods (authorize). Date of birth and phone of the checkout are sent by Gateway\Request\Mapper\CustomerMapper,
 * the device token of the Payla fingerprint and the IP by Gateway\Request\OrderBuilder (requiresCustomerDevice()).
 * Capture (final partial) and cancellation need a cancellationReason. Only EUR; the countries (DE, AT) are restricted
 * by the "country" validator (specificcountry in config.xml). PAYONE only accepts a shipping address identical to the
 * billing address, B2B only if B2B_ALLOWED, and needs date of birth (adult) and phone.
 */
abstract class BuyNowPayLater extends PayoneMethod
{
    protected const PAYONE_METHOD_TYPE = 'financingPaymentMethod';
    protected const ALLOWED_CURRENCIES = ['EUR'];
    protected const ADDITIONAL_DATA_KEYS = ['dateofbirth', 'telephone', 'device_token'];

    /**
     * Whether billing addresses with a company are accepted
     */
    protected const B2B_ALLOWED = true;

    private const MINIMUM_AGE = 18;

    /**
     * Address fields which must be identical in billing and shipping address
     */
    private const ADDRESS_FIELDS = ['firstname', 'lastname', 'company', 'street', 'postcode', 'city', 'country_id'];

    private const SESSION_KEY_DEVICE_SESSION_ID = 'payone_cp_device_session_id';

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
     * @param GeneralConfig $generalConfig
     * @param Session $checkoutSession proxy (etc/di.xml)
     * @param IdentityGeneratorInterface $identityGenerator
     * @param Escaper $escaper
     * @param ValidatorPoolInterface|null $validatorPool
     */
    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        string $formBlockType,
        string $infoBlockType,
        CommandPoolInterface $commandPool,
        LoggerInterface $logger,
        private readonly GeneralConfig $generalConfig,
        private readonly Session $checkoutSession,
        private readonly IdentityGeneratorInterface $identityGenerator,
        private readonly Escaper $escaper,
        ?ValidatorPoolInterface $validatorPool = null,
    ) {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $logger,
            $validatorPool
        );
    }

    /**
     * Additionally checks the addresses and the company of the quote
     *
     * Only when the payment has an info instance - not for the method list of the checkout (MethodList sets it
     * afterwards), only when the order is placed. Intended: both conditions can still change in the payment step
     * (billing address form), the checkout JS (bnpl-base.js) shows a warning and blocks the order button.
     * Payments without quote (e.g. of existing orders) are not checked.
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(?CartInterface $quote = null): bool
    {
        if (!parent::isAvailable($quote)) {
            return false;
        }

        $payment = $this->getInfoInstance();
        if (!$payment instanceof QuotePayment || !$payment->getQuote()) {
            return true;
        }

        $paymentQuote = $payment->getQuote();
        return $this->hasSameAddresses($paymentQuote)
            && (static::B2B_ALLOWED || trim((string)$paymentQuote->getBillingAddress()->getCompany()) === '');
    }

    /**
     * Checks the customer data: date of birth (adult) and phone
     *
     * Called by Magento whenever payment data is assigned to the quote and again when the order is placed.
     * Only the order payment is checked: Magento also assigns the bare method code without the checkout input
     * (set-payment-information when the method is selected or the billing address changes).
     * Date of birth and phone number come from the checkout input or from the customer account / billing address.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function validate()
    {
        parent::validate();

        $payment = $this->getInfoInstance();
        if (!$payment instanceof Payment) {
            return $this;
        }
        $order = $payment->getOrder();

        $errors = [];
        $dateOfBirth = $payment->getAdditionalInformation(CustomerMapper::KEY_DATE_OF_BIRTH) ?: $order->getCustomerDob();
        if (!$this->isAdult((string)$dateOfBirth)) {
            $errors[] = __('Please enter a valid date of birth. You must be at least 18 years old to use this payment method.');
        }

        $billingAddress = $order->getBillingAddress();
        $telephone = $payment->getAdditionalInformation(CustomerMapper::KEY_TELEPHONE)
            ?: ($billingAddress ? $billingAddress->getTelephone() : null);
        if (empty(trim((string)$telephone))) {
            $errors[] = __('Please enter your phone number.');
        }

        if (!empty($errors)) {
            throw new LocalizedException(__(implode("\n", $errors)));
        }
        return $this;
    }

    /**
     * Payla device fingerprint and the legal text
     *
     * The device token "<Payla partner id>_<merchant id>_<session id>" is created once per checkout session.
     * The checkout JS loads the Payla fingerprint script with it and sends it back with the payment data.
     *
     * @return array
     */
    public function getFrontendConfig(): array
    {
        $partnerId = (string)$this->generalConfig->getPaymentValue($this->getCode(), 'payla_partner_id');
        $merchantId = $this->generalConfig->getMerchantId();

        return [
            'paylaPartnerId' => $partnerId,
            'paylaMerchantId' => $merchantId,
            'paylaEnvironment' => $this->generalConfig->isLiveMode() ? 'p' : 't',
            'deviceToken' => $partnerId.'_'.$merchantId.'_'.$this->getDeviceSessionId(),
            'legalText' => $this->getLegalText(),
        ];
    }

    /**
     * Capture mode of the payment
     *
     * With requiresApproval = true PAYONE waits for the capture of the invoice in the admin, with false it captures
     * directly (invoice via payment.captured webhook).
     *
     * @param Payment $payment
     * @return array
     */
    public function getSpecificInput(Payment $payment): array
    {
        return [
            'requiresApproval' => !$this->isAutomaticCapture($payment->getOrder()->getStoreId()),
        ];
    }

    /**
     * Mandatory for final partial captures and cancellations
     *
     * @return bool
     */
    public function requiresCancellationReason(): bool
    {
        return true;
    }

    /**
     * IP address and device token are mandatory
     *
     * @return bool
     */
    public function requiresCustomerDevice(): bool
    {
        return true;
    }

    /**
     * Whether the date of birth (YYYY-MM-DD, optionally with time) is valid and at least MINIMUM_AGE years ago
     *
     * @param string $dateOfBirth
     * @return bool
     */
    private function isAdult(string $dateOfBirth): bool
    {
        $date = \DateTime::createFromFormat('!Y-m-d', substr($dateOfBirth, 0, 10));
        if ($date === false || $date->format('Y-m-d') !== substr($dateOfBirth, 0, 10)) {
            return false;
        }
        return $date <= new \DateTime('-'.self::MINIMUM_AGE.' years') && $date->format('Y') >= 1900;
    }

    /**
     * Whether billing and shipping address are identical, virtual quotes have no shipping address
     *
     * @param Quote $quote
     * @return bool
     */
    private function hasSameAddresses(Quote $quote): bool
    {
        if ($quote->isVirtual()) {
            return true;
        }

        $billingAddress = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress->getSameAsBilling() || empty($billingAddress->getCountryId())) {
            return true; // no billing address entered yet - checked again when the order is placed
        }

        return $this->getComparableData($billingAddress) === $this->getComparableData($shippingAddress);
    }

    /**
     * Returns the compared fields of an address, normalized
     *
     * @param Address $address
     * @return array
     */
    private function getComparableData(Address $address): array
    {
        $data = [];
        foreach (self::ADDRESS_FIELDS as $field) {
            $data[$field] = mb_strtolower(trim((string)$address->getData($field)));
        }
        return $data;
    }

    /**
     * Returns the consent text with the links to the payment terms and data protection notice of PAYONE (HTML)
     *
     * @return string
     */
    private function getLegalText(): string
    {
        return (string)__(
            'By placing this order, I agree to the <a href="%1" target="_blank" rel="noopener">supplementary payment terms</a>'
            .' and the performance of a risk assessment for the selected payment method.'
            .' I am aware of the <a href="%2" target="_blank" rel="noopener">supplementary data protection notice</a>.',
            $this->escaper->escapeUrl((string)$this->generalConfig->getPaymentValue($this->getCode(), 'terms_url')),
            $this->escaper->escapeUrl((string)$this->generalConfig->getPaymentValue($this->getCode(), 'privacy_url'))
        );
    }

    /**
     * Returns the id of the fingerprint session, created once per checkout session
     *
     * @return string
     */
    private function getDeviceSessionId(): string
    {
        $sessionId = (string)$this->checkoutSession->getData(self::SESSION_KEY_DEVICE_SESSION_ID);
        if ($sessionId === '') {
            $sessionId = $this->identityGenerator->generateId();
            $this->checkoutSession->setData(self::SESSION_KEY_DEVICE_SESSION_ID, $sessionId);
        }
        return $sessionId;
    }
}
