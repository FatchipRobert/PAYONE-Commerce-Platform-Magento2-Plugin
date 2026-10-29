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

namespace Payone\CommercePlatform\Model\Webhook;

use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;

/**
 * Validates the HMAC signature of incoming webhooks
 * Signature = base64(HMAC-SHA256(raw request body, API secret)), the API key is sent as key id
 */
class SignatureValidator
{
    public const HEADER_KEY_ID = 'X-GCS-KeyId';
    public const HEADER_SIGNATURE = 'X-GCS-Signature';

    /**
     * @param GeneralConfig $generalConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly GeneralConfig $generalConfig,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * Returns the API secret belonging to the given API key
     *
     * All store views are checked, since the webhook request has no store context
     *
     * @param string $keyId
     * @return string|null
     */
    private function getSecretByKeyId(string $keyId): ?string
    {
        foreach ($this->storeManager->getStores(true) as $store) {
            $apiKey = $this->generalConfig->getApiKey($store->getCode());
            if ($apiKey !== '' && hash_equals($apiKey, $keyId)) {
                $secret = $this->generalConfig->getApiSecret($store->getCode());
                return $secret !== '' ? $secret : null;
            }
        }
        return null;
    }

    /**
     * Calculates the expected signature for the given body
     *
     * @param string $rawBody
     * @param string $secret
     * @return string
     */
    public function calculateSignature(string $rawBody, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
    }

    /**
     * Checks if the webhook signature is valid
     *
     * @param RequestInterface $request
     * @param string $rawBody
     * @return bool
     */
    public function isValid(RequestInterface $request, string $rawBody): bool
    {
        $keyId = trim((string)$request->getHeader(self::HEADER_KEY_ID));
        $signature = trim((string)$request->getHeader(self::HEADER_SIGNATURE));
        if ($keyId === '' || $signature === '') {
            return false;
        }

        $secret = $this->getSecretByKeyId($keyId);
        if ($secret === null) {
            return false;
        }

        return hash_equals($this->calculateSignature($rawBody, $secret), $signature);
    }
}
