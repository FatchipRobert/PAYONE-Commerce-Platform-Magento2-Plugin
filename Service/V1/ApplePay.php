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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Payone\CommercePlatform\Api\ApplePayInterface;
use Payone\CommercePlatform\Api\Data\ApplePayResponseInterfaceFactory;
use Payone\CommercePlatform\Model\ApplePay\MerchantSession;
use Payone\CommercePlatform\Service\V1\Data\ApplePayResponse;

/**
 * Web API for the merchant validation of the Apple Pay session in the checkout (onvalidatemerchant)
 */
class ApplePay implements ApplePayInterface
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param UserContextInterface $userContext
     * @param ApplePayResponseInterfaceFactory $responseFactory
     * @param MerchantSession $merchantSession
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly UserContextInterface $userContext,
        private readonly ApplePayResponseInterfaceFactory $responseFactory,
        private readonly MerchantSession $merchantSession,
    ) {
    }

    /**
     * Get the Apple Pay merchant session (merchant validation)
     *
     * @param string $cartId
     * @param string $validationUrl
     * @return ApplePayResponse
     */
    public function getSession(string $cartId, string $validationUrl): ApplePayResponse
    {
        $quote = $this->cartRepository->getActive((int)$cartId);

        if ((int)$quote->getCustomerId() !== (int)$this->userContext->getUserId()) {
            throw new AuthorizationException(__('Not allowed.'));
        }

        return $this->process($quote, $validationUrl);
    }

    /**
     * Get the Apple Pay merchant session (merchant validation, guest checkout)
     *
     * @param string $cartId
     * @param string $validationUrl
     * @return ApplePayResponse
     */
    public function getSessionGuest(string $cartId, string $validationUrl): ApplePayResponse
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

        return $this->process($quote, $validationUrl);
    }

    /**
     * Requests the merchant session for the store of the quote
     *
     * @param CartInterface $quote
     * @param string $validationUrl
     * @return ApplePayResponse
     */
    private function process(CartInterface $quote, string $validationUrl): ApplePayResponse
    {
        $response = $this->responseFactory->create();

        try {
            $response->setData('session', $this->merchantSession->create($validationUrl, (int)$quote->getStoreId()));
            $response->setData('success', true);
        } catch (LocalizedException $e) {
            $response->setData('errormessage', $e->getMessage());
            $response->setData('success', false);
        }

        return $response;
    }
}
