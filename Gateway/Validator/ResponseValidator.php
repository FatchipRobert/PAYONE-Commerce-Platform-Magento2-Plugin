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

namespace Payone\CommercePlatform\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * Checks if an API call was successful
 *
 * Successful: HTTP 2xx, a JSON object as body, no errors. On failure the first PAYONE error message
 * is the fails description of the result (shown to the customer/admin via the error message mapper).
 */
class ResponseValidator extends AbstractValidator
{
    private const UNKNOWN_ERROR = 'An unknown error occurred.';
    private const STATUS_REJECTED = 'REJECTED';

    /**
     * Error message for a rejected payment without PAYONE error (translated by PassthroughMessageMapper)
     */
    public const PAYMENT_REJECTED = 'PAYMENT_REJECTED';

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly SubjectReader $subjectReader,
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * Validates the response in the subject
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = $this->subjectReader->readResponse($validationSubject);
        if ($this->isSuccessful($response)) {
            return $this->createResult(true);
        }
        return $this->createResult(false, [$this->getFirstErrorMessage($response['body'] ?? null)]);
    }

    /**
     * Checks the response returned by Gateway\Http\Client
     *
     * @param array $response
     * @return bool
     */
    public function isSuccessful(array $response): bool
    {
        $status = (int)($response['status'] ?? 0);
        $body = $response['body'] ?? null;

        return $status >= 200 && $status < 300
            && !empty($body) && is_array($body)
            && empty($this->getErrors($body));
    }

    /**
     * Returns the errors of an unsuccessful response
     *
     * @param array|null $body
     * @return array
     */
    private function getErrors(?array $body): array
    {
        if (!empty($body['errors'])) {
            return (array)$body['errors'];
        }

        if (!empty($body['message']) && count($body) === 1) {
            return [$body['message']];
        }

        // A failed payment inside a successful call (e.g. HTTP 201 for POST /commerce-cases) is only reported in a nested errorResponse
        $errorResponse = $this->subjectReader->findValue($body, 'errorResponse');
        if (is_array($errorResponse) && !empty($errorResponse['errors'])) {
            return (array)$errorResponse['errors'];
        }

        // A declined payment without errorResponse (e.g. risk check of Buy Now Pay Later) must not create an order
        if (($body['checkout']['paymentResponse']['payment']['status'] ?? null) === self::STATUS_REJECTED) {
            return [self::PAYMENT_REJECTED];
        }

        return [];
    }

    /**
     * Returns the message of the first error, a generic message if there is none (e.g. empty or non-JSON response)
     *
     * @param array|null $body
     * @return string
     */
    private function getFirstErrorMessage(?array $body): string
    {
        $firstError = current($this->getErrors($body));
        if (is_string($firstError) && $firstError !== '') {
            return $firstError;
        }
        if (is_array($firstError) && !empty($firstError['message'])) {
            return (string)$firstError['message'];
        }
        return self::UNKNOWN_ERROR;
    }
}
