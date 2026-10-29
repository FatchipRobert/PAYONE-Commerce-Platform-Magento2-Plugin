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

namespace Payone\CommercePlatform\Gateway;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader as CoreSubjectReader;

/**
 * Reads values from the command subject and from API responses
 *
 * The response in the subject is the array returned by Gateway\Http\Client:
 * ['status' => HTTP status, 'body' => decoded JSON or null, 'raw' => raw body]
 */
class SubjectReader
{
    /**
     * Returns the payment data object of the subject
     *
     * @param array $subject
     * @return PaymentDataObjectInterface
     */
    public function readPayment(array $subject): PaymentDataObjectInterface
    {
        return CoreSubjectReader::readPayment($subject);
    }

    /**
     * Returns the amount of the subject
     *
     * @param array $subject
     * @return float
     */
    public function readAmount(array $subject): float
    {
        return (float)CoreSubjectReader::readAmount($subject);
    }

    /**
     * Returns the API response of the subject
     *
     * @param array $subject
     * @return array
     */
    public function readResponse(array $subject): array
    {
        return CoreSubjectReader::readResponse($subject);
    }

    /**
     * Searches the data recursively for the given key and returns the first value found
     *
     * @param mixed $data
     * @param string $key
     * @return mixed
     */
    public function findValue(mixed $data, string $key): mixed
    {
        if (!is_array($data)) {
            return null;
        }

        foreach ($data as $dataKey => $value) {
            if ($dataKey === $key) {
                return $value;
            }

            if (is_array($value)) {
                $result = $this->findValue($value, $key);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }
}
