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

namespace Payone\CommercePlatform\Helper;

class Environment extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Payone\CommercePlatform\Gateway\Config\GeneralConfig
     */
    protected $generalConfig;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context                 $context
     * @param \Magento\Framework\App\RequestInterface               $request
     * @param \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\RequestInterface $request,
        \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->generalConfig = $generalConfig;
    }

    /**
     * Get the IP of the requesting client
     *
     * @return string
     */
    public function getRemoteIp()
    {
        $proxyMode = $this->generalConfig->isProxyMode();
        $clientIp = $this->request->getClientIp($proxyMode); // may return a comma separated ip list like "<client>, <proxy1>, <proxy2>"
        $splitIp = explode(",", $clientIp); // split by comma
        return trim(current($splitIp)); // return first array element
    }

    /**
     * Check if the requesting IP is allowed to send webhooks
     * An empty whitelist disables the check
     * Wildcard * can be used for one block of the IP address, e.g. 185.60.20.*
     * IPv4 ranges can also be given in CIDR notation, e.g. 185.60.20.0/24
     *
     * @return bool
     */
    public function isRemoteIpValid()
    {
        $whitelist = $this->generalConfig->getValidIps();
        if (empty($whitelist)) {
            return true;
        }

        $remoteIp = $this->getRemoteIp();
        error_log(date("Y-m-d H:i:s - ").$remoteIp.PHP_EOL, 3, BP."/access.log"); // leave here for a while to see the incoming IPs @TODO: REMOVE WHEN DONE!
        if (in_array($remoteIp, $whitelist, true)) {
            return true;
        }

        foreach ($whitelist as $validIp) {
            if (strpos($validIp, '/') !== false) {
                if ($this->isIpInCidrRange($remoteIp, $validIp)) {
                    return true;
                }
            } elseif (strpos($validIp, '*') !== false) {
                $regex = '/^'.str_replace('\*', '\d{1,3}', preg_quote($validIp, '/')).'$/';
                if (preg_match($regex, $remoteIp) === 1) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks if an IPv4 address is part of a CIDR range like 185.60.20.0/24
     *
     * @param  string $ip
     * @param  string $cidr
     * @return bool
     */
    protected function isIpInCidrRange($ip, $cidr)
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
