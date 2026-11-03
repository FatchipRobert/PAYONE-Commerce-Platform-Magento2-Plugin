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

namespace Payone\CommercePlatform\Test\Unit\Gateway\ErrorMapper;

use Payone\CommercePlatform\Gateway\ErrorMapper\PassthroughMessageMapper;
use Payone\CommercePlatform\Gateway\Validator\ResponseValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PassthroughMessageMapperTest extends TestCase
{
    public static function technicalCodeProvider(): array
    {
        return [
            ['3d_SECURE_ERROR', 'The payment was declined. Please check your card details'],
            ['INVALID_GOOGLE_PAY_TOKEN', 'The payment with Google Pay could not be processed.'],
            ['TOKEN_DECRYPT_ERROR', 'The payment with Apple Pay could not be processed.'],
            [ResponseValidator::PAYMENT_REJECTED, 'The payment was declined. Please choose a different payment method.'],
            ['PAYMENT_METHOD_NOT_ALLOWED', 'This payment method is currently not available.'],
            ['PAYMENT_PRODUCT_CONFIGURATION_ERROR', 'This payment method is currently not available.'],
            ['INVALID_REQUEST_ORDER', 'PAYONE could not execute this action for the payment (INVALID_REQUEST_ORDER).'],
        ];
    }

    #[DataProvider('technicalCodeProvider')]
    public function testTechnicalCodesGetReadableText(string $code, string $expectedStart): void
    {
        $this->assertStringStartsWith($expectedStart, (string)(new PassthroughMessageMapper())->getMessage($code));
    }

    public function testOtherMessagesArePassedThroughUnchanged(): void
    {
        $message = 'The card number is invalid (%1)';
        $this->assertSame($message, (string)(new PassthroughMessageMapper())->getMessage($message));
    }
}
