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

namespace Payone\CommercePlatform\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * WebhookMessage entity model
 */
class WebhookMessage extends AbstractModel
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Payone\CommercePlatform\Model\ResourceModel\WebhookMessage::class);
    }

    /**
     * Formats the stored JSON for the admin detail view
     *
     * @param  string|null $json
     * @return string|false
     */
    protected function getPrettyPrintJson($json)
    {
        $formatted = json_encode(
            json_decode($json, true),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return $formatted;
    }

    /**
     * Returns the webhook message as formatted JSON
     *
     * @return string|false
     */
    public function getWebhookDetails()
    {
        return $this->getPrettyPrintJson($this->getData('message'));
    }
}
