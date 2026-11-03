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

namespace Payone\CommercePlatform\Test\Unit\Controller\Onepage;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Payone\CommercePlatform\Controller\Onepage\Returned;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage\Collection;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage\CollectionFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Return from the PAYONE redirect: the status comes from the webhooks or the API.
 *
 * The controller really waits (usleep) for webhooks, so the scenarios are chosen to keep the waiting short;
 * only the "still pending" test waits the full FINAL_STATUS_WAIT_MILLISECONDS.
 */
#[AllowMockObjectsWithoutExpectations]
class ReturnedTest extends TestCase
{
    use MockCreationTrait;

    /**
     * Webhook status per lookup (false: no webhook received); the last one is repeated
     *
     * @var array<int, string|false|null>
     */
    private array $webhookStatuses = [];
    /**
     * @var int
     */
    private int $webhookLookups = 0;
    /**
     * @var \Closure|null returns the get_checkout_details result or throws
     */
    private ?\Closure $apiResponse = null;
    /**
     * @var int
     */
    private int $apiCalls = 0;

    /**
     * @var Session&MockObject
     */
    private MockObject $session;
    /**
     * @var Order&MockObject
     */
    private MockObject $order;
    /**
     * @var OrderRepositoryInterface&MockObject
     */
    private MockObject $orderRepository;
    /**
     * @var ManagerInterface&MockObject
     */
    private MockObject $messageManager;
    /**
     * @var Redirect&MockObject
     */
    private MockObject $redirect;
    /**
     * @var Returned
     */
    private Returned $controller;

    protected function setUp(): void
    {
        $this->order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canCancel', 'cancel'])
            ->getMock();
        $this->order->setId(5);
        $this->order->setData('payone_cp_payment_id', 'PAY1');
        $this->order->method('canCancel')->willReturn(true);

        $this->session = $this->createPartialMockWithReflection(
            Session::class,
            ['getLastRealOrder', 'restoreQuote', 'setPayoneCpCheckoutErrorMessage']
        );
        $this->session->method('getLastRealOrder')->willReturn($this->order);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->redirect = $this->createMock(Redirect::class);
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);

        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturnCallback(function () {
            $status = $this->webhookStatuses[min($this->webhookLookups, count($this->webhookStatuses) - 1)] ?? false;
            $this->webhookLookups++;
            $collection = $this->createStub(Collection::class);
            $collection->method('addFieldToFilter')->willReturnCallback(
                fn ($field, $value) => $field === 'payment_id' && $value === 'PAY1' ? $collection : null
            );
            $collection->method('setOrder')->willReturnSelf();
            $collection->method('getSize')->willReturn($status === false ? 0 : 1);
            $collection->method('getFirstItem')->willReturn(new DataObject(['status' => $status]));
            return $collection;
        });

        $command = $this->createStub(CommandInterface::class);
        $command->method('execute')->willReturnCallback(function (array $subject) {
            $this->assertSame(['order' => $this->order], $subject);
            $this->apiCalls++;
            return ($this->apiResponse)();
        });
        $commandPool = $this->createStub(CommandPoolInterface::class);
        $commandPool->method('get')->willReturnCallback(
            static fn ($name) => $name === 'get_checkout_details' ? $command : throw new \LogicException($name)
        );
        $this->apiResponse = static fn () => throw new CommandException(__('API error'));

        $this->controller = new Returned(
            $this->session,
            $this->orderRepository,
            $this->messageManager,
            $redirectFactory,
            $collectionFactory,
            $commandPool
        );
    }

    private static function checkoutDetails(?string $lastStatus): ArrayResult
    {
        return new ArrayResult(['paymentExecutions' => [
            ['events' => [['paymentStatus' => 'CANCELLED']]],
            ['events' => [['paymentStatus' => 'REDIRECTED'], ['paymentStatus' => $lastStatus]]],
        ]]);
    }

    private function expectSuccessPage(): void
    {
        $this->order->expects($this->never())->method('cancel');
        $this->session->expects($this->never())->method('restoreQuote');
        $this->redirect->expects($this->once())->method('setPath')->with('checkout/onepage/success')->willReturnSelf();
    }

    private function expectCancellation(): void
    {
        $this->order->expects($this->once())->method('cancel');
        $this->orderRepository->expects($this->once())->method('save')->with($this->order);
        $this->session->expects($this->once())->method('restoreQuote');
        $this->session->expects($this->once())->method('setPayoneCpCheckoutErrorMessage')->with('The payment has been cancelled.');
        $this->redirect->expects($this->once())->method('setPath')->with('checkout', ['_fragment' => 'payment'])->willReturnSelf();
    }

    public function testCsrfValidationIsDisabled(): void
    {
        $request = $this->createStub(RequestInterface::class);

        $this->assertNull($this->controller->createCsrfValidationException($request));
        $this->assertTrue($this->controller->validateForCsrf($request));
    }

    public static function successStatusProvider(): array
    {
        return [['PENDING_CAPTURE'], ['CAPTURED']];
    }

    #[DataProvider('successStatusProvider')]
    public function testWebhookWithSuccessStatus(string $status): void
    {
        $this->webhookStatuses = [$status];
        $this->expectSuccessPage();
        $this->messageManager->expects($this->never())->method('addNoticeMessage');

        $this->assertSame($this->redirect, $this->controller->execute());
        $this->assertSame(0, $this->apiCalls);
    }

    public static function cancelStatusProvider(): array
    {
        return [['REJECTED'], ['CANCELLED']];
    }

    #[DataProvider('cancelStatusProvider')]
    public function testWebhookWithCancelStatus(string $status): void
    {
        $this->webhookStatuses = [$status];
        $this->expectCancellation();

        $this->controller->execute();
    }

    public function testApiStatusWhenNoWebhookArrived(): void
    {
        $this->webhookStatuses = [false];
        $this->apiResponse = static fn () => self::checkoutDetails('PENDING_CAPTURE');
        $this->expectSuccessPage();

        $this->controller->execute();

        $this->assertSame(Returned::RETRY_LIMIT + 1, $this->webhookLookups, 'webhook lookup with retries');
        $this->assertSame(1, $this->apiCalls);
    }

    public static function unusableApiResponseProvider(): array
    {
        return [
            'API error' => [static fn () => throw new CommandException(__('API error'))],
            'no payment executions' => [static fn () => new ArrayResult(['paymentExecutions' => []])],
            'payment executions no list' => [static fn () => new ArrayResult(['paymentExecutions' => 'x'])],
            'no events' => [static fn () => new ArrayResult(['paymentExecutions' => [['events' => []]]])],
            'no status' => [static fn () => self::checkoutDetails(null)],
            'intermediate status' => [static fn () => self::checkoutDetails('PENDING_PAYMENT')],
        ];
    }

    #[DataProvider('unusableApiResponseProvider')]
    public function testFinalWebhookArrivingWhileWaiting(\Closure $apiResponse): void
    {
        // Wero: the cancellation webhook arrives after the customer returned
        $this->webhookStatuses = ['CAPTURE_REQUESTED', 'CAPTURE_REQUESTED', 'REJECTED'];
        $this->apiResponse = $apiResponse;
        $this->expectCancellation();

        $this->controller->execute();

        $this->assertSame(3, $this->webhookLookups);
    }

    public function testStillPendingAfterWaitingKeepsTheOrder(): void
    {
        $this->webhookStatuses = ['AUTHORIZATION_REQUESTED'];
        $this->apiResponse = static fn () => self::checkoutDetails('AUTHORIZATION_REQUESTED');
        $this->expectSuccessPage();
        $this->messageManager->expects($this->once())->method('addNoticeMessage');

        $this->controller->execute();

        $this->assertSame(
            1 + Returned::FINAL_STATUS_WAIT_MILLISECONDS / Returned::FINAL_STATUS_POLL_MILLISECONDS,
            $this->webhookLookups
        );
    }

    public function testWithoutOrderInSessionTheQuoteIsRestored(): void
    {
        $this->order->setId(null);
        $this->order->expects($this->once())->method('cancel');
        $this->session->expects($this->once())->method('restoreQuote');
        $this->messageManager->expects($this->once())->method('addErrorMessage');
        $this->redirect->expects($this->once())->method('setPath')->with('checkout/cart')->willReturnSelf();

        $this->controller->execute();

        $this->assertSame(0, $this->webhookLookups);
    }
}
