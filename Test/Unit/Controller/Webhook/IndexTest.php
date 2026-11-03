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

namespace Payone\CommercePlatform\Test\Unit\Controller\Webhook;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Payone\CommercePlatform\Controller\Webhook\Index;
use Payone\CommercePlatform\Model\RemoteIp;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage;
use Payone\CommercePlatform\Model\Webhook\IpValidator;
use Payone\CommercePlatform\Model\Webhook\Processor;
use Payone\CommercePlatform\Model\Webhook\QueueConsumer;
use Payone\CommercePlatform\Model\Webhook\SignatureValidator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The mocks of the processing steps are shared by all tests, each test only states the steps it is about
 */
#[AllowMockObjectsWithoutExpectations]
class IndexTest extends TestCase
{
    private const BODY = '{"id":"EV1","type":"payment.captured","payment":{"id":"PAY1"}}';

    /**
     * @var bool
     */
    private bool $ipValid = true;
    /**
     * @var bool
     */
    private bool $signatureValid = true;
    /**
     * @var string
     */
    private string $body = self::BODY;
    /**
     * @var WebhookMessage&MockObject
     */
    private MockObject $webhookMessage;
    /**
     * @var PublisherInterface&MockObject
     */
    private MockObject $publisher;
    /**
     * @var Processor&MockObject
     */
    private MockObject $processor;
    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;
    /**
     * @var Raw&MockObject
     */
    private MockObject $result;
    /**
     * @var Index
     */
    private Index $controller;

    protected function setUp(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getContent')->willReturnCallback(fn () => $this->body);
        $request->method('getHeader')->willReturn('KEY_DE');
        $request->method('getServer')->willReturnCallback(
            static fn ($name) => $name === 'REQUEST_TIME_FLOAT' ? microtime(true) - 0.05 : null
        );
        $this->result = $this->createMock(Raw::class);
        $resultFactory = $this->createStub(RawFactory::class);
        $resultFactory->method('create')->willReturn($this->result);
        $this->webhookMessage = $this->createMock(WebhookMessage::class);
        $this->publisher = $this->createMock(PublisherInterface::class);
        $this->processor = $this->createMock(Processor::class);
        $ipValidator = $this->createStub(IpValidator::class);
        $ipValidator->method('isValid')->willReturnCallback(fn ($ip) => $ip === '185.60.20.1' && $this->ipValid);
        $remoteIp = $this->createStub(RemoteIp::class);
        $remoteIp->method('get')->willReturn('185.60.20.1');
        $signatureValidator = $this->createStub(SignatureValidator::class);
        $signatureValidator->method('isValid')->willReturnCallback(
            fn ($validatedRequest, $rawBody) => $validatedRequest === $request && $rawBody === $this->body && $this->signatureValid
        );
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new Index(
            $request,
            $resultFactory,
            $this->webhookMessage,
            new Json(),
            $this->publisher,
            $this->processor,
            $ipValidator,
            $remoteIp,
            $signatureValidator,
            $this->logger
        );
    }

    private function expectResult(int $httpCode, string $output): void
    {
        $this->result->expects($this->once())->method('setHttpResponseCode')->with($httpCode);
        $this->result->expects($this->once())->method('setContents')->with($output)->willReturnSelf();
    }

    public function testCsrfValidationIsDisabled(): void
    {
        $request = $this->createStub(Http::class);
        $this->webhookMessage->expects($this->never())->method('addWebhookMessage');

        $this->assertNull($this->controller->createCsrfValidationException($request));
        $this->assertTrue($this->controller->validateForCsrf($request));
    }

    public function testMessageIsStoredAndQueued(): void
    {
        $this->webhookMessage->method('isEventReceived')->with('EV1')->willReturn(false);
        $this->webhookMessage->expects($this->once())->method('addWebhookMessage')
            ->with(json_decode(self::BODY, true))->willReturn(42);
        $this->publisher->expects($this->once())->method('publish')->with(QueueConsumer::TOPIC, '42');
        $this->processor->expects($this->never())->method('process');
        $this->logger->expects($this->once())->method('info')->with(
            'PAYONE CP webhook accepted',
            $this->callback(static fn (array $context) => $context['eventId'] === 'EV1' && $context['durationMs'] >= 0)
        );
        $this->expectResult(200, 'OK');

        $this->assertSame($this->result, $this->controller->execute());
    }

    public function testMessageIsProcessedDirectlyWhenTheQueueFails(): void
    {
        $this->webhookMessage->method('isEventReceived')->willReturn(false);
        $this->webhookMessage->method('addWebhookMessage')->willReturn(42);
        $this->publisher->expects($this->once())->method('publish')->willThrowException(new \Exception('Queue down'));
        $this->processor->expects($this->once())->method('process')->with(42);
        $this->logger->expects($this->once())->method('warning')
            ->with('PAYONE CP webhook could not be queued, processing it directly: Queue down');
        $this->expectResult(200, 'OK');

        $this->controller->execute();
    }

    public function testDuplicateEventIsOnlyConfirmed(): void
    {
        $this->webhookMessage->method('isEventReceived')->with('EV1')->willReturn(true);
        $this->webhookMessage->expects($this->never())->method('addWebhookMessage');
        $this->publisher->expects($this->never())->method('publish');
        $this->logger->expects($this->once())->method('info')->with('PAYONE CP webhook duplicate');
        $this->expectResult(200, 'OK');

        $this->controller->execute();
    }

    public function testForbiddenIp(): void
    {
        $this->ipValid = false;
        $this->webhookMessage->expects($this->never())->method('addWebhookMessage');
        $this->logger->expects($this->once())->method('warning')
            ->with('PAYONE CP webhook rejected: IP not allowed', ['ip' => '185.60.20.1', 'keyId' => 'KEY_DE']);
        $this->expectResult(403, 'Access denied');

        $this->controller->execute();
    }

    public function testInvalidSignature(): void
    {
        $this->signatureValid = false;
        $this->webhookMessage->expects($this->never())->method('addWebhookMessage');
        $this->logger->expects($this->once())->method('warning')->with('PAYONE CP webhook rejected: invalid signature');
        $this->expectResult(401, 'Invalid signature');

        $this->controller->execute();
    }

    public static function invalidMessageProvider(): array
    {
        return [
            'no json' => ['abc'],
            'json scalar' => ['"abc"'],
            'no type' => ['{"id":"EV1"}'],
            'no id' => ['{"type":"payment.captured"}'],
        ];
    }

    #[DataProvider('invalidMessageProvider')]
    public function testInvalidMessage(string $body): void
    {
        $this->body = $body;
        $this->webhookMessage->expects($this->never())->method('addWebhookMessage');
        $this->logger->expects($this->once())->method('warning')->with('PAYONE CP webhook rejected: invalid message');
        $this->expectResult(400, 'Invalid message');

        $this->controller->execute();
    }
}
