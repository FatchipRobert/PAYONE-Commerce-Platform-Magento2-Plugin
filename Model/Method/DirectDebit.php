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

use Payone\CommercePlatform\Model\Payone;

class DirectDebit extends DirectPaymentMethod
{
    /**
     * Method identifier of this payment method
     *
     * @var string
     */
    protected $methodCode = Payone::METHOD_DIRECT_DEBIT;

    /**
     * @var \Payone\CommercePlatform\Model\Country
     */
    protected $country;

    /**
     * Can be used to assign data from frontend to info instance
     *
     * @var array
     */
    protected $assignKeys = [
        'bank_country',
        'accountholder',
        'iban',
        'bic',
    ];

    /**
     * @param \Magento\Framework\Event\ManagerInterface                       $eventManager
     * @param \Magento\Payment\Gateway\Config\ValueHandlerPoolInterface       $valueHandlerPool
     * @param \Magento\Payment\Gateway\Data\PaymentDataObjectFactory          $paymentDataObjectFactory
     * @param string                                                          $code
     * @param string                                                          $formBlockType
     * @param string                                                          $infoBlockType
     * @param \Payone\CommercePlatform\Gateway\Config\GeneralConfig           $generalConfig
     * @param \Magento\Checkout\Model\Session                                 $checkoutSession
     * @param \Payone\CommercePlatform\Model\Country                          $country
     * @param \Magento\Payment\Gateway\Command\CommandPoolInterface|null      $commandPool
     * @param \Magento\Payment\Gateway\Validator\ValidatorPoolInterface|null  $validatorPool
     * @param \Magento\Payment\Gateway\Command\CommandManagerInterface|null   $commandExecutor
     * @param \Psr\Log\LoggerInterface|null                                   $logger
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
        \Payone\CommercePlatform\Model\Country $country,
        ?\Magento\Payment\Gateway\Command\CommandPoolInterface $commandPool = null,
        ?\Magento\Payment\Gateway\Validator\ValidatorPoolInterface $validatorPool = null,
        ?\Magento\Payment\Gateway\Command\CommandManagerInterface $commandExecutor = null,
        ?\Psr\Log\LoggerInterface $logger = null
    ) {
        parent::__construct($eventManager, $valueHandlerPool, $paymentDataObjectFactory, $code, $formBlockType, $infoBlockType, $generalConfig, $checkoutSession, $commandPool, $validatorPool, $commandExecutor, $logger);

        $this->country = $country;
    }

    /**
     * Hook for extension by the real payment method classes
     *
     * @return array
     */
    public function getFrontendConfig()
    {
        $frontendConfig = parent::getFrontendConfig();
        $frontendConfig['sepaCountries'] = $this->country->getEnabledCountries($this->methodCode);
        $frontendConfig['requestBic'] = $this->isBicNeeded();
        return $frontendConfig;
    }

    /**
     * Checks if the BIC has to be entered by the customer
     *
     * @return bool
     */
    protected function isBicNeeded()
    {
        return (bool)$this->getPaymentConfigParam('request_bic');
    }
}
