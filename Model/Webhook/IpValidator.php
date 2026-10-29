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

use Payone\CommercePlatform\Gateway\Config\GeneralConfig;

/**
 * Checks the sender IP of incoming webhooks against the configured whitelist
 */
class IpValidator
{
    /**
     * @param GeneralConfig $generalConfig
     */
    public function __construct(
        private readonly GeneralConfig $generalConfig,
    ) {
    }

    /**
     * Checks the IP against the whitelist: exact IPs, "*" as wildcard for one octet, CIDR ranges
     *
     * An empty whitelist allows every IP.
     *
     * @param string $ip
     * @return bool
     */
    public function isValid(string $ip): bool
    {
        $whitelist = $this->generalConfig->getValidIps();
        if (empty($whitelist)) {
            return true;
        }

        if (in_array($ip, $whitelist, true)) {
            return true;
        }

        foreach ($whitelist as $validIp) {
            if (str_contains($validIp, '/')) {
                if ($this->isIpInCidrRange($ip, $validIp)) {
                    return true;
                }
            } elseif (str_contains($validIp, '*')) {
                $regex = '/^'.str_replace('\*', '\d{1,3}', preg_quote($validIp, '/')).'$/';
                if (preg_match($regex, $ip) === 1) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks if an IPv4 address is inside a CIDR range like "18.198.31.0/24"
     *
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    private function isIpInCidrRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, '');
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false || !ctype_digit($bits) || (int)$bits > 32) {
            return false;
        }

        $mask = (int)$bits === 0 ? 0 : (-1 << (32 - (int)$bits)) & 0xFFFFFFFF;
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
