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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Http;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payone\CommercePlatform\Gateway\Http\Client;
use Payone\CommercePlatform\Gateway\Http\Signer;
use Payone\CommercePlatform\Gateway\SubjectReader;
use Payone\CommercePlatform\Gateway\Validator\ResponseValidator;
use Payone\CommercePlatform\Model\Api\Curl;
use Payone\CommercePlatform\Model\Api\CurlFactory;
use Payone\CommercePlatform\Model\ResourceModel\ApiLog;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /**
     * @var Curl&MockObject
     */
    private MockObject $curl;
    /**
     * @var ApiLog&MockObject
     */
    private MockObject $apiLog;
    /**
     * @var Client
     */
    private Client $client;
    /**
     * @var array
     */
    private array $options = [];

    protected function setUp(): void
    {
        $this->curl = $this->createMock(Curl::class);
        $this->curl->method('setOption')->willReturnCallback(function ($option, $value) {
            $this->options[$option] = $value;
        });
        $curlFactory = $this->createStub(CurlFactory::class);
        $curlFactory->method('create')->willReturn($this->curl);

        $signer = $this->createStub(Signer::class);
        $signer->method('getDate')->willReturn('Tue, 03 Nov 2026 10:00:00 GMT');
        $signer->method('getAuthorizationHeader')->willReturnCallback(
            static fn ($method, $contentType, $date, $path, $store) => "sig:$method:$contentType:$date:$path:$store"
        );

        $this->apiLog = $this->createMock(ApiLog::class);
        $responseValidator = new ResponseValidator($this->createStub(ResultInterfaceFactory::class), new SubjectReader());

        $this->client = new Client($curlFactory, $signer, $this->apiLog, $responseValidator, new Json());
    }

    private function createTransfer(): TransferInterface
    {
        $transfer = $this->createStub(TransferInterface::class);
        $transfer->method('getClientConfig')->willReturn([
            'store_id' => 2,
            'path' => '/v1/P1/commerce-cases',
            'request_name' => 'OneStep',
            'order_increment_id' => '000000100',
            'payment_method' => 'payone_cp_creditcard',
        ]);
        $transfer->method('getBody')->willReturn('{"a":1}');
        $transfer->method('getMethod')->willReturn('POST');
        $transfer->method('getUri')->willReturn('https://api.preprod.commerce.payone.com/v1/P1/commerce-cases');
        $transfer->method('getHeaders')->willReturn(['Content-Type' => 'application/json; charset=utf-8']);
        return $transfer;
    }

    public function testSendsSignedRequestAndLogsSuccess(): void
    {
        $this->curl->expects($this->once())->method('setHeaders')->with([
            'Content-Type' => 'application/json; charset=utf-8',
            'Date' => 'Tue, 03 Nov 2026 10:00:00 GMT',
            'Authorization' => 'sig:POST:application/json; charset=utf-8:Tue, 03 Nov 2026 10:00:00 GMT:/v1/P1/commerce-cases:2',
        ]);
        $this->curl->expects($this->once())->method('sendRequest')
            ->with('POST', 'https://api.preprod.commerce.payone.com/v1/P1/commerce-cases', '{"a":1}');
        $this->curl->method('getStatus')->willReturn(201);
        $this->curl->method('getBody')->willReturn(' {"paymentId":"p1"} ');
        $this->apiLog->expects($this->once())->method('addApiLogEntry')->with(
            2,
            'OneStep',
            '000000100',
            'payone_cp_creditcard',
            '{"a":1}',
            ApiLog::STATUS_SUCCESS,
            ' {"paymentId":"p1"} ',
            ['paymentId' => 'p1']
        );

        $response = $this->client->placeRequest($this->createTransfer());

        $this->assertSame(['status' => 201, 'body' => ['paymentId' => 'p1'], 'raw' => ' {"paymentId":"p1"} '], $response);
        $this->assertSame(CURLPROTO_HTTPS, $this->options[CURLOPT_PROTOCOLS]);
        $this->assertTrue($this->options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $this->options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertSame(0, $this->options[CURLOPT_MAXREDIRS]);
        $this->assertSame(30, $this->options[CURLOPT_TIMEOUT]);
    }

    public static function undecodableBodyProvider(): array
    {
        return [
            'empty' => ['  '],
            'no json' => ['<html>Bad Gateway</html>'],
            'json scalar' => ['"text"'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('undecodableBodyProvider')]
    public function testUndecodableBodyIsLoggedAsError(string $raw): void
    {
        $this->curl->expects($this->once())->method('sendRequest');
        $this->curl->method('getStatus')->willReturn(502);
        $this->curl->method('getBody')->willReturn($raw);
        $this->apiLog->expects($this->once())->method('addApiLogEntry')
            ->with(2, 'OneStep', '000000100', 'payone_cp_creditcard', '{"a":1}', ApiLog::STATUS_ERROR, $raw, null);

        $response = $this->client->placeRequest($this->createTransfer());

        $this->assertSame(502, $response['status']);
        $this->assertNull($response['body']);
    }

    public function testCurlExceptionIsLoggedAndRethrown(): void
    {
        $this->curl->expects($this->once())->method('sendRequest')->willThrowException(new \Exception('Connection timed out'));
        $this->apiLog->expects($this->once())->method('addApiLogEntry')->with(
            2,
            'OneStep',
            '000000100',
            'payone_cp_creditcard',
            '{"a":1}',
            ApiLog::STATUS_ERROR,
            'Exception caught: Connection timed out',
            null
        );

        $this->expectExceptionMessage('Connection timed out');
        $this->client->placeRequest($this->createTransfer());
    }
}
