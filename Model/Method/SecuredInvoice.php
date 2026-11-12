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

use Payone\CommercePlatform\Block\Info\PaymentInstructions;
use Payone\CommercePlatform\Model\Payone;

/**
 * PAYONE Secured Invoice (product 3390, Buy Now Pay Later), B2B allowed
 *
 * Request, checkout config and checks like every Buy Now Pay Later method. The payment instructions (PAYONE bank
 * account, due date, reference) are stored by Gateway\Response\PaymentInstructionsHandler and shown by its own
 * info block.
 */
class SecuredInvoice extends BuyNowPayLater
{
    protected const CODE = Payone::METHOD_SECURED_INVOICE;
    protected const PAYONE_PRODUCT_ID = 3390;

    /**
     * Info block with the payment instructions (order view, emails, PDFs)
     *
     * @return string
     */
    public function getInfoBlockType(): string
    {
        return PaymentInstructions::class;
    }
}
