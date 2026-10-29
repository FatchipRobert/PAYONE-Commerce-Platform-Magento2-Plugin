<?php
/**
 * PAYONE Commerce Platform Magento 2 Connector
 *
 * Copyright (c) 2026 PAYONE GmbH
 *
 * This source file is subject to the MIT License that is bundled
 * with this package in the file LICENSE.
 *
 * PHP version 8
 *
 * @category  Payone
 * @package   Payone_CommercePlatform_Magento2_Plugin
 * @author    FATCHIP GmbH <support@fatchip.de>
 * @copyright 2026 PAYONE GmbH
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://www.payone.de
 */

declare(strict_types=1);

namespace Payone\CommercePlatform\Gateway;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;

/**
 * Builds the merchant references: <type prefix><configured prefix><order increment id>, e.g. "cp000000207"
 */
class MerchantReference
{
    public const PREFIX_COMMERCE_CASE = 'cc';
    public const PREFIX_CHECKOUT = 'ck';
    public const PREFIX_ORDER = 'od';
    public const PREFIX_CAPTURE = 'cp';
    public const PREFIX_REFUND = 'rf';

    /**
     * @param GeneralConfig $generalConfig
     */
    public function __construct(
        private readonly GeneralConfig $generalConfig,
    ) {
    }

    /**
     * Returns the merchant reference of the order for the given request type
     *
     * @param Order $order
     * @param string $typePrefix one of the PREFIX_* constants
     * @return string
     */
    public function get(Order $order, string $typePrefix): string
    {
        return $typePrefix.$this->generalConfig->getRefPrefix($order->getStoreId()).$order->getIncrementId();
    }

    /**
     * Returns the merchant reference of the capture of the given invoice
     *
     * Invoices are numbered by creation: 1st invoice cp<increment_id>, 2nd cp<increment_id>-2, ...
     * Works for the invoice which is currently captured (not saved yet) and for saved invoices (refund)
     *
     * @param Order $order
     * @param Invoice $invoice
     * @return string
     */
    public function getForCapture(Order $order, Invoice $invoice): string
    {
        $position = 1;
        foreach ($order->getInvoiceCollection() as $orderInvoice) {
            if (empty($orderInvoice->getId()) || $orderInvoice->getId() == $invoice->getId()) {
                continue;
            }
            if (empty($invoice->getId()) || $orderInvoice->getId() < $invoice->getId()) {
                $position++; // invoice created before the given one
            }
        }

        $suffix = $position > 1 ? '-'.$position : '';
        return $this->get($order, self::PREFIX_CAPTURE).$suffix;
    }
}
