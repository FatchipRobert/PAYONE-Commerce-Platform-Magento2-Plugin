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

namespace Payone\CommercePlatform\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\Payone;

/**
 * Checkout config of the credit card method: Click to Pay and the styling of the hosted tokenization form
 */
class CreditcardConfigProvider implements ConfigProviderInterface
{
    /**
     * Styling of the hosted tokenization form: JS key => field in payone_cp_general/creditcard
     */
    private const UI_CONFIG_FIELDS = [
        'uiConfigCustomizationEnabled' => 'customization_enabled',
        'uiConfigFormBgColor' => 'form_bg_color',
        'uiConfigFieldBgColor' => 'field_bg_color',
        'uiConfigFieldBorder' => 'field_border',
        'uiConfigFieldOutline' => 'field_outline',
        'uiConfigFieldLabelColor' => 'field_label_color',
        'uiConfigFieldPlaceholderColor' => 'field_placeholder_color',
        'uiConfigFieldTextColor' => 'field_text_color',
        'uiConfigFieldErrorCodeColor' => 'field_error_code_color',
        'uiConfigButtonStyle' => 'button_style',
        'uiConfigButtonTextCase' => 'button_text_case',
        'uiConfigButtonAndBadgeColor' => 'button_badge_color',
        'uiConfigButtonFilledHoverColor' => 'button_hover_color',
        'uiConfigButtonOutlinedHoverColor' => 'button_outlined_hover_color',
        'uiConfigButtonDisabledColor' => 'button_disabled_color',
        'uiConfigCardItemActiveColor' => 'card_item_active_color',
        'uiConfigButtonAndBadgeTextColor' => 'button_badge_text_color',
        'uiConfigLinkTextColor' => 'link_text_color',
        'uiConfigAccentColor' => 'accent_color',
        'uiConfigFontFamily' => 'font_family',
        'uiConfigButtonAndInputRadius' => 'button_input_radius',
        'uiConfigCardItemRadius' => 'card_item_radius',
    ];

    /**
     * @param PaymentHelper $paymentHelper
     * @param GeneralConfig $generalConfig
     */
    public function __construct(
        private readonly PaymentHelper $paymentHelper,
        private readonly GeneralConfig $generalConfig,
    ) {
    }

    /**
     * Returns window.checkoutConfig.payment.payone_cp.payone_cp_creditcard
     *
     * @return array
     */
    public function getConfig(): array
    {
        $code = Payone::METHOD_CREDITCARD;
        if (!$this->paymentHelper->getMethodInstance($code)->isAvailable()) {
            return [];
        }

        $config = [
            'mode' => $this->generalConfig->getEnvironment(),
            'ctpEnabled' => $this->generalConfig->getPaymentValue($code, 'clicktopay_enabled'),
            'ctpRegisterEnabled' => $this->generalConfig->getPaymentValue($code, 'clicktopay_register_enabled'),
            'ctpShopName' => $this->generalConfig->getPaymentValue($code, 'clicktopay_shopname'),
        ];
        if ($this->generalConfig->getGeneralValue('customization_enabled', 'creditcard')) {
            foreach (self::UI_CONFIG_FIELDS as $key => $field) {
                $config[$key] = $this->generalConfig->getGeneralValue($field, 'creditcard');
            }
        }

        return ['payment' => ['payone_cp' => [$code => $config]]];
    }
}
