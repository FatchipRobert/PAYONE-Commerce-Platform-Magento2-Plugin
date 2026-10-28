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
        'Magento_Checkout/js/model/quote',
        'Magento_Ui/js/model/messageList',
        'mage/translate'
    ],
    function (Component, quote, messageList, $t) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Payone_CommercePlatform/payment/directdebit',
                bankCountry: '',
                accountholder: '',
                iban: '',
                bic: ''
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'bankCountry',
                        'accountholder',
                        'iban',
                        'bic'
                    ]);
                return this;
            },
            initialize: function () {
                let parentReturn = this._super();
                if (this.accountholder() == '') {
                    let billingAddress = quote.billingAddress();
                    this.accountholder(billingAddress.firstname + " " + billingAddress.lastname);
                }
                return parentReturn;
            },

            validate: function () {
                if (document.getElementById(this.getCode() + '_country').value == '') {
                    this.messageContainer.addErrorMessage({'message': $t('Please choose the bank country.')});
                    return false;
                }
                if (this.iban() == '') {
                    this.messageContainer.addErrorMessage({'message': $t('Please enter a valid IBAN.')});
                    return false;
                }
                if (this.accountholder() == '') {
                    this.messageContainer.addErrorMessage({'message': $t('Please enter a valid account holder.')});
                    return false;
                }
                if (this.requestBic() == 1 && this.bic() == '') {
                    this.messageContainer.addErrorMessage({'message': $t('Please enter a valid BIC.')});
                    return false;
                }
                return true;
            },
            requestBic: function () {
                return this.getFrontendConfigParam('requestBic');
            },
            getCountries: function () {
                return this.getFrontendConfigParam('sepaCountries');
            },
            getCleanedNumber: function (dirtyNumber) {
                var cleanedNumber = '';
                var currentChar;
                for (var i = 0; i < dirtyNumber.length; i++) {
                    currentChar = dirtyNumber.charAt(i);
                    if (currentChar != ' ' && (!isNaN(currentChar) || /^[A-Za-z]/.test(currentChar))) {
                        if (/^[a-z]/.test(currentChar)) {
                            currentChar = currentChar.toUpperCase();
                        }
                        cleanedNumber = cleanedNumber + currentChar;
                    }
                }
                return cleanedNumber;
            },

            getData: function () {
                document.getElementById(this.getCode() + '_iban').value = this.getCleanedNumber(this.iban());
                if (this.requestBic() == 1) {
                    document.getElementById(this.getCode() + '_bic').value = this.getCleanedNumber(this.bic());
                }

                var parentReturn = this._super();
                if (parentReturn.additional_data === null) {
                    parentReturn.additional_data = {};
                }
                parentReturn.additional_data.bank_country = this.bankCountry();
                parentReturn.additional_data.accountholder = this.accountholder();
                parentReturn.additional_data.iban = this.getCleanedNumber(this.iban());
                parentReturn.additional_data.bic = this.getCleanedNumber(this.bic());
                return parentReturn;
            }
        });
    }
);
