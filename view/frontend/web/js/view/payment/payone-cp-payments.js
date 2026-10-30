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
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
        'Magento_Ui/js/model/messageList'
    ],
    function (
        Component,
        rendererList,
        messageList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'payone_cp_creditcard',
                component: 'Payone_CommercePlatform/js/view/payment/method-renderer/creditcard-cp-method'
            },
            {
                type: 'payone_cp_directdebit',
                component: 'Payone_CommercePlatform/js/view/payment/method-renderer/directdebit-cp-method'
            },
            {
                type: 'payone_cp_paypal',
                component: 'Payone_CommercePlatform/js/view/payment/method-renderer/paypal-cp-method'
            },
            {
                type: 'payone_cp_googlepay',
                component: 'Payone_CommercePlatform/js/view/payment/method-renderer/googlepay-cp-method'
            },
            {
                type: 'payone_cp_applepay',
                component: 'Payone_CommercePlatform/js/view/payment/method-renderer/applepay-cp-method'
            },
            {
                type: 'payone_cp_securedinvoice',
                component: 'Payone_CommercePlatform/js/view/payment/method-renderer/securedinvoice-cp-method'
            },
            {
                type: 'payone_cp_secureddirectdebit',
                component: 'Payone_CommercePlatform/js/view/payment/method-renderer/secureddirectdebit-cp-method'
            }
        );
        // Message from the return of a redirect payment (e.g. cancelled payment) - shown in the checkout message area
        var checkoutErrorMessage = window.checkoutConfig.payment.payone_cp.checkoutErrorMessage;
        if (checkoutErrorMessage) {
            messageList.addErrorMessage({message: checkoutErrorMessage});
        }

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
