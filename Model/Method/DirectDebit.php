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
use Magento\Sales\Model\Order;

class DirectDebit extends DirectPaymentMethod
{
    /**
     * Method identifier of this payment method
     *
     * @var string
     */
    protected $methodCode = Payone::METHOD_DIRECT_DEBIT;

    /**
     * @var int
     */
    protected $paymentProductId = 771;

    /**
     * @var string
     */
    protected $paymentMethodType = Payone::PAYMENT_METHOD_TYPE_SEPA;

    /**
     * @var bool
     */
    protected $isAutomaticCaptureMethod = true;

    /**
     * @var \Payone\CommercePlatform\Helper\Country
     */
    protected $countryHelper;

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
     * @param \Payone\CommercePlatform\Helper\Shop                            $shopHelper
     * @param \Payone\CommercePlatform\Model\Api\Request\OneStepFactory       $oneStepRequestFactory
     * @param \Payone\CommercePlatform\Model\Api\Request\CaptureFactory       $captureRequestFactory
     * @param \Payone\CommercePlatform\Model\Api\Request\RefundFactory        $refundRequestFactory
     * @param \Payone\CommercePlatform\Model\Api\Request\CancelPaymentFactory $cancelPaymentRequestFactory
     * @param \Magento\Framework\Url                                          $url
     * @param \Magento\Checkout\Model\Session                                 $checkoutSession
     * @param \Payone\CommercePlatform\Helper\Country                         $countryHelper
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
        \Payone\CommercePlatform\Helper\Shop $shopHelper,
        \Payone\CommercePlatform\Model\Api\Request\OneStepFactory $oneStepRequestFactory,
        \Payone\CommercePlatform\Model\Api\Request\CaptureFactory $captureRequestFactory,
        \Payone\CommercePlatform\Model\Api\Request\RefundFactory $refundRequestFactory,
        \Payone\CommercePlatform\Model\Api\Request\CancelPaymentFactory $cancelPaymentRequestFactory,
        \Magento\Framework\Url $url,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Payone\CommercePlatform\Helper\Country $countryHelper,
        ?\Magento\Payment\Gateway\Command\CommandPoolInterface $commandPool = null,
        ?\Magento\Payment\Gateway\Validator\ValidatorPoolInterface $validatorPool = null,
        ?\Magento\Payment\Gateway\Command\CommandManagerInterface $commandExecutor = null,
        ?\Psr\Log\LoggerInterface $logger = null
    ) {
        parent::__construct($eventManager, $valueHandlerPool, $paymentDataObjectFactory, $code, $formBlockType, $infoBlockType, $shopHelper, $oneStepRequestFactory, $captureRequestFactory, $refundRequestFactory, $cancelPaymentRequestFactory, $url, $checkoutSession, $commandPool, $validatorPool, $commandExecutor, $logger);

        $this->countryHelper = $countryHelper;
    }

    /**
     * Hook for extension by the real payment method classes
     *
     * @return array
     */
    public function getFrontendConfig()
    {
        $frontendConfig = parent::getFrontendConfig();
        $frontendConfig['sepaCountries'] = $this->countryHelper->getEnabledCountries($this->methodCode);
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

    /**
     * Return parameters specific to this payment type
     *
     * @param  Order|null $order
     * @return array
     */
    public function getPaymentSpecificParameters(?Order $order = null)
    {
        $parameters = parent::getPaymentSpecificParameters($order);

        $infoInstance = $this->getInfoInstance();

        $parameters[$this->getPaymentProductInputIdentifier()] = [
            'mandate' => [
                'recurrenceType' => 'UNIQUE',
                'bankAccountIban' => [
                    'iban' => $infoInstance->getAdditionalInformation('iban'),
                    'accountHolder' => $infoInstance->getAdditionalInformation('accountholder'),
                ],
                'dateOfSignature' => '20220101', ///@TODO: KLÄREN!!! Parameter von Playground übernommen
                'creditorId' => 'DE98ZZZ09999999999', ///@TODO: KLÄREN!!! Parameter von Playground übernommen
                'uniqueMandateReference' => date('YmdHis'), ///@TODO: KLÄREN!!! Parameter von Playground übernommen
            ],
        ];

        if ($this->isBicNeeded() === true) {
            $parameters[$this->getPaymentProductInputIdentifier()]['mandate']['bankAccountIban']['bic'] = $infoInstance->getAdditionalInformation('bic');
        }

        return $parameters;
    }
}
