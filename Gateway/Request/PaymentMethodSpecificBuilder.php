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

namespace Payone\CommercePlatform\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Model\Method\PayoneMethod;

/**
 * "checkout.orderRequest.paymentMethodSpecificInput.<type>SpecificInput" of POST /commerce-cases
 *
 * The content comes from the payment method (PayoneMethod::getSpecificInput()) and is wrapped:
 * - key from the method type, e.g. "redirectPaymentMethod" → "redirectPaymentMethodSpecificInput"
 * - paymentProductId of the method, unless its input sets one (credit card type)
 * - the method's "productSpecificInput" becomes "paymentProduct<id>SpecificInput"
 */
class PaymentMethodSpecificBuilder implements BuilderInterface
{
    private const PRODUCT_SPECIFIC_INPUT = 'productSpecificInput';

    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
    ) {
    }

    /**
     * Builds the request part
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        /** @var Payment $payment */
        $payment = $this->subjectReader->readPayment($buildSubject)->getPayment();
        $method = $payment->getMethodInstance();
        if (!$method instanceof PayoneMethod) {
            throw new \LogicException('Payment method '.$method->getCode().' is no PAYONE payment method');
        }

        $input = $method->getSpecificInput($payment);
        $input['paymentProductId'] ??= $method->getPayoneProductId();

        if (isset($input[self::PRODUCT_SPECIFIC_INPUT])) {
            $input['paymentProduct'.$input['paymentProductId'].'SpecificInput'] = $input[self::PRODUCT_SPECIFIC_INPUT];
            unset($input[self::PRODUCT_SPECIFIC_INPUT]);
        }

        $key = $method->getPayoneMethodType().'SpecificInput';
        return ['body' => ['checkout' => ['orderRequest' => ['paymentMethodSpecificInput' => [$key => $input]]]]];
    }
}
