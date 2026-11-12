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

namespace Payone\CommercePlatform\Model;

/**
 * Matches an IP against a configured list of IPs (webhook whitelist, trusted proxies)
 */
class IpAddressList
{
    /**
     * Checks if the IP is in the list: exact IPs, "*" as wildcard for one octet, CIDR ranges
     *
     * @param string[] $entries
     * @param string $ip
     * @return bool
     */
    public function contains(array $entries, string $ip): bool
    {
        if (in_array($ip, $entries, true)) {
            return true;
        }

        foreach ($entries as $entry) {
            if (str_contains($entry, '/')) {
                if ($this->isIpInCidrRange($ip, $entry)) {
                    return true;
                }
            } elseif (str_contains($entry, '*')) {
                $regex = '/^'.str_replace('\*', '\d{1,3}', preg_quote($entry, '/')).'$/';
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
