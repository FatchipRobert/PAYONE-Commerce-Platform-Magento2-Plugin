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

class Creditcard extends RedirectPaymentMethod
{
    public const CREDITCARD_PAYMENT_PRODUCT_IDS = [
        'VISA' => 1,
        'AMERICAN_EXPRESS' => 2,
        'MASTERCARD' => 3,
        'DINERS' => 123,
    ];

    /**
     * Method identifier of this payment method
     *
     * @var string
     */
    protected $methodCode = Payone::METHOD_CREDITCARD;

    /**
     * @var int
     */
    protected $paymentProductId = 1;
    # Visa - 1
    # American Express - 2
    # Mastercard - 3
    # Diners Club - 123

    /**
     * @var string
     */
    protected $paymentMethodType = Payone::PAYMENT_METHOD_TYPE_CARD;

    /**
     * Can be used to assign data from frontend to info instance
     *
     * @var array
     */
    protected $assignKeys = [
        'pseudocardpan',
        'cardholder',
        'cardtype',
        'cardinputmode',
    ];

    /**
     * Returns the PAYONE payment product id of the selected card type
     *
     * @return int
     */
    public function getPaymentProductId()
    {
        $cardType = $this->getInfoInstance()->getAdditionalInformation('cardtype');
        if (!empty($cardType) && isset(self::CREDITCARD_PAYMENT_PRODUCT_IDS[$cardType])) {
            return self::CREDITCARD_PAYMENT_PRODUCT_IDS[$cardType];
        }

        return $this->paymentProductId;
    }

    /**
     * Returns the UI customization config for the hosted tokenization form
     *
     * @return array
     */
    protected function getFrontendUiConfig()
    {
        return [
            "uiConfigCustomizationEnabled" => $this->generalConfig->getGeneralValue("customization_enabled", "creditcard"),
            "uiConfigFormBgColor" => $this->generalConfig->getGeneralValue("form_bg_color", "creditcard"),
            "uiConfigFieldBgColor" => $this->generalConfig->getGeneralValue("field_bg_color", "creditcard"),
            "uiConfigFieldBorder" => $this->generalConfig->getGeneralValue("field_border", "creditcard"),
            "uiConfigFieldOutline" => $this->generalConfig->getGeneralValue("field_outline", "creditcard"),
            "uiConfigFieldLabelColor" => $this->generalConfig->getGeneralValue("field_label_color", "creditcard"),
            "uiConfigFieldPlaceholderColor" => $this->generalConfig->getGeneralValue("field_placeholder_color", "creditcard"),
            "uiConfigFieldTextColor" => $this->generalConfig->getGeneralValue("field_text_color", "creditcard"),
            "uiConfigFieldErrorCodeColor" => $this->generalConfig->getGeneralValue("field_error_code_color", "creditcard"),
            "uiConfigButtonStyle" => $this->generalConfig->getGeneralValue("button_style", "creditcard"),
            "uiConfigButtonTextCase" => $this->generalConfig->getGeneralValue("button_text_case", "creditcard"),
            "uiConfigButtonAndBadgeColor" => $this->generalConfig->getGeneralValue("button_badge_color", "creditcard"),
            "uiConfigButtonFilledHoverColor" => $this->generalConfig->getGeneralValue("button_hover_color", "creditcard"),
            "uiConfigButtonOutlinedHoverColor" => $this->generalConfig->getGeneralValue("button_outlined_hover_color", "creditcard"),
            "uiConfigButtonDisabledColor" => $this->generalConfig->getGeneralValue("button_disabled_color", "creditcard"),
            "uiConfigCardItemActiveColor" => $this->generalConfig->getGeneralValue("card_item_active_color", "creditcard"),
            "uiConfigButtonAndBadgeTextColor" => $this->generalConfig->getGeneralValue("button_badge_text_color", "creditcard"),
            "uiConfigLinkTextColor" => $this->generalConfig->getGeneralValue("link_text_color", "creditcard"),
            "uiConfigAccentColor" => $this->generalConfig->getGeneralValue("accent_color", "creditcard"),
            "uiConfigFontFamily" => $this->generalConfig->getGeneralValue("font_family", "creditcard"),
            "uiConfigButtonAndInputRadius" => $this->generalConfig->getGeneralValue("button_input_radius", "creditcard"),
            "uiConfigCardItemRadius" => $this->generalConfig->getGeneralValue("card_item_radius", "creditcard"),
        ];
    }

    /**
     * Returns the config for the checkout JS
     *
     * @return array
     */
    public function getFrontendConfig()
    {
        $frontendConfig = [
            'mode' => $this->getOperationMode(),
            'ctpEnabled' => $this->getPaymentConfigParam('clicktopay_enabled'),
            'ctpRegisterEnabled' => $this->getPaymentConfigParam('clicktopay_register_enabled'),
            'ctpShopName' => $this->getPaymentConfigParam('clicktopay_shopname'),
        ];
        if ($this->generalConfig->getGeneralValue("customization_enabled", "creditcard")) {
            $frontendConfig = array_merge($frontendConfig, $this->getFrontendUiConfig());
        }

        return $frontendConfig;
    }

    /**
     * Return parameters specific to this payment type
     *
     * @param  Order|null $order
     * @return array
     */
    public function getPaymentSpecificParameters(?Order $order = null)
    {
        $parameters = parent::getPaymentSpecificParameters($order);

        $parameters['authorizationMode'] = $this->isAutomaticCapture() ? "SALE" : "PRE_AUTHORIZATION";
        $parameters['paymentProcessingToken'] = $this->getInfoInstance()->getAdditionalInformation('pseudocardpan');
        $parameters['transactionChannel'] = 'ECOMMERCE';
        $parameters['card']['cardholderName'] = $this->getInfoInstance()->getAdditionalInformation('cardholder');
        $parameters['returnUrl'] = $this->getReturnUrl($order);

        /*
          "authorizationMode": "SALE",
          "paymentProcessingToken": "9410000000000001950",
          "transactionChannel": "ECOMMERCE",
          "paymentProductId": 1,
          "card": {
            "cardholderName": "Wile E.Coyote"
          },
          "returnUrl": "https://pmi.prelive.pay1-test.de/system/test/api_test.php?exit=success&txid=__txid__"
         */

        return $parameters;
    }
}
