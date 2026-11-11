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
 * PayPal (redirect method, no checkout input)
 */
class PayPal extends PayoneMethod
{
    protected const CODE = Payone::METHOD_PAYPAL;
    protected const PAYONE_METHOD_TYPE = 'redirectPaymentMethod';
    protected const PAYONE_PRODUCT_ID = 840;

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
     * PayPal specific input: capture mode, return URL
     *
     * @param Payment $payment
     * @return array
     */
    public function getSpecificInput(Payment $payment): array
    {
        $order = $payment->getOrder();

        return [
            'requiresApproval' => !$this->isAutomaticCapture($order->getStoreId()),
            'tokenize' => false,
            'productSpecificInput' => [
                'addressSelectionAtPayPal' => false,
            ],
            'redirectionData' => [
                'returnUrl' => $this->returnUrl->get($order),
            ],
        ];
    }
}
