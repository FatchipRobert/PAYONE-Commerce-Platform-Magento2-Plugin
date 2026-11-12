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
/*jshint browser:true jquery:true*/
/*global alert*/
define([
    'jquery',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/quote'
], function ($, urlBuilder, storage, fullScreenLoader, customer, quote) {
    'use strict';
    return function () {
        var serviceUrl;

        var request = {
            cartId: quote.getQuoteId()
        };
        if (!customer.isLoggedIn()) {
            serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/payone-getJwt', {
                cartId: quote.getQuoteId()
            });
        } else {
            serviceUrl = urlBuilder.createUrl('/carts/mine/payone-getJwt', {});
        }

        fullScreenLoader.startLoader();

        return storage.post(
            serviceUrl,
            JSON.stringify(request)
        ).done(
            function (response) {
                fullScreenLoader.stopLoader();
                return response;
            }
        ).fail(
            function (response) {
                //errorProcessor.process(response, messageContainer);
                fullScreenLoader.stopLoader();
            }
        );;
    };
});
