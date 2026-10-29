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

namespace Payone\CommercePlatform\Block\Adminhtml\Protocol\Webhook;

use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Payone\CommercePlatform\Model\WebhookMessage;
use Payone\CommercePlatform\Model\WebhookMessageFactory;

/**
 * Detail view of a webhook-log entry
 */
class View extends Container
{
    /**
     * Requested webhook-log entry
     *
     * @var WebhookMessage|null
     */
    private ?WebhookMessage $webhookMessage = null;

    /**
     * @param Context $context
     * @param WebhookMessageFactory $webhookFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly WebhookMessageFactory $webhookFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Returns the currently requested webhook-log entry
     *
     * @return WebhookMessage
     */
    public function getWebhookLogEntry(): WebhookMessage
    {
        if ($this->webhookMessage === null) {
            $webhookMessage = $this->webhookFactory->create();
            $webhookMessage->load($this->getRequest()->getParam('id'));
            $this->webhookMessage = $webhookMessage;
        }
        return $this->webhookMessage;
    }

    /**
     * Adding the Back button
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->buttonList->add(
            'back',
            [
                'label' => __('Back'),
                'onclick' => "setLocation('".$this->getUrl('payone_cp/protocol_webhook/')."')",
                'class' => 'back'
            ]
        );
        parent::_construct();
    }
}
