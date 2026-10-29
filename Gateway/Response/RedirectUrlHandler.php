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

namespace Payone\CommercePlatform\Gateway\Response;

use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * Stores the redirect URL (3DS, PayPal, ...) in the checkout session for Controller/Onepage/Redirect
 *
 * The value of a previous payment attempt is always removed.
 */
class RedirectUrlHandler implements HandlerInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param Session $checkoutSession
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly Session $checkoutSession,
    ) {
    }

    /**
     * Handles the response of POST /commerce-cases
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $this->checkoutSession->unsPayoneRedirectUrl();

        $body = $response['body'] ?? [];
        $redirectUrl = $this->subjectReader->findValue($body, 'redirectURL');
        if (!empty($this->subjectReader->findValue($body, 'paymentId')) && !empty($redirectUrl)) {
            $this->checkoutSession->setPayoneRedirectUrl($redirectUrl);
        }
    }
}
