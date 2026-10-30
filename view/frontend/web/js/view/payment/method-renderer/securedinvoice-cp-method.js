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
        'ko',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'mage/translate'
    ],
    function (Component, $, ko, quote, customer, $t) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Payone_CommercePlatform/payment/securedinvoice',
                dateOfBirth: '',
                telephone: ''
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'dateOfBirth',
                        'telephone'
                    ]);
                return this;
            },
            initialize: function () {
                let parentReturn = this._super();
                if (this.dateOfBirth() === '' && customer.customerData.dob) {
                    this.dateOfBirth(String(customer.customerData.dob).substring(0, 10));
                }
                if (this.isChecked() === this.getCode()) {
                    this.loadFingerprintScript();
                }
                return parentReturn;
            },
            selectPaymentMethod: function () {
                let parentReturn = this._super();
                this.loadFingerprintScript();
                return parentReturn;
            },

            /**
             * Loads the Payla device fingerprint snippet once per page (mandatory for PAYONE Buy Now Pay Later)
             * The device token was created by the SecuredInvoiceConfigProvider and is sent with the payment data
             */
            loadFingerprintScript: function () {
                if (window.payoneCpPaylaLoaded === true || this.getFrontendConfigParam('deviceToken') === '') {
                    return;
                }
                window.payoneCpPaylaLoaded = true;

                let partnerId = this.getFrontendConfigParam('paylaPartnerId');
                let merchantId = this.getFrontendConfigParam('paylaMerchantId');
                let environment = this.getFrontendConfigParam('paylaEnvironment');
                let deviceToken = this.getFrontendConfigParam('deviceToken');

                $.getScript('https://d.payla.io/dcs/' + partnerId + '/' + merchantId + '/dcs.js')
                    .done(function () {
                        if (window.paylaDcs && window.paylaDcs.init) {
                            window.paylaDcs.init(environment, deviceToken);
                        }
                        $('<link>', {
                            rel: 'stylesheet',
                            type: 'text/css',
                            href: 'https://d.payla.io/dcs/dcs.css?st=' + encodeURIComponent(deviceToken) +
                                '&pi=' + encodeURIComponent(partnerId) + '&psi=' + encodeURIComponent(merchantId) +
                                '&e=' + environment
                        }).appendTo('head');
                    });
            },

            /**
             * The phone number is only asked for if the billing address has none
             */
            requestTelephone: ko.pureComputed(function () {
                let billingAddress = quote.billingAddress();
                return billingAddress !== null && !billingAddress.telephone;
            }),
            /**
             * PAYONE Secured Invoice does not allow a shipping address different from the billing address
             */
            isAddressDifferent: ko.pureComputed(function () {
                if (quote.isVirtual() || quote.billingAddress() === null || quote.shippingAddress() === null) {
                    return false;
                }
                return quote.billingAddress().getCacheKey() !== quote.shippingAddress().getCacheKey();
            }),
            getLegalText: function () {
                return this.getFrontendConfigParam('legalText');
            },
            getMaxDateOfBirth: function () {
                let maxDate = new Date();
                maxDate.setFullYear(maxDate.getFullYear() - 18);
                return maxDate.toISOString().substring(0, 10);
            },

            validate: function () {
                let dateParts = String(this.dateOfBirth()).split('-');
                if (dateParts.length !== 3 || !this.isDateValid(dateParts[0], dateParts[1], dateParts[2]) ||
                    !this.isBirthdayValid(dateParts[0], dateParts[1], dateParts[2])
                ) {
                    this.messageContainer.addErrorMessage({'message': $t('Please enter a valid date of birth. You must be at least 18 years old to use this payment method.')});
                    return false;
                }
                if (this.requestTelephone() && $.trim(this.telephone()) === '') {
                    this.messageContainer.addErrorMessage({'message': $t('Please enter your phone number.')});
                    return false;
                }
                if (this.isAddressDifferent()) {
                    this.messageContainer.addErrorMessage({'message': $t('This payment method is only available if the billing and shipping address are identical.')});
                    return false;
                }
                return true;
            },

            getData: function () {
                let parentReturn = this._super();
                if (parentReturn.additional_data === null) {
                    parentReturn.additional_data = {};
                }
                parentReturn.additional_data.dateofbirth = this.dateOfBirth();
                if (this.requestTelephone()) {
                    parentReturn.additional_data.telephone = $.trim(this.telephone());
                }
                parentReturn.additional_data.device_token = this.getFrontendConfigParam('deviceToken');
                return parentReturn;
            }
        });
    }
);
