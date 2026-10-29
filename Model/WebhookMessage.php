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

use Magento\Framework\Model\AbstractModel;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage as WebhookMessageResource;

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
    protected function _construct(): void
    {
        $this->_init(WebhookMessageResource::class);
    }

    /**
     * Formats the stored JSON for the admin detail view
     *
     * @param string|null $json
     * @return string|false
     */
    private function getPrettyPrintJson(?string $json): string|false
    {
        return json_encode(
            json_decode((string)$json, true),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Returns the webhook message as formatted JSON
     *
     * @return string|false
     */
    public function getWebhookDetails(): string|false
    {
        return $this->getPrettyPrintJson($this->getData('message'));
    }
}
