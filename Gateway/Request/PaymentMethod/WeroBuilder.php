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

namespace Payone\CommercePlatform\Gateway\Request\PaymentMethod;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Gateway\SubjectReader;

/**
 * Wero specific input (redirect payment method, product 900)
 *
 * PAYONE only supports Direct Sale for Wero (requiresApproval = false, automatic_capture fixed to 1 in config.xml).
 * With requiresApproval = true PAYONE captures anyway, but refunds of such payments fail (INVALID_REQUEST_ORDER).
 * paymentProduct900SpecificInput.captureTrigger is only used for requiresApproval = true, so it is not sent.
 */
class WeroBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param ReturnUrl $returnUrl
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly ReturnUrl $returnUrl,
    ) {
    }

    /**
     * Builds the method specific content
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $order = $this->subjectReader->readPayment($buildSubject)->getPayment()->getOrder();

        return [
            'requiresApproval' => false,
            'tokenize' => false,
            'redirectionData' => [
                'returnUrl' => $this->returnUrl->get($order),
            ],
        ];
    }
}
