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
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/set-payment-information',
        'mage/url',
        'mage/translate',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/action/place-order'
    ],
    function (Component, $, quote, customer, additionalValidators, setPaymentInformationAction, url, $t, checkoutData, selectPaymentMethodAction, placeOrderAction) {
        'use strict';
        return Component.extend({
            redirectToPayoneController: function(path) {
                window.location.replace(url.build(path));
            },
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },
            handleRedirectAction: function(path) {
                var self = this;

                this.isPlaceOrderActionAllowed(false);
                this.getPlaceOrderDeferredObject()
                    .fail(
                        function () {
                            self.isPlaceOrderActionAllowed(true);
                        }
                    ).done(
                    function () {
                        self.afterPlaceOrder();
                        self.redirectToPayoneController(path);
                    }
                );
            },
            handleSetPaymentInformation: function(path) {
                var self = this;

                this.isPlaceOrderActionAllowed(false);

                $.when(
                    setPaymentInformationAction(this.messageContainer, self.getData())
                ).fail(
                    function () {
                        self.isPlaceOrderActionAllowed(true);
                    }
                ).done(
                    function () {
                        self.redirectToPayoneController(path);
                    }
                );
            },

            continueToPayone: function () {
                if (this.validate() && additionalValidators.validate()) {
                    this.handleRedirectAction('payone_cp/onepage/redirect/');
                    return false;
                }
            },

            isDateValid: function (year, month, day) {
                if (!$.isNumeric(year) || !$.isNumeric(month) || !$.isNumeric(day)) {
                    return false;
                }

                var birthDateString = year + "-" + month + "-" + day;
                var birthDate = new Date(birthDateString);

                if (birthDate.toString() === 'Invalid Date' || birthDate.getFullYear() !== parseInt(year) ||
                    birthDate.getMonth() + 1 !== parseInt(month) || birthDate.getDate() !== parseInt(day)) {
                    return false;
                }

                return true;
            },
            getCustomerEmail: function() {
                var email = customer.customerData.email;
                if (!customer.isLoggedIn()) {
                    email = quote.guestEmail;
                }
                return email;
            },
            getCurrency: function () {
                return quote.totals().quote_currency_code;
            },
            getOrderTotal: function () {
                let orderTotal = 0;

                let totals = quote.getTotals();
                if (totals) {
                    orderTotal = totals().grand_total;
                }
                return parseFloat(orderTotal);
            },
            getOrderTotalForAPI: function () {
                return Math.round(this.getOrderTotal() * 100);
            },
            getBillingCountry: function () {
                return quote.billingAddress().countryId.toUpperCase();
            },
            isBirthdayValid: function (year, month, day) {
                var birthDateString = year + "-" + month + "-" + day;
                var birthDate = new Date(birthDateString);
                var minDate = new Date(new Date().setYear(new Date().getFullYear() - 18));
                if (birthDate > minDate) {
                    return false;
                }

                return true;
            },
            getFrontendConfig: function () {
                if (window.checkoutConfig.payment.payone_cp[this.getCode()] !== undefined) {
                    return window.checkoutConfig.payment.payone_cp[this.getCode()];
                }
                return [];
            },
            getFrontendConfigParam: function (param, defaultReturn = '') {
                let frontendConfig = this.getFrontendConfig();
                if (param in frontendConfig) { // check if key exists
                    return frontendConfig[param];
                }
                return defaultReturn;
            },
            getGlobalFrontendConfigParam: function (param, defaultReturn = '') {
                if (window.checkoutConfig.payment.payone_cp !== undefined) {
                    if (param in window.checkoutConfig.payment.payone_cp) { // check if key exists
                        return window.checkoutConfig.payment.payone_cp[param];
                    }
                }
                return defaultReturn;
            }
        });
    }
);
