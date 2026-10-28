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

namespace Payone\CommercePlatform\Service\V1\Data;

use Payone\CommercePlatform\Api\Data\ClickToPayResponseInterface;

/**
 * Object for PayPal Express V2 response
 */
class ClickToPayResponse extends \Magento\Framework\Api\AbstractExtensibleObject implements ClickToPayResponseInterface
{
    /**
     * Returns JSON web token
     *
     * @return string
     */
    public function getJwt()
    {
        return $this->_get('jwt');
    }

    /**
     * Returns if the call was successful
     *
     * @return bool
     */
    public function getSuccess()
    {
        return $this->_get('success');
    }

    /**
     * Returns errormessage
     *
     * @return string
     */
    public function getErrormessage()
    {
        return $this->_get('errormessage');
    }
}
