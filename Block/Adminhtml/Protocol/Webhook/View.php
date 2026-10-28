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

namespace Payone\CommercePlatform\Block\Adminhtml\Protocol\Webhook;

use Payone\CommercePlatform\Model\ApiLog;

class View extends \Magento\Backend\Block\Widget\Container
{
    /**
     * Requested ApiLog-entry
     *
     * @var \Payone\CommercePlatform\Model\WebhookMessage
     */
    protected $webhookMessage = null;

    /**
     * ApiLog factory
     *
     * @var \Payone\CommercePlatform\Model\WebhookMessageFactory
     */
    protected $webhookFactory;

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Widget\Context                $context
     * @param \Payone\CommercePlatform\Model\WebhookMessageFactory $webhookFactory
     * @param array                                                $data
     */
    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Payone\CommercePlatform\Model\WebhookMessageFactory $webhookFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->webhookFactory = $webhookFactory;
    }

    /**
     * Returns the currently requested ApiLog-object
     *
     * @return ApiLog
     */
    public function getWebhookLogEntry()
    {
        if ($this->webhookMessage === null) {
            $apiLog = $this->webhookFactory->create();
            $apiLog->load($this->getRequest()->getParam('id'));
            $this->webhookMessage = $apiLog;
        }
        return $this->webhookMessage;
    }

    /**
     * Adding the Back button
     *
     * @return void
     */
    protected function _construct()
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
