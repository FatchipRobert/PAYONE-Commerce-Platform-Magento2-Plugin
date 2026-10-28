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
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $json;

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
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     * @param string $responseRaw
     * @param int|null $httpStatus
     */
    public function __construct(
        \Magento\Framework\Serialize\Serializer\Json $json,
        $responseRaw = null,
        $httpStatus = null
    ) {
        $this->json = $json;
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
        $this->response = $this->decodeJson($this->getResponseRaw());
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

        // A failed payment inside a successful call (e.g. HTTP 201 for POST /commerce-cases) is only reported in a nested errorResponse
        $errorResponse = $this->findData('errorResponse');
        if (is_array($errorResponse) && !empty($errorResponse['errors'])) {
            return $errorResponse['errors'];
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
        return $this->findValueByKey($this->getResponse(), $searchField);
    }

    /**
     * Decodes a JSON object, null for empty, invalid or non-object JSON (e.g. HTML error pages)
     *
     * @param mixed $json
     * @return array|null
     */
    private function decodeJson($json)
    {
        if (!is_string($json) || trim($json) === '') {
            return null;
        }

        try {
            $decoded = $this->json->unserialize(trim($json));
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Searches the array recursively for the given key and returns the first value found
     *
     * @param mixed $data
     * @param string $searchField
     * @return mixed
     */
    private function findValueByKey($data, $searchField)
    {
        if (!is_array($data)) {
            return null;
        }

        foreach ($data as $key => $value) {
            if ($key === $searchField) {
                return $value;
            }

            if (is_array($value)) {
                $result = $this->findValueByKey($value, $searchField);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }
}
