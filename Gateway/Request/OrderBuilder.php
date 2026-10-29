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
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Model\RemoteIp;

/**
 * "checkout.orderRequest" of POST /commerce-cases: order references, channel and customer device
 *
 * The payment method specific input is added by PaymentMethodSpecificBuilder.
 */
class OrderBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param MerchantReference $merchantReference
     * @param GeneralConfig $generalConfig
     * @param RemoteIp $remoteIp
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly MerchantReference $merchantReference,
        private readonly GeneralConfig $generalConfig,
        private readonly RemoteIp $remoteIp,
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
        $order = $this->subjectReader->readPayment($buildSubject)->getPayment()->getOrder();

        $orderRequest = [
            'orderType' => 'FULL',
            'orderReferences' => [
                'merchantReference' => $this->merchantReference->get($order, MerchantReference::PREFIX_ORDER),
            ],
            'paymentMethodSpecificInput' => [
                'paymentChannel' => 'ECOMMERCE',
            ],
        ];

        if ($this->generalConfig->isTransmitIp($order->getStoreId())) {
            $orderRequest['paymentMethodSpecificInput']['customerDevice']['ipAddress'] = $this->remoteIp->get();
        }

        return ['body' => ['checkout' => ['orderRequest' => $orderRequest]]];
    }
}
