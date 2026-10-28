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

namespace Payone\CommercePlatform\Model\Api;

class Curl extends \Magento\Framework\HTTP\Client\Curl
{
    /**
     * Wrapper for the core makeRequest function, since makeRequest is protected
     *
     * @param string       $method
     * @param string       $uri
     * @param array|string $params
     * @return void
     */
    public function sendRequest($method, $uri, $params = [])
    {
        $this->makeRequest($method, $uri, $params);
    }
}
