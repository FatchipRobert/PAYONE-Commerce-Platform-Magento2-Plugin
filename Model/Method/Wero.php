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

namespace Payone\CommercePlatform\Model\Method;

use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Model\Payone;
use Psr\Log\LoggerInterface;

/**
 * Wero (redirect method like PayPal, product 900), only EUR and the Wero countries ("country" validator,
 * specificcountry in config.xml)
 *
 * PAYONE only supports Direct Sale for Wero (requiresApproval = false, always automatic capture).
 * With requiresApproval = true PAYONE captures anyway, but refunds of such payments fail (INVALID_REQUEST_ORDER).
 * paymentProduct900SpecificInput.captureTrigger is only used for requiresApproval = true, so it is not sent.
 */
class Wero extends PayoneMethod
{
    protected const CODE = Payone::METHOD_WERO;
    protected const PAYONE_METHOD_TYPE = 'redirectPaymentMethod';
    protected const PAYONE_PRODUCT_ID = 900;
    protected const ALLOWED_CURRENCIES = ['EUR'];

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
     * @param ReturnUrl $returnUrl
     * @param ValidatorPoolInterface|null $validatorPool
     */
    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        string $formBlockType,
        string $infoBlockType,
        CommandPoolInterface $commandPool,
        LoggerInterface $logger,
        private readonly ReturnUrl $returnUrl,
        ?ValidatorPoolInterface $validatorPool = null,
    ) {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $logger,
            $validatorPool
        );
    }

    /**
     * Always Direct Sale
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isAutomaticCapture(int|string|null $storeId = null): bool
    {
        return true;
    }

    /**
     * Wero specific input: always Direct Sale, return URL
     *
     * @param Payment $payment
     * @return array
     */
    public function getSpecificInput(Payment $payment): array
    {
        return [
            'requiresApproval' => false,
            'tokenize' => false,
            'redirectionData' => [
                'returnUrl' => $this->returnUrl->get($payment->getOrder()),
            ],
        ];
    }
}
