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

namespace Payone\CommercePlatform\Model\Api\Request;

use Magento\Payment\Model\InfoInterface;

class Order extends BaseRequest
{
    /**
     * @var string
     */
    protected $endpoint = '/v1/{merchantId}/commerce-cases/{commerceCaseId}/checkouts/{checkoutId}/order';

    /**
     * @var string
     */
    protected $httpMethod = self::METHOD_POST;

    /**
     * @var \Payone\CommercePlatform\Helper\Environment
     */
    protected $environmentHelper;

    /**
     * @param \Payone\CommercePlatform\Model\Api\Curl $curl
     * @param \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
     * @param \Payone\CommercePlatform\Helper\Customer $customerHelper
     * @param \Payone\CommercePlatform\Helper\Api $apiHelper
     * @param \Payone\CommercePlatform\Model\ResourceModel\ApiLog $apiLog
     * @param \Payone\CommercePlatform\Model\Api\ResponseFactory $responseFactory
     * @param \Payone\CommercePlatform\Helper\Environment $environmentHelper
     */
    public function __construct(
        \Payone\CommercePlatform\Model\Api\Curl $curl,
        \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig,
        \Payone\CommercePlatform\Helper\Customer $customerHelper,
        \Payone\CommercePlatform\Helper\Api $apiHelper,
        \Payone\CommercePlatform\Model\ResourceModel\ApiLog $apiLog,
        \Payone\CommercePlatform\Model\Api\ResponseFactory $responseFactory,
        \Payone\CommercePlatform\Helper\Environment $environmentHelper,
    ) {
        parent::__construct($curl, $generalConfig, $customerHelper, $apiHelper, $apiLog, $responseFactory);
        $this->environmentHelper = $environmentHelper;
    }

    /**
     * Builds the payload for the order of an existing checkout
     *
     * @param InfoInterface $payment
     * @param float         $amount
     * @param string|null   $commerceCaseId
     * @param string|null   $checkoutId
     * @return array
     */
    public function populateParameters(InfoInterface $payment, $amount, $commerceCaseId = null, $checkoutId = null)
    {
        if (!empty($commerceCaseId) && !empty($checkoutId)) {
            $this->setEndpointParameters([
                'commerceCaseId' => $commerceCaseId,
                'checkoutId' => $checkoutId,
            ]);
        }

        $order = $payment->getOrder();

        $this->setOrder($payment->getOrder());

        $methodInstance = $order->getPayment()->getMethodInstance();

        $parameters = [
            'orderType' => 'FULL',
            'orderReferences' => [
                'merchantReference' => $this->getMerchantReference($payment, self::REFERENCE_PREFIX_ORDER),
                #'descriptor' => 'TODO', // Abbuchungs-Text?
                #'merchantParameters' => json_encode(['foo' => 'bar']), // Add certain parameters for later use to connect to the order/payment?
            ],
            'paymentMethodSpecificInput' => [
                $methodInstance->getSpecificInputIdentifier() => $methodInstance->getPaymentSpecificParameters($order),
                'paymentChannel' => 'ECOMMERCE',
            ],
        ];
        $parameters['paymentMethodSpecificInput'][$methodInstance->getSpecificInputIdentifier()]['paymentProductId'] = $methodInstance->getPaymentProductId();

        if ($this->generalConfig->isTransmitIp($this->storeId) === true) {
            $parameters['paymentMethodSpecificInput']['customerDevice']['ipAddress'] = $this->environmentHelper->getRemoteIp();
        }
        #$parameters['paymentMethodSpecificInput']['customerDevice']['deviceToken'] = "TODO"; // Only needed for Payone BNPL payment methods?

        $this->setParameters($parameters);

        return $parameters;
    }
}
