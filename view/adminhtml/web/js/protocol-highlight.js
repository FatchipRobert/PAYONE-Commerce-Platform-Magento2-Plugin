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
define([
    'Payone_CommercePlatform/js/lib/highlight.min'
], function (hljs) {
    'use strict';

    /**
     * Syntax highlighting for the request/response JSON in the protocol detail views
     */
    return function () {
        hljs.highlightAll();
    };
});
