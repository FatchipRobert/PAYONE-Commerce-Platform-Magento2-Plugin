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
        'Payone_CommercePlatform/js/view/payment/method-renderer/base'
    ],
    function (Component) {
        'use strict';
        // Pay in Store: no input, no redirect - the order is placed directly (payment in the store)
        return Component.extend({
            defaults: {
                template: 'Payone_CommercePlatform/payment/payinstore'
            }
        });
    }
);
