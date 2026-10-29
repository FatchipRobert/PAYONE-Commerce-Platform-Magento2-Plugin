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

namespace Payone\CommercePlatform\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * Stores the PAYONE ids of the new payment on the order (needed for capture, refund, cancel, webhooks)
 * and uses the payment id as Magento transaction id of the authorization
 */
class PaymentIdsHandler implements HandlerInterface
{
    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
    ) {
    }

    /**
     * Handles the response of POST /commerce-cases
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        /** @var Payment $payment */
        $payment = $this->subjectReader->readPayment($handlingSubject)->getPayment();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false); // the order mail is sent after the authorization

        $body = $response['body'] ?? [];
        $paymentId = $this->subjectReader->findValue($body, 'paymentId');
        if (empty($paymentId)) {
            return;
        }

        $order->setData('payone_cp_commerce_case_id', $this->subjectReader->findValue($body, 'commerceCaseId'));
        $order->setData('payone_cp_checkout_id', $this->subjectReader->findValue($body, 'checkoutId'));
        $order->setData('payone_cp_payment_id', $paymentId);
        $order->setData('payone_cp_payment_execution_id', $this->subjectReader->findValue($body, 'paymentExecutionId'));

        $payment->setTransactionId((string)$paymentId);
        $payment->setIsTransactionClosed(false);
    }
}
