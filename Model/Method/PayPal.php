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

namespace Payone\CommercePlatform\Model\Method;

use Payone\CommercePlatform\Model\Payone;
use Magento\Sales\Model\Order;

class PayPal extends RedirectPaymentMethod
{
    /**
     * Method identifier of this payment method
     *
     * @var string
     */
    protected $methodCode = Payone::METHOD_PAYPAL;

    /**
     * @var int
     */
    protected $paymentProductId = 840;

    /**
     * @var string
     */
    protected $paymentMethodType = Payone::PAYMENT_METHOD_TYPE_REDIRECT;

    /**
     * Return parameters specific to this payment type
     *
     * @param  Order|null $order
     * @return array
     */
    public function getPaymentSpecificParameters(?Order $order = null)
    {
        $parameters = parent::getPaymentSpecificParameters($order);

        $parameters['requiresApproval'] = !$this->isAutomaticCapture();
        $parameters['tokenize'] = false;
        $parameters[$this->getPaymentProductInputIdentifier()]['addressSelectionAtPayPal'] = false;
        $parameters['redirectionData']['returnUrl'] = $this->getReturnUrl($order);

        return $parameters;
    }
}
