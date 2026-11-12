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

namespace Payone\CommercePlatform\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResourceConnection\ConnectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Model\ResourceModel\ApiLog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApiLogTest extends TestCase
{
    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;
    /**
     * @var ConnectionFactory&MockObject
     */
    private MockObject $connectionFactory;
    /**
     * @var ApiLog
     */
    private ApiLog $apiLog;
    /**
     * @var int
     */
    private int $transactionLevel = 0;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('getTransactionLevel')->willReturnCallback(fn () => $this->transactionLevel);
        $resources = $this->createStub(ResourceConnection::class);
        $resources->method('getConnection')->willReturn($this->connection);
        $resources->method('getTableName')->willReturnArgument(0);
        $context = $this->createStub(Context::class);
        $context->method('getResources')->willReturn($resources);

        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getMerchantId')->willReturnCallback(static fn ($store) => 'P1_'.$store);
        $deploymentConfig = $this->createStub(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturnCallback(
            static fn ($path) => $path === 'db/connection/default' ? ['host' => 'db', 'persistent' => 1] : null
        );
        $this->connectionFactory = $this->createMock(ConnectionFactory::class);

        $this->apiLog = new ApiLog($context, $generalConfig, $deploymentConfig, $this->connectionFactory, new SubjectReader());
    }

    private function captureInsert(AdapterInterface&MockObject $connection, ?array &$row): void
    {
        $connection->expects($this->once())->method('insert')->willReturnCallback(
            function (string $table, array $data) use (&$row) {
                $this->assertSame('payone_cp_protocol_api', $table);
                $row = $data;
                return 1;
            }
        );
    }

    public function testAddApiLogEntryStoresRequestAndPayoneIds(): void
    {
        $this->connectionFactory->expects($this->never())->method('create');
        $this->captureInsert($this->connection, $row);

        $this->apiLog->addApiLogEntry(
            2,
            'OneStep',
            '000000100',
            'payone_cp_paypal',
            '{"amount":100}',
            ApiLog::STATUS_SUCCESS,
            '{"commerceCaseId":"CC1"}',
            ['commerceCaseId' => 'CC1', 'checkout' => ['checkoutId' => 'CK1', 'paymentResponse' => [
                'paymentId' => 'PAY1',
                'paymentExecutionId' => 'PE1',
            ]]]
        );

        $this->assertSame([
            'order_increment_id' => '000000100',
            'store_id' => 2,
            'payment_method' => 'payone_cp_paypal',
            'merchant_id' => 'P1_2',
            'request' => 'OneStep',
            'response' => ApiLog::STATUS_SUCCESS,
            'request_details' => '{"amount":100}',
            'response_details' => '{"commerceCaseId":"CC1"}',
            'commerce_case_id' => 'CC1',
            'checkout_id' => 'CK1',
            'payment_id' => 'PAY1',
            'payment_execution_id' => 'PE1',
        ], $row);
    }

    public static function maskingProvider(): array
    {
        return [
            'IBAN partially' => [
                '{"mandate":{"bankAccountIban":{"iban":"DE02120300000000202051","accountHolder":"Max"}}}',
                '{"mandate":{"bankAccountIban":{"iban":"******************2051","accountHolder":"Max"}}}',
            ],
            'short value completely' => ['{"iban":"1234"}', '{"iban":"***"}'],
            'token completely' => ['{"token":"eyJhbGciOi.payload.sig"}', '{"token":"***"}'],
            'card token partially, Google Pay data completely' => [
                '{"paymentProcessingToken":"abcdef123456","encryptedPaymentData":"eyJzaWduYXR1cmUi"}',
                '{"paymentProcessingToken":"********3456","encryptedPaymentData":"***"}',
            ],
            'empty values are kept' => ['{"iban":"","token":null}', '{"iban":"","token":null}'],
            'nothing to mask keeps the raw formatting' => ["{\"amount\": 100}", "{\"amount\": 100}"],
            'no json' => ['<html>Bad Gateway</html>', '<html>Bad Gateway</html>'],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('maskingProvider')]
    public function testSensitiveFieldsAreMasked(string $raw, ?string $expected): void
    {
        $this->connectionFactory->expects($this->never())->method('create');
        $this->captureInsert($this->connection, $row);

        $this->apiLog->addApiLogEntry(null, 'X', null, null, $raw, ApiLog::STATUS_ERROR, $raw);

        $this->assertSame($expected, $row['request_details']);
        $this->assertSame($expected, $row['response_details']);
        $this->assertNull($row['payment_id']);
    }

    public function testInsideTransactionASeparateConnectionIsUsed(): void
    {
        $this->transactionLevel = 1;
        $logConnection = $this->createMock(AdapterInterface::class);
        $this->connectionFactory->expects($this->once())->method('create')->with(['host' => 'db'])->willReturn($logConnection);
        $this->connection->expects($this->never())->method('insert');
        $logConnection->expects($this->exactly(2))->method('insert');

        $this->apiLog->addApiLogEntry(1, 'Capture', '1', 'm', '{}', ApiLog::STATUS_ERROR);
        $this->apiLog->addApiLogEntry(1, 'Capture', '1', 'm', '{}', ApiLog::STATUS_ERROR);
    }

    public function testHasSuccessfulRequest(): void
    {
        $this->connectionFactory->expects($this->never())->method('create');
        $select = $this->createMock(Select::class);
        $select->expects($this->exactly(2))->method('from')->with('payone_cp_protocol_api', ['entity_id'])->willReturnSelf();
        $select->expects($this->exactly(6))->method('where')->willReturnCallback(function ($condition, $value) use ($select) {
            $this->assertContains([$condition, $value], [
                ['order_increment_id = ?', '000000100'],
                ['request = ?', 'Capture'],
                ['response = ?', ApiLog::STATUS_SUCCESS],
            ]);
            return $select;
        });
        $select->method('limit')->willReturnSelf();
        $this->connection->method('select')->willReturn($select);
        $this->connection->expects($this->exactly(2))->method('fetchOne')->willReturnOnConsecutiveCalls('17', false);

        $this->assertTrue($this->apiLog->hasSuccessfulRequest('000000100', 'Capture'));
        $this->assertFalse($this->apiLog->hasSuccessfulRequest('000000100', 'Capture'));
    }
}
