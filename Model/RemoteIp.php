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
 * IP of the requesting client, respecting the proxy mode setting
 */
class RemoteIp
{
    /**
     * @param Http $request
     * @param GeneralConfig $generalConfig
     */
    public function __construct(
        private readonly Http $request,
        private readonly GeneralConfig $generalConfig,
    ) {
    }

    /**
     * Returns the IP of the requesting client
     *
     * In proxy mode the IP is taken from X-Forwarded-For, which may be a list like "<client>, <proxy1>, <proxy2>"
     *
     * @return string
     */
    public function get(): string
    {
        $clientIp = (string)$this->request->getClientIp($this->generalConfig->isProxyMode());
        $splitIp = explode(',', $clientIp);
        return trim((string)current($splitIp));
    }
}
