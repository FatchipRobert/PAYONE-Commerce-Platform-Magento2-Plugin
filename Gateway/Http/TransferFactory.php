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
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;

/**
 * Creates the transfer object for one API endpoint - configured per command in etc/di.xml
 *
 * Expected result of the request builder:
 * [
 *     'uri' => ['commerceCaseId' => ..., ...],    values for the {placeholders} of the endpoint, {merchantId} is added here
 *     'body' => [...],                            payload, sent as JSON
 *     'store_id' => ...,                          store whose credentials and environment are used (null = current store)
 *     'order_increment_id' => ...,                optional, for the API log
 *     'payment_method' => ...,                    optional, for the API log
 * ]
 */
class TransferFactory implements TransferFactoryInterface
{
    public const API_URL_PRODUCTION = 'https://api.commerce.payone.com';
    public const API_URL_PRE_PRODUCTION = 'https://api.preprod.commerce.payone.com';

    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_PATCH = 'PATCH';

    private const CONTENT_TYPE = 'application/json; charset=utf-8';

    /**
     * @param TransferBuilder $transferBuilder
     * @param GeneralConfig $generalConfig
     * @param Json $json
     * @param string $endpoint path with {placeholders}, e.g. /v1/{merchantId}/commerce-cases
     * @param string $httpMethod
     * @param string $requestName name of the request in the API log
     */
    public function __construct(
        private readonly TransferBuilder $transferBuilder,
        private readonly GeneralConfig $generalConfig,
        private readonly Json $json,
        private readonly string $endpoint,
        private readonly string $httpMethod,
        private readonly string $requestName,
    ) {
    }

    /**
     * Builds the transfer object from the request builder result
     *
     * @param array $request
     * @return TransferInterface
     */
    public function create(array $request): TransferInterface
    {
        $storeId = $request['store_id'] ?? null;
        $path = $this->getPath($request['uri'] ?? [], $storeId);

        $headers = [];
        if ($this->hasBody()) {
            $headers['Content-Type'] = self::CONTENT_TYPE;
        }

        return $this->transferBuilder
            ->setClientConfig([
                'store_id' => $storeId,
                'path' => $path,
                'request_name' => $this->requestName,
                'order_increment_id' => $request['order_increment_id'] ?? null,
                'payment_method' => $request['payment_method'] ?? null,
            ])
            ->setMethod($this->httpMethod)
            ->setUri($this->getBaseUrl($storeId).$path)
            ->setHeaders($headers)
            ->setBody($this->json->serialize($request['body'] ?? []))
            ->shouldEncode(false)
            ->build();
    }

    /**
     * Returns the endpoint with all placeholders replaced
     *
     * @param array $uriParameters
     * @param int|string|null $storeId
     * @return string
     */
    private function getPath(array $uriParameters, int|string|null $storeId): string
    {
        $uriParameters = array_merge(['merchantId' => $this->generalConfig->getMerchantId($storeId)], $uriParameters);

        $path = $this->endpoint;
        foreach ($uriParameters as $key => $value) {
            $path = str_replace('{'.$key.'}', (string)$value, $path);
        }
        return $path;
    }

    /**
     * Returns the URL of the live or the pre-production API
     *
     * @param int|string|null $storeId
     * @return string
     */
    private function getBaseUrl(int|string|null $storeId): string
    {
        return $this->generalConfig->isLiveMode($storeId) ? self::API_URL_PRODUCTION : self::API_URL_PRE_PRODUCTION;
    }

    /**
     * Whether the request has a body and therefore a Content-Type header (also part of the signature)
     *
     * @return bool
     */
    private function hasBody(): bool
    {
        return in_array(
            $this->httpMethod,
            [self::METHOD_POST, self::METHOD_PUT, self::METHOD_PATCH, self::METHOD_DELETE],
            true
        );
    }
}
