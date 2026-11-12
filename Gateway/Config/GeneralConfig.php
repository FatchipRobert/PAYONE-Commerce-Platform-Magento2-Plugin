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

use Locale;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Module configuration outside of the payment method config (payment/<code>/*)
 *
 * $store is a store id or code. null means the current store - pass the store of the order
 * wherever an order is involved, especially in the admin.
 */
class GeneralConfig
{
    public const ENVIRONMENT_LIVE = 'live';

    /**
     * Minimum number of days API and webhook log entries are kept (getLogRetentionDays())
     */
    public const MIN_LOG_RETENTION_DAYS = 30;

    private const SECTION_GENERAL = 'payone_cp_general';
    private const SECTION_PAYMENT = 'payone_cp_payment';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    /**
     * Returns a config value by its full path
     *
     * @param string $path
     * @param int|string|null $store
     * @return mixed
     */
    public function getValue(string $path, int|string|null $store = null): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORES, $store);
    }

    /**
     * Returns a value from section payone_cp_general
     *
     * @param string $field
     * @param string $group
     * @param int|string|null $store
     * @return mixed
     */
    public function getGeneralValue(string $field, string $group = 'global', int|string|null $store = null): mixed
    {
        return $this->getValue(self::SECTION_GENERAL.'/'.$group.'/'.$field, $store);
    }

    /**
     * Returns a value from section payone_cp_payment for a payment method
     *
     * @param string $methodCode
     * @param string $field
     * @param int|string|null $store
     * @return mixed
     */
    public function getPaymentValue(string $methodCode, string $field, int|string|null $store = null): mixed
    {
        return $this->getValue(self::SECTION_PAYMENT.'/'.$methodCode.'/'.$field, $store);
    }

    /**
     * Returns the PAYONE merchant id
     *
     * @param int|string|null $store
     * @return string
     */
    public function getMerchantId(int|string|null $store = null): string
    {
        return (string)$this->getGeneralValue('merchant_id', 'global', $store);
    }

    /**
     * Returns the API key (also the key id of incoming webhooks)
     *
     * @param int|string|null $store
     * @return string
     */
    public function getApiKey(int|string|null $store = null): string
    {
        return (string)$this->getGeneralValue('api_key', 'global', $store);
    }

    /**
     * Returns the API secret (decrypted by the backend_model in etc/config.xml)
     *
     * @param int|string|null $store
     * @return string
     */
    public function getApiSecret(int|string|null $store = null): string
    {
        return (string)$this->getGeneralValue('api_secret', 'global', $store);
    }

    /**
     * Returns the configured environment: "live" or "test"
     *
     * @param int|string|null $store
     * @return string
     */
    public function getEnvironment(int|string|null $store = null): string
    {
        return (string)$this->getGeneralValue('environment', 'global', $store);
    }

    /**
     * Whether the live API is used
     *
     * @param int|string|null $store
     * @return bool
     */
    public function isLiveMode(int|string|null $store = null): bool
    {
        return $this->getEnvironment($store) === self::ENVIRONMENT_LIVE;
    }

    /**
     * Returns the prefix for merchant references
     *
     * @param int|string|null $store
     * @return string
     */
    public function getRefPrefix(int|string|null $store = null): string
    {
        return (string)$this->getGeneralValue('ref_prefix', 'shared_account', $store);
    }

    /**
     * Whether the customer IP is sent to PAYONE
     *
     * @param int|string|null $store
     * @return bool
     */
    public function isTransmitIp(int|string|null $store = null): bool
    {
        return (bool)$this->getGeneralValue('transmit_ip', 'ip_addresses', $store);
    }

    /**
     * Returns after how many days API and webhook log entries are deleted, 0 = never (global setting)
     *
     * Smaller values are raised to MIN_LOG_RETENTION_DAYS: the webhook processing still reads recent entries (event
     * ids of received webhooks, successful captures in the API log).
     *
     * @return int
     */
    public function getLogRetentionDays(): int
    {
        $days = (int)$this->scopeConfig->getValue(self::SECTION_GENERAL.'/logs/retention_days');
        if ($days > 0 && $days < self::MIN_LOG_RETENTION_DAYS) {
            return self::MIN_LOG_RETENTION_DAYS;
        }
        return $days;
    }

    /**
     * Returns the IPs of the proxies the shop runs behind (client IP from X-Forwarded-For), one entry per line
     *
     * @param int|string|null $store
     * @return string[]
     */
    public function getTrustedProxies(int|string|null $store = null): array
    {
        return $this->getIpList('trusted_proxies', $store);
    }

    /**
     * Returns the webhook IP whitelist, one entry per line in the config
     *
     * @param int|string|null $store
     * @return string[]
     */
    public function getValidIps(int|string|null $store = null): array
    {
        return $this->getIpList('valid_ips', $store);
    }

    /**
     * Returns the lines of a textarea field with IPs from payone_cp_general/ip_addresses
     *
     * @param string $field
     * @param int|string|null $store
     * @return string[]
     */
    private function getIpList(string $field, int|string|null $store): array
    {
        $ipList = [];
        foreach (preg_split('/\R/', (string)$this->getGeneralValue($field, 'ip_addresses', $store)) as $line) {
            $ip = trim($line);
            if ($ip !== '') {
                $ipList[] = $ip;
            }
        }
        return $ipList;
    }

    /**
     * Returns the store locale, e.g. "de_DE"
     *
     * @param int|string|null $store
     * @return string
     */
    public function getLocale(int|string|null $store = null): string
    {
        return (string)$this->getValue('general/locale/code', $store);
    }

    /**
     * Returns the language of the store locale, e.g. "de"
     *
     * @param int|string|null $store
     * @return string
     */
    public function getLanguage(int|string|null $store = null): string
    {
        return (string)Locale::getPrimaryLanguage($this->getLocale($store));
    }
}
