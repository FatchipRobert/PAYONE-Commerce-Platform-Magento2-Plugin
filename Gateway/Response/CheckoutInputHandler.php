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
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Model\Method\PayoneMethod;

/**
 * Payment request (authorize/initialize): removes or masks the checkout input that was only needed for the
 * request, as defined by the payment method (PayoneMethod::reduceCheckoutInput())
 */
class CheckoutInputHandler implements HandlerInterface
{
    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
    ) {
    }

    /**
     * Handles the response
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $payment = $this->subjectReader->readPayment($handlingSubject)->getPayment();
        $method = $payment->getMethodInstance();
        if ($method instanceof PayoneMethod) {
            $method->reduceCheckoutInput($payment);
        }
    }
}
