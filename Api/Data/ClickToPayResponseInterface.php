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

namespace Payone\CommercePlatform\Api\Data;

interface ClickToPayResponseInterface
{
    /**
     * Returns JSON web token
     *
     * @return string|null
     */
    public function getJwt(): ?string;

    /**
     * Returns if the call was successful
     *
     * @return bool
     */
    public function getSuccess(): bool;

    /**
     * Returns errormessage
     *
     * @return string|null
     */
    public function getErrormessage(): ?string;
}
