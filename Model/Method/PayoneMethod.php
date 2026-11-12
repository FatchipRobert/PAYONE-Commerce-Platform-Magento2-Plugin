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

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\Method\Adapter;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order\Payment;
use Psr\Log\LoggerInterface;

/**
 * Base class of all PAYONE payment methods
 *
 * Every method has its own class in this directory which decides about its own behaviour: identity (code, PAYONE
 * method type and product id), availability, currencies, checkout input, checkout JS config, capture mode and the
 * method specific input of the payment request. The API calls are not made here, Magento's Adapter executes the
 * commands of the command pool (etc/di.xml).
 *
 * Constructor arguments: formBlockType, infoBlockType and commandPool are set for all methods in etc/di.xml
 * (<type name="...\PayoneMethod">), valueHandlerPool and validatorPool per method. A method with own dependencies
 * repeats the arguments of this constructor and adds its own before $validatorPool.
 */
abstract class PayoneMethod extends Adapter
{
    /**
     * Magento payment method code (Model\Payone::METHOD_*)
     */
    protected const CODE = '';

    /**
     * Type of the method specific input of the PAYONE API, e.g. "redirectPaymentMethod" → "redirectPaymentMethodSpecificInput"
     */
    protected const PAYONE_METHOD_TYPE = '';

    /**
     * PAYONE payment product id, e.g. 840 (PayPal)
     */
    protected const PAYONE_PRODUCT_ID = 0;

    /**
     * Currencies the method can be used with (ISO 4217), empty = all
     */
    protected const ALLOWED_CURRENCIES = [];

    /**
     * Keys of the checkout input (additional_data of the JS) copied into the additional_information of the payment
     */
    protected const ADDITIONAL_DATA_KEYS = [];

    /**
     * Keys of ADDITIONAL_DATA_KEYS that are only needed for the payment request - removed after it (reduceCheckoutInput())
     */
    protected const REQUEST_ONLY_DATA_KEYS = [];

    /**
     * Keys of ADDITIONAL_DATA_KEYS of which only the last 4 characters are kept after the payment request, e.g. the
     * IBAN - enough to tell which account was used (reduceCheckoutInput())
     */
    protected const MASKED_DATA_KEYS = [];

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
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
        ?ValidatorPoolInterface $validatorPool = null,
    ) {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            static::CODE,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $validatorPool,
            null,
            $logger
        );
    }

    /**
     * Returns the type of the method specific input of the PAYONE API (PAYONE_METHOD_TYPE)
     *
     * @return string
     */
    public function getPayoneMethodType(): string
    {
        return static::PAYONE_METHOD_TYPE;
    }

    /**
     * Returns the PAYONE payment product id (PAYONE_PRODUCT_ID)
     *
     * @return int
     */
    public function getPayoneProductId(): int
    {
        return static::PAYONE_PRODUCT_ID;
    }

    /**
     * Checks ALLOWED_CURRENCIES, then the "currency" validator of the validatorPool if there is one
     *
     * Called for the payment method list of the checkout and when the order is placed.
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode): bool
    {
        if (!empty(static::ALLOWED_CURRENCIES) && !in_array($currencyCode, static::ALLOWED_CURRENCIES, true)) {
            return false;
        }
        return (bool)parent::canUseForCurrency($currencyCode);
    }

    /**
     * Copies the checkout input of ADDITIONAL_DATA_KEYS into the payment, empty values are skipped
     *
     * The event payment_method_assign_data_<code> of Magento is still dispatched by the parent.
     *
     * @param DataObject $data
     * @return $this
     */
    public function assignData(DataObject $data)
    {
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (is_array($additionalData)) {
            foreach (static::ADDITIONAL_DATA_KEYS as $key) {
                if (!empty($additionalData[$key])) {
                    $this->getInfoInstance()->setAdditionalInformation($key, $additionalData[$key]);
                }
            }
        }

        return parent::assignData($data);
    }

    /**
     * Removes the checkout input of REQUEST_ONLY_DATA_KEYS and masks the one of MASKED_DATA_KEYS
     *
     * Called by Gateway\Response\CheckoutInputHandler after the successful payment request, the order is saved
     * afterwards - the data is not needed for capture, refund or cancellation.
     *
     * @param Payment $payment
     * @return void
     */
    public function reduceCheckoutInput(Payment $payment): void
    {
        foreach (static::REQUEST_ONLY_DATA_KEYS as $key) {
            $payment->unsAdditionalInformation($key);
        }
        foreach (static::MASKED_DATA_KEYS as $key) {
            $value = (string)$payment->getAdditionalInformation($key);
            if (strlen($value) > 4) {
                $payment->setAdditionalInformation($key, str_repeat('*', strlen($value) - 4).substr($value, -4));
            }
        }
    }

    /**
     * Returns window.checkoutConfig.payment.payone_cp.<method code> - only requested if the method is available
     *
     * Empty: no entry, the JS of the method gets [].
     *
     * @return array
     */
    public function getFrontendConfig(): array
    {
        return [];
    }

    /**
     * Returns if the payment is captured directly (SALE) instead of only authorized - config value automatic_capture
     *
     * Methods without a choice override it.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isAutomaticCapture(int|string|null $storeId = null): bool
    {
        return (bool)$this->getConfigData('automatic_capture', $storeId);
    }

    /**
     * Returns the content of "<PAYONE_METHOD_TYPE>SpecificInput" of the payment request
     *
     * Wrapped by Gateway\Request\PaymentMethodSpecificBuilder: product specific fields go under the key
     * "productSpecificInput" (renamed to "paymentProduct<id>SpecificInput"), "paymentProductId" only if it differs
     * from PAYONE_PRODUCT_ID.
     *
     * @param Payment $payment
     * @return array
     */
    public function getSpecificInput(Payment $payment): array
    {
        throw new \LogicException('Payment method '.$this->getCode().' has no payment method specific input');
    }

    /**
     * Returns if capture (final partial) and cancellation need a cancellationReason (PAYONE Buy Now Pay Later)
     *
     * @return bool
     */
    public function requiresCancellationReason(): bool
    {
        return false;
    }

    /**
     * Returns if the payment request always needs the customer device: IP address and device token of the checkout
     *
     * Without it the IP is only sent if configured (transmit_ip).
     *
     * @return bool
     */
    public function requiresCustomerDevice(): bool
    {
        return false;
    }

    /**
     * Returns the token of the device fingerprint of the checkout, empty for methods without fingerprint
     *
     * Sent as customerDevice.deviceToken if requiresCustomerDevice().
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getDeviceToken(int|string|null $storeId = null): string
    {
        return '';
    }

    /**
     * Called when the order is cancelled (event sales_order_payment_cancel, Observer\OrderPaymentCancel)
     *
     * For work at PAYONE that is not done by a void/cancel command - Magento calls these only for an authorization
     * transaction. An exception stops the cancellation.
     *
     * @param Payment $payment
     * @return void
     */
    public function onCancel(Payment $payment): void // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
    {
        // nothing to do for most methods
    }
}
