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

namespace Payone\CommercePlatform\Block\Adminhtml\Protocol\Api;

use Payone\CommercePlatform\Model\ApiLog;

class View extends \Magento\Backend\Block\Widget\Container
{
    /**
     * Requested ApiLog-entry
     *
     * @var \Payone\CommercePlatform\Model\ApiLog
     */
    protected $apiLog = null;

    /**
     * @var \Payone\CommercePlatform\Model\ApiLogFactory
     */
    protected $apiLogFactory;

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Widget\Context        $context
     * @param \Payone\CommercePlatform\Model\ApiLogFactory $apiLogFactory
     * @param array                                        $data
     */
    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Payone\CommercePlatform\Model\ApiLogFactory $apiLogFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->apiLogFactory = $apiLogFactory;
    }

    /**
     * Returns the currently requested ApiLog-object
     *
     * @return ApiLog
     */
    public function getApiLogEntry()
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
    protected function _construct()
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
