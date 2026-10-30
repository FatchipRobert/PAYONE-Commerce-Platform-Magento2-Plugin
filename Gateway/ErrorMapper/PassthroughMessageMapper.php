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

namespace Payone\CommercePlatform\Gateway\ErrorMapper;

use Magento\Framework\Phrase;
use Magento\Payment\Gateway\ErrorMapper\ErrorMessageMapperInterface;
use Payone\CommercePlatform\Gateway\Validator\ResponseValidator;

/**
 * Passes the PAYONE error message through to the customer/admin
 *
 * Without an error message mapper, GatewayCommand only shows "Transaction has been declined. Please try again later.".
 * Technical messages without meaning for the customer are replaced by an understandable, translated text.
 */
class PassthroughMessageMapper implements ErrorMessageMapperInterface
{
    /**
     * Returns the message to show for a PAYONE error message
     *
     * @param string $code PAYONE error message (fails description of Gateway\Validator\ResponseValidator)
     * @return Phrase
     */
    public function getMessage(string $code): Phrase
    {
        return match ($code) {
            '3d_SECURE_ERROR' => __(
                'The payment was declined. Please check your card details or choose a different payment method.'
            ),
            'INVALID_GOOGLE_PAY_TOKEN' => __(
                'The payment with Google Pay could not be processed. Please try again or choose a different payment method.'
            ),
            ResponseValidator::PAYMENT_REJECTED => __(
                'The payment was declined. Please choose a different payment method.'
            ),
            // Payment product not activated for the PAYONE account
            'PAYMENT_METHOD_NOT_ALLOWED' => __(
                'This payment method is currently not available. Please choose a different payment method.'
            ),
            default => new Phrase($code),
        };
    }
}
