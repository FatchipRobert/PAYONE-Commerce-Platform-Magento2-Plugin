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

namespace Payone\CommercePlatform\Controller\Onepage;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect as RedirectResult;
use Magento\Framework\Controller\Result\RedirectFactory;

/**
 * Controller for redirect payment types (payone_cp/onepage/redirect)
 */
class Redirect implements HttpGetActionInterface
{
    /**
     * @param Session $checkoutSession
     * @param RedirectFactory $resultRedirectFactory
     */
    public function __construct(
        private readonly Session $checkoutSession,
        private readonly RedirectFactory $resultRedirectFactory,
    ) {
    }

    /**
     * Redirect to payment-provider or to success page
     *
     * @return RedirectResult
     */
    public function execute(): RedirectResult
    {
        $payoneRedirectUrl = (string)$this->checkoutSession->getPayoneRedirectUrl();
        if ($payoneRedirectUrl !== '') {
            return $this->resultRedirectFactory->create()->setUrl($payoneRedirectUrl);
        }
        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }
}
