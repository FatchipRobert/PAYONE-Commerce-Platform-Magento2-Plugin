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

class OneStep extends BaseRequest
{
    /**
     * @var string
     */
    protected $endpoint = '/v1/{merchantId}/commerce-cases';

    /**
     * @var string
     */
    protected $httpMethod = self::METHOD_POST;

    /**
     * @var \Payone\CommercePlatform\Model\Api\Request\CommerceCaseFactory
     */
    protected $commerceCaseRequestFactory;

    /**
     * @var \Payone\CommercePlatform\Model\Api\Request\CheckoutFactory
     */
    protected $checkoutRequestFactory;

    /**
     * @var \Payone\CommercePlatform\Model\Api\Request\OrderFactory
     */
    protected $orderRequestFactory;

    /**
     * @param \Payone\CommercePlatform\Model\Api\Curl $curl
     * @param \Payone\CommercePlatform\Helper\Shop $shopHelper
     * @param \Payone\CommercePlatform\Helper\Customer $customerHelper
     * @param \Payone\CommercePlatform\Helper\Api $apiHelper
     * @param \Payone\CommercePlatform\Model\ResourceModel\ApiLog $apiLog
     * @param \Payone\CommercePlatform\Model\Api\ResponseFactory $responseFactory
     * @param \Payone\CommercePlatform\Model\Api\Request\CommerceCaseFactory $commerceCaseRequestFactory
     * @param \Payone\CommercePlatform\Model\Api\Request\CheckoutFactory $checkoutRequestFactory
     * @param \Payone\CommercePlatform\Model\Api\Request\OrderFactory $orderRequestFactory
     */
    public function __construct(
        \Payone\CommercePlatform\Model\Api\Curl                        $curl,
        \Payone\CommercePlatform\Helper\Shop                           $shopHelper,
        \Payone\CommercePlatform\Helper\Customer                       $customerHelper,
        \Payone\CommercePlatform\Helper\Api                            $apiHelper,
        \Payone\CommercePlatform\Model\ResourceModel\ApiLog            $apiLog,
        \Payone\CommercePlatform\Model\Api\ResponseFactory             $responseFactory,
        \Payone\CommercePlatform\Model\Api\Request\CommerceCaseFactory $commerceCaseRequestFactory,
        \Payone\CommercePlatform\Model\Api\Request\CheckoutFactory     $checkoutRequestFactory,
        \Payone\CommercePlatform\Model\Api\Request\OrderFactory        $orderRequestFactory,
    ) {
        parent::__construct($curl, $shopHelper, $customerHelper, $apiHelper, $apiLog, $responseFactory);
        $this->commerceCaseRequestFactory = $commerceCaseRequestFactory;
        $this->checkoutRequestFactory = $checkoutRequestFactory;
        $this->orderRequestFactory = $orderRequestFactory;
    }

    /**
     * Builds the combined payload of commerce case, checkout and order
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return array
     */
    public function populateParameters(InfoInterface $payment, $amount)
    {
        $this->setOrder($payment->getOrder());

        $commerceCaseRequest = $this->commerceCaseRequestFactory->create();
        $checkoutRequest = $this->checkoutRequestFactory->create();
        $orderRequest = $this->orderRequestFactory->create();

        $parameters = $commerceCaseRequest->populateParameters($payment);
        $parameters['checkout'] = $checkoutRequest->populateParameters($payment, $amount);
        $parameters['checkout']['autoExecuteOrder'] = true;
        $parameters['checkout']['orderRequest'] = $orderRequest->populateParameters($payment, $amount);

        $this->setParameters($parameters);

        return $parameters;
    }
}
