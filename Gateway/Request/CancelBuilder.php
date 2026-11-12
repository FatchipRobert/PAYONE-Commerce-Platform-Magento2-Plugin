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

namespace Payone\CommercePlatform\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Payone\CommercePlatform\Gateway\AmountConverter;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Model\Method\PayoneMethod;

/**
 * Payload of the cancellation (void/cancel) of the authorized amount which has not been captured yet
 *
 * The subject has no amount for void/cancel. Amounts are base amounts like in capture/refund.
 */
class CancelBuilder implements BuilderInterface
{
    /**
     * Reason sent if the method requires one (PayoneMethod::requiresCancellationReason(), Buy Now Pay Later)
     */
    public const CANCELLATION_REASON = 'CONSUMER_REQUEST';

    /**
     * @param SubjectReader $subjectReader
     * @param AmountConverter $amountConverter
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly AmountConverter $amountConverter,
    ) {
    }

    /**
     * Builds the request part
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $payment = $this->subjectReader->readPayment($buildSubject)->getPayment();
        $order = $payment->getOrder();
        $amount = (float)$order->getBaseGrandTotal() - (float)$order->getBaseTotalPaid();

        $body = [
            'amount' => $this->amountConverter->toMinorUnits($amount),
        ];

        // Magento has no reason for void/cancel, the merchant cancels on request
        $method = $payment->getMethodInstance();
        if ($method instanceof PayoneMethod && $method->requiresCancellationReason()) {
            $body['cancellationReason'] = self::CANCELLATION_REASON;
        }

        return ['body' => $body];
    }
}
