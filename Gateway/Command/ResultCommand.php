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

namespace Payone\CommercePlatform\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Payone\CommercePlatform\Gateway\Http\Client;
use Payone\CommercePlatform\Gateway\Validator\ResponseValidator;

/**
 * API call that returns its result - for calls outside of payment operations (e.g. GetJWT, GetCheckoutDetails)
 *
 * Builder → TransferFactory → Client → ResponseValidator → ArrayResult with the decoded response body.
 */
class ResultCommand implements CommandInterface
{
    /**
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param Client $client
     * @param ResponseValidator $validator
     * @param ArrayResultFactory $resultFactory
     */
    public function __construct(
        private readonly BuilderInterface $requestBuilder,
        private readonly TransferFactoryInterface $transferFactory,
        private readonly Client $client,
        private readonly ResponseValidator $validator,
        private readonly ArrayResultFactory $resultFactory,
    ) {
    }

    /**
     * Sends the request and returns the response body
     *
     * @param array $commandSubject
     * @return ArrayResult
     * @throws CommandException with the PAYONE error message if the call was not successful
     */
    public function execute(array $commandSubject): ArrayResult
    {
        $response = $this->client->placeRequest(
            $this->transferFactory->create($this->requestBuilder->build($commandSubject))
        );

        $result = $this->validator->validate(array_merge($commandSubject, ['response' => $response]));
        if (!$result->isValid()) {
            throw new CommandException(__(implode(PHP_EOL, $result->getFailsDescription())));
        }

        return $this->resultFactory->create(['array' => $response['body'] ?? []]);
    }
}
