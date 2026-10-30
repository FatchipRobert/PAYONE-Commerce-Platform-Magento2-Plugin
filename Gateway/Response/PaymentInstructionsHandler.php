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
use Payone\CommercePlatform\Block\Info\PaymentInstructions;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * Stores the payment instructions (bank account, due date, reference) of PAYONE Secured Invoice on the payment
 *
 * PAYONE returns them as soon as the payment is captured - in the payment response with automatic capture,
 * otherwise in the capture response. Shown by Block\Info\PaymentInstructions (order view, mails, invoice PDF).
 * Responses without instructions (all other methods) are ignored.
 */
class PaymentInstructionsHandler implements HandlerInterface
{
    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
    ) {
    }

    /**
     * Copies the instructions into the additional_information of the payment
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $instructions = $this->subjectReader->findValue($response['body'] ?? [], 'paymentInstructions');
        if (!is_array($instructions) || empty($instructions['payee'])) {
            return;
        }

        $values = [
            PaymentInstructions::KEY_PAYEE_NAME => $instructions['payee']['name'] ?? null,
            PaymentInstructions::KEY_PAYEE_IBAN => $instructions['payee']['iban'] ?? null,
            PaymentInstructions::KEY_PAYEE_BIC => $instructions['payee']['bic'] ?? null,
            PaymentInstructions::KEY_REFERENCE_NUMBER => $instructions['referenceNumber'] ?? null,
            PaymentInstructions::KEY_DUE_DATE => $instructions['dueDate'] ?? null,
        ];

        $payment = $this->subjectReader->readPayment($handlingSubject)->getPayment();
        foreach ($values as $key => $value) {
            if (!empty($value)) {
                $payment->setAdditionalInformation($key, (string)$value);
            }
        }
    }
}
