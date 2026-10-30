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

namespace Payone\CommercePlatform\Service\V1\Data;

use Magento\Framework\Api\AbstractExtensibleObject;
use Payone\CommercePlatform\Api\Data\ApplePayResponseInterface;

/**
 * Response of the Apple Pay merchant session endpoints
 */
class ApplePayResponse extends AbstractExtensibleObject implements ApplePayResponseInterface
{
    /**
     * Returns the merchant session object from Apple (JSON)
     *
     * @return string|null
     */
    public function getSession(): ?string
    {
        $session = $this->_get('session');
        return $session !== null ? (string)$session : null;
    }

    /**
     * Returns if the call was successful
     *
     * @return bool
     */
    public function getSuccess(): bool
    {
        return (bool)$this->_get('success');
    }

    /**
     * Returns errormessage
     *
     * @return string|null
     */
    public function getErrormessage(): ?string
    {
        $message = $this->_get('errormessage');
        return $message !== null ? (string)$message : null;
    }
}
