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

namespace Payone\CommercePlatform\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Finds orders by their PAYONE references
 */
class OrderLocator
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    /**
     * Returns the order belonging to the given PAYONE payment id, null if there is none
     *
     * @param string $paymentId
     * @return Order|null
     */
    public function getByPayonePaymentId(string $paymentId): ?Order
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('payone_cp_payment_id', $paymentId)
            ->setPageSize(1)
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();
        $order = reset($orders);
        return $order instanceof Order ? $order : null;
    }
}
