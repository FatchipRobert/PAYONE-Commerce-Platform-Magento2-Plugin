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
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/translate'
    ],
    function (Component, $, urlBuilder, storage, quote, customer, additionalValidators, $t) {
        'use strict';

        /**
         * Apple Pay on the web: the payment sheet only authorizes the payment, the order is placed with the addresses
         * of the checkout. The token of the sheet (ApplePayPayment.token) is decrypted by PAYONE, the merchant
         * validation is done by the shop (REST payone-applePaySession, Merchant Identity Certificate).
         */
        return Component.extend({
            defaults: {
                template: 'Payone_CommercePlatform/payment/applepay',
                sdkUrl: 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js',
                maxApplePayVersion: 14,
                isApplePayAvailable: false,
                paymentToken: ''
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'isApplePayAvailable',
                        'paymentToken'
                    ]);
                return this;
            },
            initialize: function () {
                this._super();
                this.initApplePay();
                return this;
            },
            initApplePay: function () {
                // No config: method not available or Apple Pay settings incomplete
                if (this.getFrontendConfigParam('countryCode') === '') {
                    return;
                }
                var self = this;
                $.getScript(this.sdkUrl).always(function () {
                    self.isApplePayAvailable(self.canMakePayments());
                });
            },
            canMakePayments: function () {
                try {
                    return !!window.ApplePaySession && window.ApplePaySession.canMakePayments();
                } catch (e) {
                    console.warn('Apple Pay could not be initialized:', e);
                }
                return false;
            },
            getButtonLocale: function () {
                return String(this.getGlobalFrontendConfigParam('fullLocale', 'de_DE')).replace('_', '-');
            },
            getApplePayVersion: function () {
                for (var version = this.maxApplePayVersion; version > 3; version--) {
                    if (window.ApplePaySession.supportsVersion(version)) {
                        return version;
                    }
                }
                return 3;
            },
            getPaymentRequest: function () {
                return {
                    countryCode: this.getFrontendConfigParam('countryCode'),
                    currencyCode: this.getCurrency(),
                    supportedNetworks: this.getFrontendConfigParam('supportedNetworks', []),
                    merchantCapabilities: ['supports3DS'],
                    total: {
                        label: this.getFrontendConfigParam('displayName'),
                        amount: this.getOrderTotal().toFixed(2),
                        type: 'final'
                    }
                };
            },
            getData: function () {
                var parentReturn = this._super();
                if (parentReturn.additional_data === null) {
                    parentReturn.additional_data = {};
                }
                parentReturn.additional_data.payment_token = this.paymentToken();
                return parentReturn;
            },

            /**
             * Click on the Apple Pay button - the session has to be started synchronously in the click handler
             */
            startApplePay: function () {
                if (!this.isPlaceOrderActionAllowed() || !this.validate() || !additionalValidators.validate()) {
                    return;
                }

                var self = this;
                var session = new window.ApplePaySession(this.getApplePayVersion(), this.getPaymentRequest());
                session.onvalidatemerchant = function (event) {
                    self.validateMerchant(session, event.validationURL);
                };
                session.onpaymentauthorized = function (event) {
                    self.paymentToken(JSON.stringify(event.payment.token));
                    self.placeApplePayOrder(session);
                };
                session.begin();
            },
            validateMerchant: function (session, validationUrl) {
                var self = this;
                var serviceUrl = urlBuilder.createUrl('/carts/mine/payone-applePaySession', {});
                if (!customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/payone-applePaySession', {
                        cartId: quote.getQuoteId()
                    });
                }

                storage.post(
                    serviceUrl,
                    JSON.stringify({cartId: quote.getQuoteId(), validationUrl: validationUrl})
                ).done(function (response) {
                    if (response.success === true && response.session) {
                        session.completeMerchantValidation(JSON.parse(response.session));
                        return;
                    }
                    self.abortApplePay(session, response.errormessage);
                }).fail(function () {
                    self.abortApplePay(session);
                });
            },
            abortApplePay: function (session, message) {
                try {
                    session.abort();
                } catch (e) {
                    // session already closed by the customer
                }
                this.messageContainer.addErrorMessage({
                    message: message || $t('Apple Pay is currently not available.')
                });
            },

            /**
             * Places the order and closes the payment sheet with the result - redirects to 3D Secure if PAYONE requires it
             */
            placeApplePayOrder: function (session) {
                var self = this;

                this.isPlaceOrderActionAllowed(false);
                this.getPlaceOrderDeferredObject()
                    .done(function () {
                        session.completePayment(window.ApplePaySession.STATUS_SUCCESS);
                        self.afterPlaceOrder();
                        self.redirectToPayoneController('payone_cp/onepage/redirect/');
                    })
                    .fail(function () {
                        session.completePayment(window.ApplePaySession.STATUS_FAILURE);
                        self.isPlaceOrderActionAllowed(true);
                    });
            }
        });
    }
);
