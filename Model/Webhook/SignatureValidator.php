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

namespace Payone\CommercePlatform\Model\Webhook;

/**
 * Validates the HMAC signature of incoming webhooks
 * Signature = base64(HMAC-SHA256(raw request body, API secret)), the API key is sent as key id
 */
class SignatureValidator
{
    public const HEADER_KEY_ID = 'X-GCS-KeyId';
    public const HEADER_SIGNATURE = 'X-GCS-Signature';

    /**
     * @var \Payone\CommercePlatform\Helper\Shop
     */
    protected $shopHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Constructor
     *
     * @param \Payone\CommercePlatform\Helper\Shop       $shopHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Payone\CommercePlatform\Helper\Shop $shopHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->shopHelper = $shopHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * Returns the API secret belonging to the given API key
     *
     * All store views are checked, since the webhook request has no store context
     *
     * @param  string $keyId
     * @return string|null
     */
    protected function getSecretByKeyId($keyId)
    {
        foreach ($this->storeManager->getStores(true) as $store) {
            $apiKey = (string)$this->shopHelper->getConfigParam('api_key', 'global', 'payone_cp_general', $store->getCode());
            if ($apiKey !== '' && hash_equals($apiKey, $keyId)) {
                $secret = (string)$this->shopHelper->getConfigParam('api_secret', 'global', 'payone_cp_general', $store->getCode());
                return $secret !== '' ? $secret : null;
            }
        }
        return null;
    }

    /**
     * Calculates the expected signature for the given body
     *
     * @param  string $rawBody
     * @param  string $secret
     * @return string
     */
    public function calculateSignature($rawBody, $secret)
    {
        return base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
    }

    /**
     * Checks if the webhook signature is valid
     *
     * @param  \Magento\Framework\App\RequestInterface $request
     * @param  string                                  $rawBody
     * @return bool
     */
    public function isValid(\Magento\Framework\App\RequestInterface $request, $rawBody)
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
