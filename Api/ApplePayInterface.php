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

namespace Payone\CommercePlatform\Api;

use Payone\CommercePlatform\Service\V1\Data\ApplePayResponse;

interface ApplePayInterface
{
    /**
     * Get the Apple Pay merchant session (merchant validation)
     *
     * @param string $cartId
     * @param string $validationUrl
     * @return \Payone\CommercePlatform\Service\V1\Data\ApplePayResponse
     */
    public function getSession(string $cartId, string $validationUrl): ApplePayResponse;

    /**
     * Get the Apple Pay merchant session (merchant validation, guest checkout)
     *
     * @param string $cartId
     * @param string $validationUrl
     * @return \Payone\CommercePlatform\Service\V1\Data\ApplePayResponse
     */
    public function getSessionGuest(string $cartId, string $validationUrl): ApplePayResponse;
}
