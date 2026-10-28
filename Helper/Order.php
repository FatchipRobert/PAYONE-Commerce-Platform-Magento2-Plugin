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

namespace Payone\CommercePlatform\Helper;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\Order as CoreOrder;

class Order extends Base
{
    /**
     * Suffix of the Magento capture transaction id for automatic captures (SALE event at PAYONE, no separate capture id)
     */
    public const TRANSACTION_SUFFIX_SALE = '-sale';
    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var CoreOrder\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context                 $context
     * @param \Magento\Store\Model\StoreManagerInterface            $storeManager
     * @param \Payone\CommercePlatform\Helper\Shop                  $shopHelper
     * @param \Magento\Framework\App\State                          $state
     * @param \Magento\Sales\Model\Service\InvoiceService           $invoiceService
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param OrderCollectionFactory                                $orderCollectionFactory
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Payone\CommercePlatform\Helper\Shop $shopHelper,
        \Magento\Framework\App\State $state,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        parent::__construct($context, $storeManager, $shopHelper, $state);
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
    }

    /**
     * Loads the order belonging to the given PAYONE payment id
     *
     * @param string $paymentId
     * @return CoreOrder|null
     */
    public function getOrderByPayonePaymentId($paymentId)
    {
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('payone_cp_payment_id', $paymentId);
        $order = $orderCollection->getFirstItem();

        if (!empty($order->getId())) {
            return $order;
        }
        return null; // order not found
    }

    /**
     * Returns the transaction id used for automatic captures (SALE)
     *
     * @param CoreOrder $order
     * @return string
     */
    public function getSaleTransactionId(CoreOrder $order)
    {
        return $order->getData('payone_cp_payment_id').self::TRANSACTION_SUFFIX_SALE;
    }

    /**
     * Registers the automatic capture (SALE) reported by the payment.captured webhook
     *
     * Magento creates a paid invoice and a capture transaction below the authorization and closes the authorization
     *
     * @param CoreOrder $order
     * @return void
     */
    public function createInvoice(CoreOrder $order)
    {
        $payment = $order->getPayment();
        $methodInstance = $payment->getMethodInstance();
        if ($methodInstance->isAutomaticCapture() !== true || $order->getInvoiceCollection()->count() > 0) {
            return;
        }

        // A preset transaction id is kept by Magento, but then the parent is not set automatically
        $payment->setTransactionId($this->getSaleTransactionId($order));
        $authorizationTransaction = $payment->getAuthorizationTransaction();
        if ($authorizationTransaction) {
            $payment->setParentTransactionId($authorizationTransaction->getTxnId());
        }
        $payment->registerCaptureNotification($order->getBaseTotalDue(), true);

        $order->save(); // saves the invoice and the transaction as well

        $invoice = $payment->getCreatedInvoice();
        if ($invoice) {
            $this->invoiceSender->send($invoice);
        }
    }
}
