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

namespace Payone\CommercePlatform\Gateway\Http;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payone\CommercePlatform\Gateway\Validator\ResponseValidator;
use Payone\CommercePlatform\Model\Api\Curl;
use Payone\CommercePlatform\Model\Api\CurlFactory;
use Payone\CommercePlatform\Model\ResourceModel\ApiLog;

/**
 * Sends a signed request to the PAYONE API and writes it to the API log
 *
 * Returns ['status' => HTTP status, 'body' => decoded JSON object or null, 'raw' => raw response body]
 */
class Client implements ClientInterface
{
    /**
     * @param CurlFactory $curlFactory
     * @param Signer $signer
     * @param ApiLog $apiLog
     * @param ResponseValidator $responseValidator
     * @param Json $json
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Signer $signer,
        private readonly ApiLog $apiLog,
        private readonly ResponseValidator $responseValidator,
        private readonly Json $json,
    ) {
    }

    /**
     * Sends the request
     *
     * @param TransferInterface $transferObject
     * @return array
     * @throws \Exception curl errors, after they were logged
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $config = $transferObject->getClientConfig();
        $requestRaw = (string)$transferObject->getBody();
        $response = ['status' => 0, 'body' => null, 'raw' => null];

        try {
            $curl = $this->curlFactory->create();
            $this->setSecurityOptions($curl);
            $curl->setHeaders($this->getSignedHeaders($transferObject));
            $curl->sendRequest($transferObject->getMethod(), $transferObject->getUri(), $requestRaw);

            $raw = (string)$curl->getBody();
            $response = [
                'status' => (int)$curl->getStatus(),
                'body' => $this->decode($raw),
                'raw' => $raw,
            ];
        } catch (\Exception $e) {
            $response['raw'] = 'Exception caught: '.$e->getMessage();
            throw $e;
        } finally {
            $this->apiLog->addApiLogEntry(
                $config['store_id'] ?? null,
                (string)($config['request_name'] ?? ''),
                $config['order_increment_id'] ?? null,
                $config['payment_method'] ?? null,
                $requestRaw,
                $this->responseValidator->isSuccessful($response) ? ApiLog::STATUS_SUCCESS : ApiLog::STATUS_ERROR,
                $response['raw'],
                $response['body']
            );
        }

        return $response;
    }

    /**
     * Returns the headers of the transfer plus Date and Authorization
     *
     * Signed right before sending, so that the Date header is current.
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    private function getSignedHeaders(TransferInterface $transferObject): array
    {
        $config = $transferObject->getClientConfig();
        $headers = $transferObject->getHeaders();

        $headers['Date'] = $this->signer->getDate();
        $headers['Authorization'] = $this->signer->getAuthorizationHeader(
            $transferObject->getMethod(),
            (string)($headers['Content-Type'] ?? ''),
            $headers['Date'],
            (string)$config['path'],
            $config['store_id'] ?? null
        );
        return $headers;
    }

    /**
     * Sets cURL security options
     *
     * @param Curl $curl
     * @return void
     */
    private function setSecurityOptions(Curl $curl): void
    {
        $curl->setOption(CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        $curl->setOption(CURLOPT_SSL_VERIFYPEER, true);
        $curl->setOption(CURLOPT_SSL_VERIFYHOST, 2);
        $curl->setOption(CURLOPT_CONNECTTIMEOUT, 30);
        $curl->setOption(CURLOPT_TIMEOUT, 30);
        $curl->setOption(CURLOPT_MAXREDIRS, 0);
    }

    /**
     * Decodes a JSON object, null for empty, invalid or non-object JSON (e.g. HTML error pages)
     *
     * @param string $raw
     * @return array|null
     */
    private function decode(string $raw): ?array
    {
        if (trim($raw) === '') {
            return null;
        }

        try {
            $decoded = $this->json->unserialize(trim($raw));
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
