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

namespace Payone\CommercePlatform\Service\V1;

use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Authorization\Model\UserContextInterface;
use Payone\CommercePlatform\Api\Data\ClickToPayResponseInterfaceFactory;
use Payone\CommercePlatform\Model\Api\Request\GetJWTFactory;
use Payone\CommercePlatform\Model\Method\Creditcard;
use Payone\CommercePlatform\Api\ClickToPayInterface;
use Payone\CommercePlatform\Model\Payone;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Model\Group;

/**
 * Web API model for the PAYONE Creditcard
 */
class ClickToPay implements ClickToPayInterface
{
    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    protected $maskedQuoteIdToQuoteId;

    /**
     * @var UserContextInterface
     */
    protected $userContext;

    /**
     * Factory for the response object
     *
     * @var ClickToPayResponseInterfaceFactory
     */
    protected $responseFactory;

    /**
     * Factory for the JWT request - a new request object per call, as request objects hold state
     *
     * @var GetJWTFactory
     */
    protected $jwtRequestFactory;

    /**
     * Constructor.
     *
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param UserContextInterface $userContext
     * @param ClickToPayResponseInterfaceFactory $responseFactory
     * @param GetJWTFactory $jwtRequestFactory
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        UserContextInterface $userContext,
        ClickToPayResponseInterfaceFactory $responseFactory,
        GetJWTFactory $jwtRequestFactory,
    ) {
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->userContext = $userContext;
        $this->responseFactory = $responseFactory;
        $this->jwtRequestFactory = $jwtRequestFactory;
    }

    /**
     * Get JWT for ClickToPay process
     *
     * @param string $cartId
     * @return ClickToPayResponse
     */
    public function getJwt($cartId)
    {
        $quote = $this->cartRepository->getActive((int)$cartId);

        if ((int)$quote->getCustomerId() !== (int)$this->userContext->getUserId()) {
            throw new AuthorizationException(__('Not allowed.'));
        }

        return $this->process($quote);
    }

    /**
     * Get JWT for ClickToPay process
     *
     * @param string $cartId
     * @return ClickToPayResponse
     */
    public function getJwtGuest($cartId)
    {
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute((string)$cartId);
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
     * Get JWT for ClickToPay process
     *
     * @param  CartInterface $quote
     * @return ClickToPayResponse
     */
    private function process(CartInterface $quote)
    {
        $success = false;
        $response = $this->responseFactory->create();

        $payment = $quote->getPayment();
        $payment->setMethod(Payone::METHOD_CREDITCARD);

        $quote->setPayment($payment);
        $this->cartRepository->save($quote);

        $apiResponse = $this->jwtRequestFactory->create()->sendRequest();

        if ($apiResponse->isSuccessResponse() === true) {
            $success = true;

            $response->setData('jwt', $apiResponse->findData('token'));
        } else {
            $response->setData('errormessage', __($apiResponse->getFirstErrorMessage()));
        }

        $response->setData('success', $success);
        return $response;
    }
}
