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
use Magento\Sales\Model\Order;
use Payone\CommercePlatform\Model\Api\Response;

abstract class BaseRequest
{
    public const API_URL_PRODUCTION = 'https://api.commerce.payone.com';

    public const API_URL_PRE_PRODUCTION = 'https://api.preprod.commerce.payone.com';

    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_PATCH = 'PATCH';

    public const DATE_RFC2616 = 'D, d M Y H:i:s T';

    public const RESPONSE_SUCCESS = 'SUCCESS';
    public const RESPONSE_ERROR = 'ERROR';

    public const REFERENCE_PREFIX_COMMERCECASE = 'cc';
    public const REFERENCE_PREFIX_CHECKOUT = 'ck';
    public const REFERENCE_PREFIX_ORDER = 'od';
    public const REFERENCE_PREFIX_CAPTURE = 'cp';
    public const REFERENCE_PREFIX_REFUND = 'rf';

    /**
     * @var string|null
     */
    protected $endpoint = null;

    /**
     * @var string|null
     */
    protected $httpMethod = null;

    /**
     * @var \Payone\CommercePlatform\Model\Api\Curl
     */
    protected $curl;

    /**
     * @var \Payone\CommercePlatform\Gateway\Config\GeneralConfig
     */
    protected $generalConfig;

    /**
     * @var \Payone\CommercePlatform\Helper\Customer
     */
    protected $customerHelper;

    /**
     * @var \Payone\CommercePlatform\Helper\Api
     */
    protected $apiHelper;

    /**
     * @var \Payone\CommercePlatform\Model\ResourceModel\ApiLog
     */
    protected $apiLog;

    /**
     * @var \Payone\CommercePlatform\Model\Api\ResponseFactory
     */
    protected $responseFactory;

    /**
     * @var int
     */
    protected $storeId = null;

    /**
     * @var Order
     */
    protected $order = null;

    /**
     * @var array
     */
    protected $parameters = [];

    /**
     * @var array
     */
    protected $endpointParameters = [];

    /**
     * @var string|null
     */
    protected $requestDate = null;

    /**
     * @var bool
     */
    protected $logRequest = true;

    /**
     * @var \Payone\CommercePlatform\Model\Country
     */
    protected $country;

    /**
     * @param \Payone\CommercePlatform\Model\Api\Curl $curl
     * @param \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
     * @param \Payone\CommercePlatform\Helper\Customer $customerHelper
     * @param \Payone\CommercePlatform\Helper\Api $apiHelper
     * @param \Payone\CommercePlatform\Model\ResourceModel\ApiLog $apiLog
     * @param \Payone\CommercePlatform\Model\Api\ResponseFactory $responseFactory
     * @param \Payone\CommercePlatform\Model\Country $country
     */
    public function __construct(
        \Payone\CommercePlatform\Model\Api\Curl $curl,
        \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig,
        \Payone\CommercePlatform\Helper\Customer $customerHelper,
        \Payone\CommercePlatform\Helper\Api $apiHelper,
        \Payone\CommercePlatform\Model\ResourceModel\ApiLog $apiLog,
        \Payone\CommercePlatform\Model\Api\ResponseFactory $responseFactory,
        \Payone\CommercePlatform\Model\Country $country
    ) {
        $this->curl = $curl;
        $this->generalConfig = $generalConfig;
        $this->customerHelper = $customerHelper;
        $this->apiHelper = $apiHelper;
        $this->apiLog = $apiLog;
        $this->responseFactory = $responseFactory;
        $this->country = $country;
    }

    /**
     * Set current store code and reinit base parameters
     *
     * @param  int $storeId
     * @return void
     */
    protected function setStoreId($storeId)
    {
        $this->storeId = $storeId;
    }

    /**
     * Returns the store id whose config is used for this request
     *
     * @return int|null
     */
    public function getStoreId()
    {
        return $this->storeId;
    }

    /**
     * Sets the order of this request and uses its store for the config
     *
     * @param Order $order
     * @return void
     */
    protected function setOrder(Order $order)
    {
        $this->order = $order;
        $this->setStoreId($order->getStore()->getId());
    }

    /**
     * Returns the order of this request
     *
     * @return Order|null
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Sets the request payload
     *
     * @param array $parameters
     * @return void
     */
    protected function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Returns the request payload
     *
     * @return array
     */
    protected function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Sets the values for the placeholders in the endpoint
     *
     * @param array $endpointParameters
     * @return void
     */
    protected function setEndpointParameters(array $endpointParameters)
    {
        $this->endpointParameters = $endpointParameters;
    }

    /**
     * Returns the values for the endpoint placeholders, including the merchant id
     *
     * @return array
     */
    protected function getEndpointParameters()
    {
        $baseParams = [
            'merchantId' => $this->generalConfig->getMerchantId($this->storeId),
        ];

        return array_merge($baseParams, $this->endpointParameters);
    }

    /**
     * Returns the endpoint with placeholders
     *
     * @return string|null
     */
    protected function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Sets the HTTP method of the request
     *
     * @param string $httpMethod
     * @return void
     */
    protected function setHttpMethod($httpMethod)
    {
        $this->httpMethod = $httpMethod;
    }

    /**
     * Returns the HTTP method of the request
     *
     * @return string|null
     */
    protected function getHttpMethod()
    {
        return $this->httpMethod;
    }

    /**
     * Returns the request date in RFC 2616 format - generated once, as it is part of the signature
     *
     * @return string
     */
    protected function getRfc161Date()
    {
        if ($this->requestDate === null) {
            $this->requestDate = gmdate(self::DATE_RFC2616);
        }
        return $this->requestDate;
    }

    /**
     * Returns the string to sign for the GCS v1HMAC authorization header
     *
     * @return string
     */
    protected function getSignData()
    {
        $signData = $this->getHttpMethod()."\n";
        if ($this->isContentTypeHeaderNeeded() === true) {
            $signData .= $this->getContentTypeValue();
        }
        $signData .= "\n";
        $signData .= $this->getRfc161Date()."\n";
        $signData .= $this->getPopulatedEndpoint()."\n";

        return $signData;
    }

    /**
     * Returns the GCS v1HMAC authorization header value
     *
     * @return string
     */
    protected function getAuthorizationHeader()
    {
        return
            'GCS v1HMAC:'.$this->generalConfig->getApiKey($this->storeId).':'.
            base64_encode(
                hash_hmac('sha256', $this->getSignData(), $this->generalConfig->getApiSecret($this->storeId), true)
            );
    }

    /**
     * Returns the endpoint with all placeholders replaced
     *
     * @return string
     */
    protected function getPopulatedEndpoint()
    {
        $endpoint = $this->getEndpoint();
        $endpointParameters = $this->getEndpointParameters();

        foreach ($endpointParameters as $key => $value) {
            $endpoint = str_replace('{'.$key.'}', $value, $endpoint);
        }

        return $endpoint;
    }

    /**
     * Returns the merchant reference of the order for the given request type
     *
     * @param InfoInterface $payment
     * @param string        $typePrefix one of the REFERENCE_PREFIX_* constants
     * @return string
     */
    protected function getMerchantReference(InfoInterface $payment, $typePrefix)
    {
        return $typePrefix.$this->generalConfig->getRefPrefix($this->storeId).$payment->getOrder()->getIncrementId();
    }

    /**
     * Checks if the live API has to be used
     *
     * @return bool
     */
    protected function isProductionCall()
    {
        if ($this->generalConfig->isLiveMode($this->storeId) === true) {
            return true;
        }
        return false;
    }

    /**
     * Returns the full request URL for the live or the sandbox API
     *
     * @return string
     */
    protected function getRequestUrl()
    {
        $url = self::API_URL_PRODUCTION;
        if ($this->isProductionCall() === false) {
            $url = self::API_URL_PRE_PRODUCTION;
        }

        $url .= $this->getPopulatedEndpoint();

        return $url;
    }

    /**
     * Checks if the request needs a Content-Type header (requests with a body)
     *
     * @return bool
     */
    protected function isContentTypeHeaderNeeded()
    {
        if (in_array($this->getHttpMethod(), [self::METHOD_POST, self::METHOD_PUT,self::METHOD_PATCH , self::METHOD_DELETE])) {
            return true;
        }
        return false;
    }

    /**
     * Returns the value of the Content-Type header
     *
     * @return string
     */
    protected function getContentTypeValue()
    {
        return 'application/json; charset=utf-8';
    }

    /**
     * Sets cURL security options
     *
     * @return void
     */
    protected function setCurlSecurityOptions()
    {
        $this->curl->setOption(CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, true);
        $this->curl->setOption(CURLOPT_SSL_VERIFYHOST, 2);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 30);
        $this->curl->setOption(CURLOPT_TIMEOUT, 30);
        $this->curl->setOption(CURLOPT_MAXREDIRS, 0);
    }

    /**
     * Adds the Content-Type, Date and Authorization headers to the curl request
     *
     * @return void
     */
    protected function addHeaders()
    {
        if ($this->isContentTypeHeaderNeeded() === true) {
            $this->curl->addHeader('Content-Type', $this->getContentTypeValue());
        }
        $this->curl->addHeader('Date', $this->getRfc161Date());
        $this->curl->addHeader('Authorization', $this->getAuthorizationHeader());
    }

    /**
     * Writes the API call to the payone_cp_protocol_api table
     *
     * @param  string|false  $requestRaw
     * @param  string        $responseStatus
     * @param  string|null   $responseRaw
     * @param  Response|null $responseObject
     * @return void
     */
    protected function handleLogging($requestRaw, $responseStatus, $responseRaw, $responseObject = null)
    {
        $this->apiLog->addApiLogEntry($this, $requestRaw, $responseStatus, $responseRaw, $responseObject);
    }

    /**
     * Creates the response object from the raw response
     *
     * @param string $responseBody
     * @param int|null $httpStatus
     * @return Response
     */
    protected function getResponseObjectFromResponseBody($responseBody, $httpStatus = null)
    {
        return $this->responseFactory->create([
            'responseRaw' => $responseBody,
            'httpStatus' => $httpStatus,
        ]);
    }

    /**
     * Sends the request to the PAYONE API and logs it
     *
     * @param array $endpointParameters
     * @return Response
     * @throws \Exception
     */
    public function sendRequest($endpointParameters = [])
    {
        $response = null; // Initial default value
        $responseStatus = self::RESPONSE_ERROR; // Initial default value

        if (!empty($endpointParameters)) {
            $this->setEndpointParameters($endpointParameters);
        }

        $this->setCurlSecurityOptions();
        $this->addHeaders();

        try {
            $request = json_encode($this->getParameters());

            $this->curl->sendRequest($this->getHttpMethod(), $this->getRequestUrl(), $request);

            $responseBody = $this->curl->getBody();
            $httpStatus = $this->curl->getStatus();

            $response = $this->getResponseObjectFromResponseBody($responseBody, $httpStatus);

            if ($response->isSuccessResponse() === true) {
                $responseStatus = self::RESPONSE_SUCCESS;
            }
        } catch (\Exception $e) {
            $responseBody = "Exception caught: ".$e->getMessage();
            throw $e;
        } finally {
            if ($this->logRequest === true) {
                $this->handleLogging($request, $responseStatus, $responseBody, $response);
            }
        }

        return $response;
    }
}
