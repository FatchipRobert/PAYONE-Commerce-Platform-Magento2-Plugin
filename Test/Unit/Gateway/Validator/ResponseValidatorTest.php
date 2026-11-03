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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Validator;

use Magento\Payment\Gateway\Validator\Result;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Gateway\Validator\ResponseValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResponseValidatorTest extends TestCase
{
    /**
     * @var ResponseValidator
     */
    private ResponseValidator $validator;

    protected function setUp(): void
    {
        $resultFactory = $this->createStub(ResultInterfaceFactory::class);
        $resultFactory->method('create')->willReturnCallback(
            static fn (array $data) => new Result($data['isValid'], $data['failsDescription'], $data['errorCodes'])
        );
        $this->validator = new ResponseValidator($resultFactory, new SubjectReader());
    }

    public static function responseProvider(): array
    {
        return [
            'success' => [200, ['paymentId' => 'PAY1'], null],
            'created' => [201, ['checkout' => ['paymentResponse' => ['payment' => ['status' => 'PENDING_CAPTURE']]]], null],
            'no body' => [200, null, 'An unknown error occurred.'],
            'empty body' => [204, [], 'An unknown error occurred.'],
            'no status' => [0, ['paymentId' => 'PAY1'], 'An unknown error occurred.'],
            'server error without errors' => [500, ['x' => 'y'], 'An unknown error occurred.'],
            'redirect status' => [302, ['paymentId' => 'PAY1'], 'An unknown error occurred.'],
            'error list' => [
                400,
                ['errorId' => 'E1', 'errors' => [['code' => '1', 'message' => 'Invalid IBAN'], ['message' => 'second']]],
                'Invalid IBAN',
            ],
            'error list in successful status' => [200, ['errors' => [['message' => 'Failed']]], 'Failed'],
            'error without message' => [400, ['errors' => [['code' => '1']]], 'An unknown error occurred.'],
            'error as string' => [400, ['errors' => ['PAYMENT_METHOD_NOT_ALLOWED']], 'PAYMENT_METHOD_NOT_ALLOWED'],
            'only message' => [401, ['message' => 'Unauthorized'], 'Unauthorized'],
            'message with other fields is no error' => [200, ['message' => 'info', 'paymentId' => 'PAY1'], null],
            'nested errorResponse in HTTP 201' => [
                201,
                ['checkout' => ['errorResponse' => ['errors' => [['message' => 'INVALID_GOOGLE_PAY_TOKEN']]]]],
                'INVALID_GOOGLE_PAY_TOKEN',
            ],
            'empty nested errorResponse' => [201, ['checkout' => ['errorResponse' => ['errors' => []]]], null],
            'rejected payment without errorResponse' => [
                201,
                ['checkout' => ['paymentResponse' => ['payment' => ['status' => 'REJECTED']]]],
                ResponseValidator::PAYMENT_REJECTED,
            ],
        ];
    }

    #[DataProvider('responseProvider')]
    public function testValidate(int $status, ?array $body, ?string $expectedError): void
    {
        $response = ['status' => $status, 'body' => $body];

        $result = $this->validator->validate(['response' => $response]);

        $this->assertSame($expectedError === null, $result->isValid());
        $this->assertSame($expectedError === null, $this->validator->isSuccessful($response));
        $this->assertSame($expectedError === null ? [] : [$expectedError], array_map('strval', $result->getFailsDescription()));
    }

    public function testIsSuccessfulWithoutStatus(): void
    {
        $this->assertFalse($this->validator->isSuccessful([]));
    }
}
