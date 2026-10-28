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

namespace Payone\CommercePlatform\Model\Api;

class Response
{
    /**
     * @var \Payone\CommercePlatform\Helper\Json
     */
    protected $jsonHelper;

    /**
     * @var string
     */
    protected $responseRaw;

    /**
     * @var array|null
     */
    protected $response;

    /**
     * @var int|null
     */
    protected $httpStatus;

    /**
     * @param \Payone\CommercePlatform\Helper\Json $jsonHelper
     * @param string $responseRaw
     * @param int|null $httpStatus
     */
    public function __construct(
        \Payone\CommercePlatform\Helper\Json $jsonHelper,
        $responseRaw = null,
        $httpStatus = null
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->httpStatus = $httpStatus !== null ? (int)$httpStatus : null;
        if (!empty($responseRaw)) {
            $this->setResponseRaw($responseRaw);
        }
    }

    /**
     * Returns the HTTP status code of the response
     *
     * @return int|null
     */
    public function getHttpStatus()
    {
        return $this->httpStatus;
    }

    /**
     * Checks if the HTTP status is 2xx (or unknown)
     *
     * @return bool
     */
    public function isHttpSuccess()
    {
        return $this->httpStatus === null || ($this->httpStatus >= 200 && $this->httpStatus < 300);
    }

    /**
     * Returns the raw response body
     *
     * @return string
     */
    public function getResponseRaw()
    {
        return $this->responseRaw;
    }

    /**
     * Sets the raw response body
     *
     * @param string $responseRaw
     * @return void
     */
    public function setResponseRaw($responseRaw)
    {
        $this->responseRaw = $responseRaw;
        $this->response = $this->jsonHelper->jsonDecode($this->getResponseRaw());
    }

    /**
     * Returns the decoded response body
     *
     * @return array|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Sets the decoded response body
     *
     * @param array|null $response
     * @return void
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * Checks if the API call was successful
     *
     * @return bool
     */
    public function isSuccessResponse()
    {
        $response = $this->getResponse();
        if ($this->isHttpSuccess() === false || empty($response) || !is_array($response) || !empty($this->getErrorsFromResponse())) {
            return false;
        }
        return true;
    }

    /**
     * Returns the errors of an unsuccessful response
     *
     * @return array|null
     */
    public function getErrorsFromResponse()
    {
        $response = $this->getResponse();
        if (!empty($response['errors'])) {
            return $response['errors'];
        }

        if (!empty($response['message']) && count($response) === 1) {
            return [$response['message']];
        }

        return null;
    }

    /**
     * Returns the message of the first error of the response
     *
     * @return string|null
     */
    public function getFirstErrorMessage()
    {
        $errors = $this->getErrorsFromResponse();
        if (empty($errors) || !is_array($errors)) {
            return "An unknown error occurred.";
        }

        $firstError = current($errors);
        if (is_string($firstError)) {
            return $firstError;
        }
        if (!empty($firstError['message'])) {
            return $firstError['message'];
        }
        return "An unknown error occurred.";
    }

    /**
     * Searches the response recursively for the given key
     *
     * @param string $searchField
     * @return string|null
     */
    public function findData($searchField)
    {
        return $this->jsonHelper->findValueByKey($this->getResponse(), $searchField);
    }
}
