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

namespace Payone\CommercePlatform\Block\Adminhtml\Protocol\Api;

use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Payone\CommercePlatform\Model\ApiLog;
use Payone\CommercePlatform\Model\ApiLogFactory;

/**
 * Detail view of an API-log entry
 */
class View extends Container
{
    /**
     * Requested ApiLog-entry
     *
     * @var ApiLog|null
     */
    private ?ApiLog $apiLog = null;

    /**
     * @param Context $context
     * @param ApiLogFactory $apiLogFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ApiLogFactory $apiLogFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Returns the currently requested ApiLog-object
     *
     * @return ApiLog
     */
    public function getApiLogEntry(): ApiLog
    {
        if ($this->apiLog === null) {
            $apiLog = $this->apiLogFactory->create();
            $apiLog->load($this->getRequest()->getParam('id'));
            $this->apiLog = $apiLog;
        }
        return $this->apiLog;
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
                'onclick' => "setLocation('".$this->getUrl('payone_cp/protocol_api/')."')",
                'class' => 'back'
            ]
        );
        parent::_construct();
    }
}
