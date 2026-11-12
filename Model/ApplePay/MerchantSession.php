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

namespace Payone\CommercePlatform\Model\ApplePay;

use Laminas\Uri\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payone\CommercePlatform\Gateway\Config\ApplePayConfig;
use Psr\Log\LoggerInterface;

/**
 * Merchant validation of Apple Pay on the web: requests the merchant session object for the ApplePaySession of the
 * checkout from Apple, authenticated with the Apple Pay Merchant Identity Certificate of the shop
 *
 * PAYONE only decrypts the payment token (Payment Processing Certificate), the merchant validation is done by the shop.
 */
class MerchantSession
{
    /**
     * Hosts of the Apple Pay servers the validation URL of the ApplePaySession may point to
     * (https://developer.apple.com/documentation/applepayontheweb/setting-up-your-server)
     */
    private const VALIDATION_HOST_PATTERN = '/^(cn-)?apple-pay-gateway(-[a-z0-9-]+)?\.apple\.com$/';

    private const TIMEOUT = 20;

    /**
     * @param CurlFactory $curlFactory
     * @param StoreManagerInterface $storeManager
     * @param ApplePayConfig $applePayConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ApplePayConfig $applePayConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Requests the merchant session from the validation URL of the ApplePaySession
     *
     * @param string $validationUrl
     * @param int $storeId
     * @return string merchant session (JSON) for ApplePaySession.completeMerchantValidation()
     * @throws LocalizedException
     */
    public function create(string $validationUrl, int $storeId): string
    {
        if (!$this->isValidationUrlAllowed($validationUrl)) {
            throw new LocalizedException(__('Invalid Apple Pay validation URL.'));
        }
        if (!$this->applePayConfig->isConfigurationComplete($storeId)) {
            throw new LocalizedException(__('Apple Pay is not configured completely.'));
        }

        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::TIMEOUT);
        $curl->addHeader('Content-Type', 'application/json');
        $curl->setOption(CURLOPT_SSLCERT_BLOB, $this->applePayConfig->getCertificate($storeId));
        $curl->setOption(CURLOPT_SSLCERTTYPE, 'PEM');
        $curl->setOption(CURLOPT_SSLKEY_BLOB, $this->applePayConfig->getPrivateKey($storeId));
        $curl->setOption(CURLOPT_SSLKEYTYPE, 'PEM');
        $keyPassword = $this->applePayConfig->getPrivateKeyPassword($storeId);
        if ($keyPassword !== '') {
            $curl->setOption(CURLOPT_KEYPASSWD, $keyPassword);
        }

        try {
            $curl->post($validationUrl, (string)json_encode([
                'merchantIdentifier' => $this->applePayConfig->getMerchantIdentifier($storeId),
                'displayName' => $this->applePayConfig->getDisplayName($storeId),
                'initiative' => 'web',
                'initiativeContext' => $this->getShopDomain($storeId),
            ]));
        } catch (\Exception $e) {
            $this->logger->error('PAYONE CP Apple Pay merchant validation failed: '.$e->getMessage());
            throw new LocalizedException(__('Apple Pay is currently not available.'));
        }

        $body = $curl->getBody();
        $session = json_decode($body, true);
        if ($curl->getStatus() !== 200 || !is_array($session) || !isset($session['merchantSessionIdentifier'])) {
            $this->logger->error(sprintf(
                'PAYONE CP Apple Pay merchant validation failed: HTTP %d %s',
                $curl->getStatus(),
                $body
            ));
            throw new LocalizedException(__('Apple Pay is currently not available.'));
        }
        return $body;
    }

    /**
     * Checks that the validation URL points to an Apple Pay server (the certificate must not be sent anywhere else)
     *
     * @param string $validationUrl
     * @return bool
     */
    private function isValidationUrlAllowed(string $validationUrl): bool
    {
        $uri = new Http($validationUrl);
        return $uri->isValid()
            && $uri->getScheme() === 'https'
            && in_array($uri->getPort(), [null, 443], true)
            && $uri->getUserInfo() === null
            && preg_match(self::VALIDATION_HOST_PATTERN, strtolower((string)$uri->getHost())) === 1;
    }

    /**
     * Returns the domain of the store, which has to be registered as merchant domain at Apple
     *
     * @param int $storeId
     * @return string
     */
    private function getShopDomain(int $storeId): string
    {
        $baseUrl = $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        return (string)(new Http($baseUrl))->getHost();
    }
}
