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

namespace Payone\CommercePlatform\Service\V1;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Payone\CommercePlatform\Api\ClickToPayInterface;
use Payone\CommercePlatform\Api\Data\ClickToPayResponseInterfaceFactory;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Service\V1\Data\ClickToPayResponse;

/**
 * Web API for the JWT of the Click to Pay credit card form
 */
class ClickToPay implements ClickToPayInterface
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param UserContextInterface $userContext
     * @param ClickToPayResponseInterfaceFactory $responseFactory
     * @param CommandPoolInterface $commandPool PayoneCPServiceCommandPool
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly UserContextInterface $userContext,
        private readonly ClickToPayResponseInterfaceFactory $responseFactory,
        private readonly CommandPoolInterface $commandPool,
    ) {
    }

    /**
     * Get JWT for ClickToPay process
     *
     * @param string $cartId
     * @return ClickToPayResponse
     */
    public function getJwt(string $cartId): ClickToPayResponse
    {
        $quote = $this->cartRepository->getActive((int)$cartId);

        if ((int)$quote->getCustomerId() !== (int)$this->userContext->getUserId()) {
            throw new AuthorizationException(__('Not allowed.'));
        }

        return $this->process($quote);
    }

    /**
     * Get JWT for ClickToPay process (guest checkout)
     *
     * @param string $cartId
     * @return ClickToPayResponse
     */
    public function getJwtGuest(string $cartId): ClickToPayResponse
    {
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        } catch (NoSuchEntityException $e) {
            throw new NoSuchEntityException(__('Cart not found.'));
        }

        $quote = $this->cartRepository->getActive($quoteId);

        if ($quote->getCustomerId()) {
            throw new AuthorizationException(__('Not allowed.'));
        }

        return $this->process($quote);
    }

    /**
     * Sets the credit card method on the quote and requests the JWT
     *
     * @param CartInterface $quote
     * @return ClickToPayResponse
     */
    private function process(CartInterface $quote): ClickToPayResponse
    {
        $response = $this->responseFactory->create();

        $payment = $quote->getPayment();
        $payment->setMethod(Payone::METHOD_CREDITCARD);

        $quote->setPayment($payment);
        $this->cartRepository->save($quote);

        try {
            $result = $this->commandPool->get('get_jwt')->execute(['store_id' => $quote->getStoreId()]);
            $response->setData('jwt', $result->get()['token'] ?? null);
            $response->setData('success', true);
        } catch (CommandException $e) {
            $response->setData('errormessage', $e->getMessage());
            $response->setData('success', false);
        }

        return $response;
    }
}
