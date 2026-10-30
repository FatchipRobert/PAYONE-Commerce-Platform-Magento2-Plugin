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

namespace Payone\CommercePlatform\Gateway\Config;

use Magento\Store\Model\StoreManagerInterface;
use Payone\CommercePlatform\Model\Config\EncryptedFile;
use Payone\CommercePlatform\Model\Payone;

/**
 * Apple Pay settings of section payone_cp_payment/payone_cp_applepay: Apple merchant identifier, Merchant Identity
 * Certificate with private key (uploaded files, stored encrypted - merchant validation), display name and card
 * networks of the payment sheet
 */
class ApplePayConfig
{
    private const DEFAULT_DISPLAY_NAME = 'Online Store';

    /* Apple allows at most 64 characters for the display name of the merchant session */
    private const DISPLAY_NAME_MAX_LENGTH = 64;

    /**
     * @param GeneralConfig $generalConfig
     * @param StoreManagerInterface $storeManager
     * @param EncryptedFile $encryptedFile
     */
    public function __construct(
        private readonly GeneralConfig $generalConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly EncryptedFile $encryptedFile,
    ) {
    }

    /**
     * Returns if merchant identifier, certificate and private key are configured
     *
     * @param int|string|null $store
     * @return bool
     */
    public function isConfigurationComplete(int|string|null $store = null): bool
    {
        return $this->getMerchantIdentifier($store) !== ''
            && $this->getCertificate($store) !== ''
            && $this->getPrivateKey($store) !== '';
    }

    /**
     * Returns the Apple Pay merchant identifier, e.g. merchant.com.example.shop
     *
     * @param int|string|null $store
     * @return string
     */
    public function getMerchantIdentifier(int|string|null $store = null): string
    {
        return trim((string)$this->getValue('merchant_identifier', $store));
    }

    /**
     * Returns the Merchant Identity Certificate (PEM, from the uploaded file)
     *
     * @param int|string|null $store
     * @return string
     */
    public function getCertificate(int|string|null $store = null): string
    {
        return $this->getFileContent('certificate_file', $store);
    }

    /**
     * Returns the private key of the Merchant Identity Certificate (PEM, from the uploaded file)
     *
     * @param int|string|null $store
     * @return string
     */
    public function getPrivateKey(int|string|null $store = null): string
    {
        return $this->getFileContent('private_key_file', $store);
    }

    /**
     * Returns the password of the private key (stored encrypted, empty if the key is not encrypted)
     *
     * @param int|string|null $store
     * @return string
     */
    public function getPrivateKeyPassword(int|string|null $store = null): string
    {
        return (string)$this->getValue('private_key_password', $store);
    }

    /**
     * Returns the store name shown in the payment sheet, the frontend name of the store as fallback
     *
     * @param int|string|null $store
     * @return string
     */
    public function getDisplayName(int|string|null $store = null): string
    {
        $displayName = trim((string)$this->getValue('display_name', $store));
        if ($displayName === '') {
            $displayName = (string)$this->storeManager->getStore($store)->getFrontendName();
        }
        return mb_substr($displayName !== '' ? $displayName : self::DEFAULT_DISPLAY_NAME, 0, self::DISPLAY_NAME_MAX_LENGTH);
    }

    /**
     * Returns the card networks offered in the payment sheet (Apple Pay JS names, e.g. masterCard)
     *
     * @param int|string|null $store
     * @return string[]
     */
    public function getSupportedNetworks(int|string|null $store = null): array
    {
        return array_values(array_filter(explode(',', (string)$this->getValue('supported_networks', $store))));
    }

    /**
     * Returns the merchant's country for the payment request: country of the store information, default country as fallback
     *
     * @param int|string|null $store
     * @return string
     */
    public function getCountryCode(int|string|null $store = null): string
    {
        $country = (string)$this->generalConfig->getValue('general/store_information/country_id', $store);
        if ($country === '') {
            $country = (string)$this->generalConfig->getValue('general/country/default', $store);
        }
        return strtoupper($country);
    }

    /**
     * Returns a value of section payone_cp_payment/payone_cp_applepay
     *
     * @param string $field
     * @param int|string|null $store
     * @return mixed
     */
    private function getValue(string $field, int|string|null $store): mixed
    {
        return $this->generalConfig->getPaymentValue(Payone::METHOD_APPLEPAY, $field, $store);
    }

    /**
     * Returns the content of an uploaded file (Model\Config\Backend\EncryptedFile)
     *
     * @param string $field
     * @param int|string|null $store
     * @return string
     */
    private function getFileContent(string $field, int|string|null $store): string
    {
        return $this->encryptedFile->decode((string)$this->getValue($field, $store))['content'] ?? '';
    }
}
