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

namespace Payone\CommercePlatform\Model\Method;

use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Model\Payone;

/**
 * PAYONE Secured Direct Debit (product 3392, Buy Now Pay Later): additionally the bank account, no B2B
 *
 * The BIC is only needed for countries where it cannot be derived from the IBAN (Switzerland), not for DE/AT.
 */
class SecuredDirectDebit extends BuyNowPayLater
{
    protected const CODE = Payone::METHOD_SECURED_DIRECT_DEBIT;
    protected const PAYONE_PRODUCT_ID = 3392;
    protected const B2B_ALLOWED = false;

    protected const ADDITIONAL_DATA_KEYS = ['dateofbirth', 'telephone', 'device_token', 'accountholder', 'iban'];

    /**
     * Capture mode plus the bank account (paymentProduct3392SpecificInput)
     *
     * @param Payment $payment
     * @return array
     */
    public function getSpecificInput(Payment $payment): array
    {
        $input = parent::getSpecificInput($payment);
        $input['productSpecificInput'] = [
            'bankAccountInformation' => [
                'iban' => $payment->getAdditionalInformation('iban'),
                'accountHolder' => mb_substr((string)$payment->getAdditionalInformation('accountholder'), 0, 50), // max. 50
            ],
        ];
        return $input;
    }
}
