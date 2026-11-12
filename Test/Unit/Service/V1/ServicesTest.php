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

namespace Payone\CommercePlatform\Test\Unit\Service\V1;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Payone\CommercePlatform\Api\Data\ApplePayResponseInterfaceFactory;
use Payone\CommercePlatform\Api\Data\ClickToPayResponseInterfaceFactory;
use Payone\CommercePlatform\Model\ApplePay\MerchantSession;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Service\V1\ApplePay;
use Payone\CommercePlatform\Service\V1\ClickToPay;
use Payone\CommercePlatform\Service\V1\Data\ApplePayResponse;
use Payone\CommercePlatform\Service\V1\Data\ClickToPayResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * REST services of the checkout: Click to Pay JWT and Apple Pay merchant session
 */
class ServicesTest extends TestCase
{
    private const VALIDATION_URL = 'https://apple-pay-gateway.apple.com/paymentservices/startSession';

    /**
     * @var CartRepositoryInterface&MockObject
     */
    private MockObject $cartRepository;
    /**
     * @var Quote
     */
    private Quote $quote;
    /**
     * @var int
     */
    private int $userId = 0;

    private function getResponseArguments(): array
    {
        return [$this->createStub(ExtensionAttributesFactory::class), $this->createStub(AttributeValueFactory::class)];
    }

    private function createQuote(?int $customerId): Quote
    {
        $payment = static::getStubBuilder(Payment::class)->disableOriginalConstructor()->onlyMethods([])->getStub();
        $quote = static::getStubBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'setPayment'])
            ->getStub();
        $quote->setData(['customer_id' => $customerId, 'store_id' => 2]);
        $quote->method('getPayment')->willReturn($payment);
        return $quote;
    }

    private function createCommon(?int $customerId): array
    {
        $this->quote = $this->createQuote($customerId);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->cartRepository->method('getActive')->willReturnCallback(
            fn ($quoteId) => $quoteId === 10 ? $this->quote : throw new NoSuchEntityException()
        );
        $maskedQuoteId = $this->createStub(MaskedQuoteIdToQuoteIdInterface::class);
        $maskedQuoteId->method('execute')->willReturnCallback(
            static fn ($maskedId) => $maskedId === 'masked10' ? 10 : throw new NoSuchEntityException(__('No such entity'))
        );
        $userContext = $this->createStub(UserContextInterface::class);
        $userContext->method('getUserId')->willReturnCallback(fn () => $this->userId);
        return [$maskedQuoteId, $userContext];
    }

    private function createClickToPay(?int $customerId, ?\Closure $command): ClickToPay
    {
        [$maskedQuoteId, $userContext] = $this->createCommon($customerId);
        $responseFactory = $this->createStub(ClickToPayResponseInterfaceFactory::class);
        $responseFactory->method('create')
            ->willReturnCallback(fn () => new ClickToPayResponse(...$this->getResponseArguments()));
        $jwtCommand = $this->createStub(CommandInterface::class);
        $jwtCommand->method('execute')->willReturnCallback($command ?? static fn () => null);
        $commandPool = $this->createStub(CommandPoolInterface::class);
        $commandPool->method('get')->willReturnCallback(
            static fn ($name) => $name === 'get_jwt' ? $jwtCommand : throw new \LogicException($name)
        );
        return new ClickToPay($this->cartRepository, $maskedQuoteId, $userContext, $responseFactory, $commandPool);
    }

    public function testClickToPayJwtForGuest(): void
    {
        $service = $this->createClickToPay(null, static function (array $subject) {
            return new ArrayResult($subject === ['store_id' => 2] ? ['token' => 'JWT'] : []);
        });
        $this->cartRepository->expects($this->once())->method('save')->with($this->quote);

        $response = $service->getJwtGuest('masked10');

        $this->assertTrue($response->getSuccess());
        $this->assertSame('JWT', $response->getJwt());
        $this->assertNull($response->getErrormessage());
        $this->assertSame(Payone::METHOD_CREDITCARD, $this->quote->getPayment()->getMethod());
    }

    public function testClickToPayJwtForCustomer(): void
    {
        $service = $this->createClickToPay(7, static fn () => new ArrayResult([]));
        $this->userId = 7;
        $this->cartRepository->expects($this->once())->method('save');

        $response = $service->getJwt('10');

        $this->assertTrue($response->getSuccess());
        $this->assertNull($response->getJwt());
    }

    public function testClickToPayCommandError(): void
    {
        $service = $this->createClickToPay(null, static fn () => throw new CommandException(__('Invalid merchant')));
        $this->cartRepository->expects($this->once())->method('save');

        $response = $service->getJwtGuest('masked10');

        $this->assertFalse($response->getSuccess());
        $this->assertSame('Invalid merchant', $response->getErrormessage());
    }

    public function testClickToPayCartOfOtherCustomer(): void
    {
        $service = $this->createClickToPay(7, null);
        $this->userId = 8;
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(AuthorizationException::class);
        $service->getJwt('10');
    }

    public function testClickToPayGuestRouteWithCustomerCart(): void
    {
        $service = $this->createClickToPay(7, null);
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(AuthorizationException::class);
        $service->getJwtGuest('masked10');
    }

    public function testClickToPayUnknownMaskedCartId(): void
    {
        $service = $this->createClickToPay(null, null);
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Cart not found.');
        $service->getJwtGuest('unknown');
    }

    private function createApplePay(?int $customerId, \Closure $session): ApplePay
    {
        [$maskedQuoteId, $userContext] = $this->createCommon($customerId);
        $responseFactory = $this->createStub(ApplePayResponseInterfaceFactory::class);
        $responseFactory->method('create')
            ->willReturnCallback(fn () => new ApplePayResponse(...$this->getResponseArguments()));
        $merchantSession = $this->createStub(MerchantSession::class);
        $merchantSession->method('create')->willReturnCallback($session);
        return new ApplePay($this->cartRepository, $maskedQuoteId, $userContext, $responseFactory, $merchantSession);
    }

    public function testApplePaySessionForGuest(): void
    {
        $service = $this->createApplePay(null, static function ($url, $storeId) {
            return $url === self::VALIDATION_URL && $storeId === 2 ? '{"merchantSessionIdentifier":"S"}' : '';
        });
        $this->cartRepository->expects($this->never())->method('save');

        $response = $service->getSessionGuest('masked10', self::VALIDATION_URL);

        $this->assertTrue($response->getSuccess());
        $this->assertSame('{"merchantSessionIdentifier":"S"}', $response->getSession());
        $this->assertNull($response->getErrormessage());
    }

    public function testApplePaySessionForCustomerWithError(): void
    {
        $service = $this->createApplePay(7, static fn () => throw new LocalizedException(__('Apple Pay is currently not available.')));
        $this->userId = 7;
        $this->cartRepository->expects($this->never())->method('save');

        $response = $service->getSession('10', self::VALIDATION_URL);

        $this->assertFalse($response->getSuccess());
        $this->assertNull($response->getSession());
        $this->assertSame('Apple Pay is currently not available.', $response->getErrormessage());
    }

    public function testApplePayAccessChecks(): void
    {
        $service = $this->createApplePay(7, static fn () => '');
        $this->userId = 8;
        $this->cartRepository->expects($this->never())->method('save');

        foreach ([
            static fn () => $service->getSession('10', self::VALIDATION_URL),
            static fn () => $service->getSessionGuest('masked10', self::VALIDATION_URL),
        ] as $call) {
            try {
                $call();
                $this->fail('AuthorizationException expected');
            } catch (AuthorizationException $e) {
                $this->assertSame('Not allowed.', $e->getMessage());
            }
        }

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Cart not found.');
        $service->getSessionGuest('unknown', self::VALIDATION_URL);
    }
}
