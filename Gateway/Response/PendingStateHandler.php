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

namespace Payone\CommercePlatform\Gateway\Response;

use Magento\Framework\DataObject;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order;

/**
 * initialize only: the order of a redirect method stays in pending_payment until the webhook authorizes it
 */
class PendingStateHandler implements HandlerInterface
{
    /**
     * Sets the state object of the initialize subject
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $stateObject = $handlingSubject['stateObject'] ?? null;
        if ($stateObject instanceof DataObject) {
            $stateObject->setData('state', Order::STATE_PENDING_PAYMENT);
            $stateObject->setData('status', 'pending_payment');
        }
    }
}
