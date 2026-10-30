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
        'Payone_CommercePlatform/js/view/payment/method-renderer/bnpl-base',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'mage/translate'
    ],
    function (Component, $, quote, $t) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Payone_CommercePlatform/payment/secureddirectdebit',
                b2bAllowed: false,
                accountholder: '',
                iban: ''
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'accountholder',
                        'iban'
                    ]);
                return this;
            },
            initialize: function () {
                let parentReturn = this._super();
                let billingAddress = quote.billingAddress();
                if (this.accountholder() === '' && billingAddress !== null) {
                    this.accountholder(billingAddress.firstname + ' ' + billingAddress.lastname);
                }
                return parentReturn;
            },

            /**
             * IBAN without spaces and other separators, upper case
             */
            getCleanedIban: function () {
                return String(this.iban()).replace(/[^A-Za-z0-9]/g, '').toUpperCase();
            },

            validate: function () {
                if ($.trim(this.accountholder()) === '') {
                    this.messageContainer.addErrorMessage({'message': $t('Please enter a valid account holder.')});
                    return false;
                }
                if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/.test(this.getCleanedIban())) {
                    this.messageContainer.addErrorMessage({'message': $t('Please enter a valid IBAN.')});
                    return false;
                }
                return this._super();
            },

            getData: function () {
                let parentReturn = this._super();
                parentReturn.additional_data.accountholder = $.trim(this.accountholder());
                parentReturn.additional_data.iban = this.getCleanedIban();
                return parentReturn;
            }
        });
    }
);
