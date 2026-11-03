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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Validator\Result;
use Payone\CommercePlatform\Gateway\Command\ResultCommand;
use Payone\CommercePlatform\Gateway\Http\Client;
use Payone\CommercePlatform\Gateway\Validator\ResponseValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ResultCommandTest extends TestCase
{
    /**
     * @var Client&Stub
     */
    private Stub $client;
    /**
     * @var ResponseValidator&MockObject
     */
    private MockObject $validator;
    /**
     * @var ResultCommand
     */
    private ResultCommand $command;

    protected function setUp(): void
    {
        $builder = $this->createStub(BuilderInterface::class);
        $builder->method('build')->willReturn(['body' => []]);
        $transfer = $this->createStub(TransferInterface::class);
        $transferFactory = $this->createStub(TransferFactoryInterface::class);
        $transferFactory->method('create')->willReturn($transfer);
        $this->client = $this->createStub(Client::class);
        $this->client->method('placeRequest')
            ->willReturnCallback(fn () => $this->response);
        $this->validator = $this->createMock(ResponseValidator::class);
        $resultFactory = $this->createStub(ArrayResultFactory::class);
        $resultFactory->method('create')->willReturnCallback(
            static fn (array $data) => new ArrayResult($data['array'])
        );

        $this->command = new ResultCommand($builder, $transferFactory, $this->client, $this->validator, $resultFactory);
    }

    /**
     * @var array
     */
    private array $response = [];

    public function testReturnsResponseBody(): void
    {
        $this->response = ['status' => 200, 'body' => ['token' => 'jwt']];
        $this->validator->expects($this->once())->method('validate')
            ->with(['store_id' => 1, 'response' => $this->response])
            ->willReturn(new Result(true));

        $this->assertSame(['token' => 'jwt'], $this->command->execute(['store_id' => 1])->get());
    }

    public function testReturnsEmptyArrayWithoutBody(): void
    {
        $this->response = ['status' => 204, 'body' => null];
        $this->validator->expects($this->once())->method('validate')->willReturn(new Result(true));

        $this->assertSame([], $this->command->execute(['store_id' => 1])->get());
    }

    public function testThrowsWithPayoneMessage(): void
    {
        $this->response = ['status' => 400, 'body' => ['errors' => [['message' => 'bad']]]];
        $this->validator->expects($this->once())->method('validate')->willReturn(new Result(false, ['bad']));

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('bad');
        $this->command->execute(['store_id' => 1]);
    }
}
