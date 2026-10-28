/**
 * PAYONE Commerce Platform Magento 2 Connector
 *
 * Copyright (c) 2026 PAYONE GmbH
 *
 * This source file is subject to the MIT License that is bundled
 * with this package in the file LICENSE.
 *
 * @category  Payone
 * @package   Payone_CommercePlatform_Magento2_Plugin
 * @author    FATCHIP GmbH <support@fatchip.de>
 * @copyright 2026 PAYONE GmbH
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://www.payone.de
 */
define(
    [
        'Payone_CommercePlatform/js/view/payment/method-renderer/base',
        'jquery',
        'mage/translate',
        'Payone_CommercePlatform/js/action/getjwt',
        'Magento_Checkout/js/checkout-data',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/payment/additional-validators',
    ],
    function (Component, $, $t, getJwt, checkoutData, messageList, additionalValidators) {
        'use strict';
        return Component.extend({
            /** START MAGENTO CHECKOUT CODE **/
            defaults: {
                template: 'Payone_CommercePlatform/payment/creditcard',
                tokenDataSet: false,
                cardholder: '',
                cardtype: '',
                pseudocardpan: '',
                cardinputmode: '',
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'tokenDataSet',
                        'cardholder',
                        'cardtype',
                        'pseudocardpan',
                        'cardinputmode',
                    ]);
                return this;
            },
            getData: function () {
                var parentReturn = this._super();
                if (parentReturn.additional_data === null) {
                    parentReturn.additional_data = {};
                }
                parentReturn.additional_data.cardholder = this.cardholder();
                parentReturn.additional_data.cardtype = this.cardtype();
                parentReturn.additional_data.pseudocardpan = this.pseudocardpan();
                parentReturn.additional_data.cardinputmode = this.cardinputmode();

                return parentReturn;
            },
            initialize: function () {
                let parentReturn = this._super();
                if (checkoutData.getSelectedPaymentMethod() === this.getCode()) {
                    this.initJavascriptSDK();
                }
                return parentReturn;
            },
            selectPaymentMethod: function () {
                this.initJavascriptSDK();
                return this._super();
            },
            isPlaceOrderActionAllowedClickToPay: function () {
                var parentReturn = this.isPlaceOrderActionAllowed();

                if (this.tokenDataSet() === false) {
                    return false;
                }

                return parentReturn;
            },
            setPaymentObject: function () {
                window.payoneccv2 = this;
            },
            /** END MAGENTO CHECKOUT CODE **/

            /** START ClickToPay SHOW IFRAME CODE **/
            getDpaId: function () {
                return this.getFrontendConfigParam('dpaId');
            },
            getUiConfig: function () {
                let uiConfig = {};
                if (this.getFrontendConfigParam('uiConfigCustomizationEnabled') === "1") {
                    if (this.getFrontendConfigParam('uiConfigFormBgColor')) {
                        uiConfig.formBgColor = this.getFrontendConfigParam('uiConfigFormBgColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigFieldBgColor')) {
                        uiConfig.fieldBgColor = this.getFrontendConfigParam('uiConfigFieldBgColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigFieldBorder')) {
                        uiConfig.fieldBorder = this.getFrontendConfigParam('uiConfigFieldBorder');
                    }
                    if (this.getFrontendConfigParam('uiConfigFieldOutline')) {
                        uiConfig.fieldOutline = this.getFrontendConfigParam('uiConfigFieldOutline');
                    }
                    if (this.getFrontendConfigParam('uiConfigFieldLabelColor')) {
                        uiConfig.fieldLabelColor = this.getFrontendConfigParam('uiConfigFieldLabelColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigFieldPlaceholderColor')) {
                        uiConfig.fieldPlaceholderColor = this.getFrontendConfigParam('uiConfigFieldPlaceholderColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigFieldTextColor')) {
                        uiConfig.fieldTextColor = this.getFrontendConfigParam('uiConfigFieldTextColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigFieldErrorCodeColor')) {
                        uiConfig.fieldErrorCodeColor = this.getFrontendConfigParam('uiConfigFieldErrorCodeColor');
                    }
                }
                return uiConfig;
            },
            getCTPUiConfig: function () {
                let uiConfig = {};
                if (this.getFrontendConfigParam('uiConfigCustomizationEnabled') === "1") {
                    if (this.getFrontendConfigParam('uiConfigButtonStyle')) {
                        uiConfig.buttonStyle = this.getFrontendConfigParam('uiConfigButtonStyle');
                    }
                    if (this.getFrontendConfigParam('uiConfigButtonTextCase')) {
                        uiConfig.buttonTextCase = this.getFrontendConfigParam('uiConfigButtonTextCase');
                    }
                    if (this.getFrontendConfigParam('uiConfigButtonAndBadgeColor')) {
                        uiConfig.buttonAndBadgeColor = this.getFrontendConfigParam('uiConfigButtonAndBadgeColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigButtonFilledHoverColor')) {
                        uiConfig.buttonFilledHoverColor = this.getFrontendConfigParam('uiConfigButtonFilledHoverColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigButtonOutlinedHoverColor')) {
                        uiConfig.buttonOutlinedHoverColor = this.getFrontendConfigParam('uiConfigButtonOutlinedHoverColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigButtonDisabledColor')) {
                        uiConfig.buttonDisabledColor = this.getFrontendConfigParam('uiConfigButtonDisabledColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigCardItemActiveColor')) {
                        uiConfig.cardItemActiveColor = this.getFrontendConfigParam('uiConfigCardItemActiveColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigButtonAndBadgeTextColor')) {
                        uiConfig.buttonAndBadgeTextColor = this.getFrontendConfigParam('uiConfigButtonAndBadgeTextColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigLinkTextColor')) {
                        uiConfig.linkTextColor = this.getFrontendConfigParam('uiConfigLinkTextColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigAccentColor')) {
                        uiConfig.accentColor = this.getFrontendConfigParam('uiConfigAccentColor');
                    }
                    if (this.getFrontendConfigParam('uiConfigFontFamily')) {
                        uiConfig.fontFamily = this.getFrontendConfigParam('uiConfigFontFamily');
                    }
                    if (this.getFrontendConfigParam('uiConfigButtonAndInputRadius')) {
                        uiConfig.buttonAndInputRadius = this.getFrontendConfigParam('uiConfigButtonAndInputRadius');
                    }
                    if (this.getFrontendConfigParam('uiConfigCardItemRadius')) {
                        uiConfig.cardItemRadius = this.getFrontendConfigParam('uiConfigCardItemRadius');
                    }
                }
                return uiConfig;
            },
            getIsCTPEnabled: function () {
                if (this.getFrontendConfigParam('ctpEnabled') === "1") {
                    return true;
                }
                return false;
            },
            getIsCTPRegisterEnabled: function () {
                if (this.getFrontendConfigParam('ctpRegisterEnabled') === "1") {
                    return true;
                }
                return false;
            },
            getCTPShopName: function () {
                let shopName = this.getFrontendConfigParam('ctpShopName');
                if (shopName) {
                    return shopName;
                }
                return "";
            },
            getIframeContainerId: function () {
                return 'payone-cp-ccv2-iframe';
            },
            getConfig: async function (jwtToken) {
                let config = {
                    iframe: {
                        iframeWrapperId: this.getIframeContainerId(),
                        zIndex: 10000,
                        height: 500,
                        width: 400
                    },
                    uiConfig: this.getUiConfig(),
                    locale: this.getGlobalFrontendConfigParam('fullLocale', 'en_US'),
                    token: jwtToken,
                    email: this.getCustomerEmail(),
                    showCardholderName:true,
                    strictCardholderName:false,
                    isSecurityCodeMasked: true,
                    allowedCardSchemes: [
                        "visa",
                        "mastercard",
                        "amex",
                        "diners",
                        "jcb",
                        "discover",
                        "maestro",
                        "unionpay",
                    ],
                    CTPConfig: {
                        enableCTP: this.getIsCTPEnabled(),
                        enableCustomerOnboarding: this.getIsCTPRegisterEnabled(),
                        transactionAmount: {
                            amount: this.getOrderTotalForAPI(),
                            currencyCode: this.getCurrency(),
                        },
                        uiConfig: this.getCTPUiConfig(),
                        shopName: this.getCTPShopName(),
                        token: jwtToken,
                    }
                };
                return config;
            },
            handleTokenError: function () {
                console.error("JWT Token could not be retrieved");
                var paymentMethod = this.getCode();
                this.messageContainer.addErrorMessage({'message': $t('There has been a technical error. Please choose another payment method.')});
                $('INPUT#' + paymentMethod).parents('.payment-method').delay(7000).fadeOut(2000, function() {
                    $('INPUT#' + paymentMethod).parents('.payment-method').remove();
                });
            },
            getJsSDKUrl: function () {
                let domain = 'https://sdk.tokenization.secure.payone.com';
                if (this.getGlobalFrontendConfigParam('mode') === 'test') {
                    domain = 'https://sdk.preprod.tokenization.secure.payone.com';
                }
                return domain + '/1.7.0/hosted-tokenization-sdk.js';
            },
            initJavascriptSDK: async function () {
                if (window.HostedTokenizationSdk || document.getElementById('payone-sdk')) {
                    return;
                }

                let jwtTokenResponse = await getJwt();
                if (!jwtTokenResponse || jwtTokenResponse.success === false || !jwtTokenResponse.jwt) {
                    this.handleTokenError();
                    return;
                }

                const jwtToken = jwtTokenResponse.jwt;

                this.isPlaceOrderActionAllowed(false);

                // IMPORTANT: Disable RequireJS temporarily to prevent the SDK from reporting itself as an anonymous module
                var _oldDefine = window.define;
                window.define = undefined;

                var script = document.createElement('script');
                script.id = 'payone-sdk';
                script.src = this.getJsSDKUrl();
                script.integrity = 'sha384-En0FTt1/y/oAtSiKcVajUNbL3xKFS6znYv+TGUO6wtMrD8d//NlUJRCVA7PT44Ua';
                script.async = true;
                script.crossOrigin = "anonymous";

                var self = this;

                script.onload = function () {
                    // Restore the original define function
                    window.define = _oldDefine;

                    if (window.HostedTokenizationSdk) {
                        self.initPaymentPage(jwtToken);
                    } else {
                        console.error("HTP-SDK failed to load");
                    }
                };

                script.onerror = function () {
                    window.define = _oldDefine;
                    console.error("Error loading HTP-SDK.");
                };

                document.head.appendChild(script);
            },
            initPaymentPage: async function (jwtToken) {
                try {
                    await window.HostedTokenizationSdk.init();
                    let config = await this.getConfig(jwtToken);

                    this.setPaymentObject();
                    window.HostedTokenizationSdk.getPaymentPage(config, this.callbackFunc);
                } catch (error) {
                    console.error('Error initializing HTP-SDK:', error);
                }
            },
            /** END ClickToPay SHOW IFRAME CODE **/

            /** START ClickToPay STATUS EVENTS **/
            callbackFunc: function (statusCode, res) {
                if (statusCode === "ReadyToPay" && res === 0) { // Behaviour not documented like this, but it seems like these in combination mean "ready to pay"
                    window.payoneccv2.isPlaceOrderActionAllowed(true);
                    window.payoneccv2.tokenDataSet(true);
                }
            },
            subtmitClickToPay: function () {
                this.setPaymentObject();

                window.HostedTokenizationSdk.submitForm(this.tokenizationSuccessCallback, this.tokenizationFailureCallback);
            },
            handleCreditcardPayment: function () {
                var firstValidation = additionalValidators.validate();
                if (!(firstValidation)) {
                    return false;
                }

                if (this.validate() && firstValidation && this.pseudocardpan()) {
                    this.handleRedirectAction('payone_cp/onepage/redirect/');
                    return false;
                }
            },
            tokenizationSuccessCallback: function (statusCode, token, cardDetails, cardInputMode) {
                // this variable is missing in this context
                let self = window.payoneccv2;

                self.cardholder(cardDetails.cardholderName);
                self.cardtype(cardDetails.cardType);
                self.pseudocardpan(token);
                self.cardinputmode(cardInputMode);

                self.handleCreditcardPayment();
            },
            tokenizationFailureCallback: function (statusCode, errorResponse) {
                // This is where you can handle errors that can happen during the tokenization (e.g. expired JWTs or malformated JWTs)
                console.error("Tokenization of card failed");
            }
            /** END ClickToPay STATUS EVENTS **/
        });
    }
);
