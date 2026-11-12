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

namespace Payone\CommercePlatform\Model\Webhook;

use Magento\Store\Model\StoreManagerInterface;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\MerchantReference;
use Payone\CommercePlatform\Model\OrderLocator;

/**
 * Recognizes webhooks of other shops that use the same PAYONE account
 *
 * PAYONE sends every webhook to all webhook URLs of the account. If every shop has its own reference prefix,
 * the merchant reference of the payment shows which shop the webhook belongs to.
 */
class ShopFilter
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param GeneralConfig $generalConfig
     * @param OrderLocator $orderLocator
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly GeneralConfig $generalConfig,
        private readonly OrderLocator $orderLocator,
    ) {
    }

    /**
     * Whether the webhook may belong to this shop
     *
     * Only false if every store view has a reference prefix, the merchant reference starts with none of them and
     * there is no order with the payment id (order placed before the prefix was changed).
     *
     * @param array $message
     * @return bool
     */
    public function isForThisShop(array $message): bool
    {
        $merchantReference = (string)($message['payment']['paymentOutput']['references']['merchantReference'] ?? '');
        if ($merchantReference === '') {
            return true;
        }

        foreach ($this->storeManager->getStores() as $store) {
            $refPrefix = $this->generalConfig->getRefPrefix((int)$store->getId());
            // Without a prefix the references of this store cannot be told apart from those of other shops
            if ($refPrefix === '' || str_starts_with($merchantReference, MerchantReference::PREFIX_ORDER.$refPrefix)) {
                return true;
            }
        }

        $paymentId = (string)($message['payment']['id'] ?? '');
        return $paymentId !== '' && $this->orderLocator->getByPayonePaymentId($paymentId) !== null;
    }
}
