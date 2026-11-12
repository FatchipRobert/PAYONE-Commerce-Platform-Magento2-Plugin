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

use Magento\Framework\App\Request\Http;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;

/**
 * IP of the requesting client, behind the configured trusted proxies of the shop
 */
class RemoteIp
{
    /**
     * @param Http $request
     * @param GeneralConfig $generalConfig
     * @param IpAddressList $ipAddressList
     */
    public function __construct(
        private readonly Http $request,
        private readonly GeneralConfig $generalConfig,
        private readonly IpAddressList $ipAddressList,
    ) {
    }

    /**
     * Returns the IP of the requesting client
     *
     * Without trusted proxies this is REMOTE_ADDR. Every proxy appends the IP it was called from to
     * X-Forwarded-For ("<client>, <proxy1>, <proxy2>"), so as long as the request came from a trusted proxy,
     * the IP is taken from the end of the list. Entries before the first other IP are not used, they are not
     * set by the own proxies.
     *
     * @return string
     */
    public function get(): string
    {
        $ip = (string)$this->request->getServer('REMOTE_ADDR');
        $trustedProxies = $this->generalConfig->getTrustedProxies();
        if (empty($trustedProxies)) {
            return $ip;
        }

        $forwardedFor = (string)$this->request->getServer('HTTP_X_FORWARDED_FOR');
        $forwardedIps = $forwardedFor !== '' ? explode(',', $forwardedFor) : [];
        while (!empty($forwardedIps) && $this->ipAddressList->contains($trustedProxies, $ip)) {
            $ip = trim((string)array_pop($forwardedIps));
        }
        return $ip;
    }
}
