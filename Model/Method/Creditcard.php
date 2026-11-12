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

use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Sales\Model\Order\Payment;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\Request\ReturnUrl;
use Payone\CommercePlatform\Model\Payone;
use Psr\Log\LoggerInterface;

/**
 * Credit card (redirect method because of 3DS): hosted tokenization form of PAYONE, optionally with Click to Pay
 *
 * The card type of the form determines the payment product id, PAYONE_PRODUCT_ID is the fallback.
 */
class Creditcard extends PayoneMethod
{
    protected const CODE = Payone::METHOD_CREDITCARD;
    protected const PAYONE_METHOD_TYPE = 'cardPaymentMethod';
    protected const PAYONE_PRODUCT_ID = 1;

    /**
     * PAYONE payment product ids of the card types of the tokenization form
     */
    public const PAYMENT_PRODUCT_IDS = [
        'VISA' => 1,
        'AMERICAN_EXPRESS' => 2,
        'MASTERCARD' => 3,
        'DINERS' => 132,
    ];

    protected const ADDITIONAL_DATA_KEYS = ['pseudocardpan', 'cardholder', 'cardtype', 'cardinputmode'];
    protected const MASKED_DATA_KEYS = ['pseudocardpan'];

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
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
     * @param GeneralConfig $generalConfig
     * @param ReturnUrl $returnUrl
     * @param ValidatorPoolInterface|null $validatorPool
     */
    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        string $formBlockType,
        string $infoBlockType,
        CommandPoolInterface $commandPool,
        LoggerInterface $logger,
        private readonly GeneralConfig $generalConfig,
        private readonly ReturnUrl $returnUrl,
        ?ValidatorPoolInterface $validatorPool = null,
    ) {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $logger,
            $validatorPool
        );
    }

    /**
     * Click to Pay and the styling of the hosted tokenization form
     *
     * @return array
     */
    public function getFrontendConfig(): array
    {
        $code = $this->getCode();
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

        return $config;
    }

    /**
     * Card specific input: token of the tokenization form, card holder, 3DS return URL
     *
     * @param Payment $payment
     * @return array
     */
    public function getSpecificInput(Payment $payment): array
    {
        $order = $payment->getOrder();

        $input = [
            'authorizationMode' => $this->isAutomaticCapture($order->getStoreId()) ? 'SALE' : 'PRE_AUTHORIZATION',
            'paymentProcessingToken' => $payment->getAdditionalInformation('pseudocardpan'),
            'transactionChannel' => 'ECOMMERCE',
            'card' => [
                'cardholderName' => $payment->getAdditionalInformation('cardholder'),
            ],
            'returnUrl' => $this->returnUrl->get($order),
        ];

        $cardType = (string)$payment->getAdditionalInformation('cardtype');
        if (isset(self::PAYMENT_PRODUCT_IDS[$cardType])) {
            $input['paymentProductId'] = self::PAYMENT_PRODUCT_IDS[$cardType];
        }

        return $input;
    }
}
