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

namespace Payone\CommercePlatform\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payone\CommercePlatform\Model\Api\Curl;
use Payone\CommercePlatform\Model\ApiLog;
use Payone\CommercePlatform\Model\OrderLocator;
use Payone\CommercePlatform\Model\ResourceModel\ApiLog as ApiLogResource;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage as WebhookMessageResource;
use Payone\CommercePlatform\Model\WebhookMessage;
use Payone\CommercePlatform\Test\Unit\Helper\ObjectManagerStubTrait;
use Payone\CommercePlatform\Test\Unit\Helper\OrderMockTrait;
use PHPUnit\Framework\TestCase;

/**
 * Log models, OrderLocator and the Curl client
 */
class SmallModelsTest extends TestCase
{
    use ObjectManagerStubTrait;
    use OrderMockTrait;

    public function testApiLogPrettyPrintsDetails(): void
    {
        $apiLog = $this->createPartialStub(ApiLog::class, []);
        $apiLog->setData([
            'request_details' => '{"url":"https://x.test/a","name":"Müller"}',
            'response_details' => null,
        ]);

        $this->assertSame("{\n    \"url\": \"https://x.test/a\",\n    \"name\": \"Müller\"\n}", $apiLog->getRequestDetails());
        $this->assertSame('null', $apiLog->getResponseDetails());
    }

    public function testWebhookMessagePrettyPrintsMessage(): void
    {
        $message = $this->createPartialStub(WebhookMessage::class, []);
        $message->setData('message', '{"type":"payment.captured"}');

        $this->assertSame("{\n    \"type\": \"payment.captured\"\n}", $message->getWebhookDetails());
    }

    public function testModelsAreBoundToTheirResourceModels(): void
    {
        $this->setUpObjectManagerStub();
        $apiLog = $this->createPartialStub(ApiLog::class, []);
        $webhookMessage = $this->createPartialStub(WebhookMessage::class, []);
        foreach ([$apiLog, $webhookMessage] as $model) {
            (new \ReflectionMethod($model, '_construct'))->invoke($model);
        }

        $this->assertSame(ApiLogResource::class, $apiLog->getResourceName());
        $this->assertSame(WebhookMessageResource::class, $webhookMessage->getResourceName());
    }

    public function testCollectionsOfTheAdminGrids(): void
    {
        $apiLogs = $this->createPartialStub(ApiLogResource\Collection::class, []);
        $webhookMessages = $this->createPartialStub(WebhookMessageResource\Collection::class, []);
        foreach ([$apiLogs, $webhookMessages] as $collection) {
            (new \ReflectionMethod($collection, '_construct'))->invoke($collection);
        }

        $this->assertSame(ApiLog::class, $apiLogs->getModelName());
        $this->assertSame(ApiLogResource::class, $apiLogs->getResourceModelName());
        $this->assertSame(WebhookMessage::class, $webhookMessages->getModelName());
        $this->assertSame(WebhookMessageResource::class, $webhookMessages->getResourceModelName());
    }

    public function testOrderLocatorFindsOrderByPayonePaymentId(): void
    {
        $order = $this->createOrder();
        $searchCriteria = $this->createStub(SearchCriteria::class);
        $builder = $this->createMock(SearchCriteriaBuilder::class);
        $builder->expects($this->once())->method('addFilter')->with('payone_cp_payment_id', 'PAY1')->willReturnSelf();
        $builder->expects($this->once())->method('setPageSize')->with(1)->willReturnSelf();
        $builder->method('create')->willReturn($searchCriteria);
        $searchResult = $this->createStub(OrderSearchResultInterface::class);
        $searchResult->method('getItems')->willReturn([5 => $order]);
        $repository = $this->createStub(OrderRepositoryInterface::class);
        $repository->method('getList')->willReturn($searchResult);

        $this->assertSame($order, (new OrderLocator($repository, $builder))->getByPayonePaymentId('PAY1'));
    }

    public function testOrderLocatorReturnsNullWithoutOrder(): void
    {
        $builder = $this->createStub(SearchCriteriaBuilder::class);
        $builder->method('addFilter')->willReturnSelf();
        $builder->method('setPageSize')->willReturnSelf();
        $builder->method('create')->willReturn($this->createStub(SearchCriteria::class));
        $searchResult = $this->createStub(OrderSearchResultInterface::class);
        $searchResult->method('getItems')->willReturn([]);
        $repository = $this->createStub(OrderRepositoryInterface::class);
        $repository->method('getList')->willReturn($searchResult);

        $this->assertNull((new OrderLocator($repository, $builder))->getByPayonePaymentId('PAY1'));
    }

    public function testCurlSendRequestSupportsEveryHttpMethod(): void
    {
        $curl = $this->getMockBuilder(Curl::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['makeRequest'])
            ->getMock();
        $curl->expects($this->once())->method('makeRequest')->with('PATCH', 'https://x.test', '{"a":1}');

        $curl->sendRequest('PATCH', 'https://x.test', '{"a":1}');
    }
}
