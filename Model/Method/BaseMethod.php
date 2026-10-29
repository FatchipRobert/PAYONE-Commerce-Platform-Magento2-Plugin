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

namespace Payone\CommercePlatform\Model\Method;

use Magento\Payment\Model\Method\Adapter;

/**
 * All payment operations (authorize, initialize, capture, refund, void, cancel) are executed by the Adapter
 * through PayoneCPCommandPool (etc/di.xml)
 */
abstract class BaseMethod extends Adapter
{
    /**
     * @var \Payone\CommercePlatform\Gateway\Config\GeneralConfig
     */
    protected $generalConfig;

    /**
     * Checkout session model
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Payment\Gateway\Config\ValueHandlerPoolInterface $valueHandlerPool
     * @param \Magento\Payment\Gateway\Data\PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $code
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Payment\Gateway\Command\CommandPoolInterface|null $commandPool
     * @param \Magento\Payment\Gateway\Validator\ValidatorPoolInterface|null $validatorPool
     * @param \Magento\Payment\Gateway\Command\CommandManagerInterface|null $commandExecutor
     * @param \Psr\Log\LoggerInterface|null $logger
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Payment\Gateway\Config\ValueHandlerPoolInterface $valueHandlerPool,
        \Magento\Payment\Gateway\Data\PaymentDataObjectFactory $paymentDataObjectFactory,
        $code,
        $formBlockType,
        $infoBlockType,
        \Payone\CommercePlatform\Gateway\Config\GeneralConfig $generalConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        ?\Magento\Payment\Gateway\Command\CommandPoolInterface $commandPool = null,
        ?\Magento\Payment\Gateway\Validator\ValidatorPoolInterface $validatorPool = null,
        ?\Magento\Payment\Gateway\Command\CommandManagerInterface $commandExecutor = null,
        ?\Psr\Log\LoggerInterface $logger = null
    ) {
        if (empty($this->methodCode)) {
            throw new \LogicException("MethodCode is empty!");
        }
        $code = $this->methodCode;

        parent::__construct($eventManager, $valueHandlerPool, $paymentDataObjectFactory, $code, $formBlockType, $infoBlockType, $commandPool, $validatorPool, $commandExecutor, $logger);

        $this->generalConfig = $generalConfig;
        $this->checkoutSession = $checkoutSession;
    }
}
