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
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * "checkout.orderRequest.paymentMethodSpecificInput.<type>SpecificInput" of POST /commerce-cases
 *
 * Delegates the content to the builder of the payment method (di.xml argument "builders": method code => builder)
 * and wraps it:
 * - key from the config value payone_method_type, e.g. "redirectPaymentMethod" → "redirectPaymentMethodSpecificInput"
 * - paymentProductId from the config value payone_product_id, unless the method builder sets it (credit card type)
 * - the method builder's "productSpecificInput" becomes "paymentProduct<id>SpecificInput"
 */
class PaymentMethodSpecificBuilder implements BuilderInterface
{
    private const PRODUCT_SPECIFIC_INPUT = 'productSpecificInput';

    /**
     * @param SubjectReader $subjectReader
     * @param BuilderInterface[] $builders payment method code => builder of the method specific content
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly array $builders = [],
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
        $payment = $this->subjectReader->readPayment($buildSubject)->getPayment();
        $method = $payment->getMethodInstance();
        $storeId = (int)$payment->getOrder()->getStoreId();

        $builder = $this->builders[$method->getCode()] ?? null;
        if (!$builder instanceof BuilderInterface) {
            throw new \LogicException('No PaymentMethodSpecificBuilder configured for '.$method->getCode());
        }

        $input = $builder->build($buildSubject);
        $input['paymentProductId'] ??= (int)$method->getConfigData('payone_product_id', $storeId);

        if (isset($input[self::PRODUCT_SPECIFIC_INPUT])) {
            $input['paymentProduct'.$input['paymentProductId'].'SpecificInput'] = $input[self::PRODUCT_SPECIFIC_INPUT];
            unset($input[self::PRODUCT_SPECIFIC_INPUT]);
        }

        $key = $method->getConfigData('payone_method_type', $storeId).'SpecificInput';
        return ['body' => ['checkout' => ['orderRequest' => ['paymentMethodSpecificInput' => [$key => $input]]]]];
    }
}
