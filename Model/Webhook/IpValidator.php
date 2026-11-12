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
use Payone\CommercePlatform\Model\IpAddressList;

/**
 * Checks the sender IP of incoming webhooks against the configured whitelist
 */
class IpValidator
{
    /**
     * @param GeneralConfig $generalConfig
     * @param IpAddressList $ipAddressList
     */
    public function __construct(
        private readonly GeneralConfig $generalConfig,
        private readonly IpAddressList $ipAddressList,
    ) {
    }

    /**
     * Checks the IP against the whitelist (format see IpAddressList), an empty whitelist allows every IP
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

        return $this->ipAddressList->contains($whitelist, $ip);
    }
}
