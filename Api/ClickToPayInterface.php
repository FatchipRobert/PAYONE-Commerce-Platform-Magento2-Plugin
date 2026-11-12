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

use Payone\CommercePlatform\Service\V1\Data\ClickToPayResponse;

interface ClickToPayInterface
{
    /**
     * Get JWT for ClickToPay process
     *
     * @param string $cartId
     * @return \Payone\CommercePlatform\Service\V1\Data\ClickToPayResponse
     */
    public function getJwt(string $cartId): ClickToPayResponse;

    /**
     * Get JWT for ClickToPay process (guest checkout)
     *
     * @param string $cartId
     * @return \Payone\CommercePlatform\Service\V1\Data\ClickToPayResponse
     */
    public function getJwtGuest(string $cartId): ClickToPayResponse;
}
