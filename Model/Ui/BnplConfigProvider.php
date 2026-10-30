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

namespace Payone\CommercePlatform\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;

/**
 * Checkout config of a PAYONE Buy Now Pay Later method: Payla device fingerprint and links of the legal text
 *
 * One virtualType per method in etc/frontend/di.xml ($methodCode).
 * The device token "<Payla partner id>_<merchant id>_<session id>" is created once per checkout session.
 * The checkout JS loads the Payla fingerprint script with it and sends it back with the payment data
 * (paymentMethodSpecificInput.customerDevice.deviceToken).
 */
class BnplConfigProvider implements ConfigProviderInterface
{
    private const SESSION_KEY_DEVICE_SESSION_ID = 'payone_cp_device_session_id';

    /**
     * @param PaymentHelper $paymentHelper
     * @param GeneralConfig $generalConfig
     * @param Session $checkoutSession
     * @param IdentityGeneratorInterface $identityGenerator
     * @param Escaper $escaper
     * @param string $methodCode
     */
    public function __construct(
        private readonly PaymentHelper $paymentHelper,
        private readonly GeneralConfig $generalConfig,
        private readonly Session $checkoutSession,
        private readonly IdentityGeneratorInterface $identityGenerator,
        private readonly Escaper $escaper,
        private readonly string $methodCode,
    ) {
    }

    /**
     * Returns window.checkoutConfig.payment.payone_cp.<method code>
     *
     * @return array
     */
    public function getConfig(): array
    {
        $code = $this->methodCode;
        if (!$this->paymentHelper->getMethodInstance($code)->isAvailable()) {
            return [];
        }

        $partnerId = (string)$this->generalConfig->getPaymentValue($code, 'payla_partner_id');
        $merchantId = $this->generalConfig->getMerchantId();

        return ['payment' => ['payone_cp' => [$code => [
            'paylaPartnerId' => $partnerId,
            'paylaMerchantId' => $merchantId,
            'paylaEnvironment' => $this->generalConfig->isLiveMode() ? 'p' : 't',
            'deviceToken' => $partnerId.'_'.$merchantId.'_'.$this->getDeviceSessionId(),
            'legalText' => $this->getLegalText($code),
        ]]]];
    }

    /**
     * Returns the consent text with the links to the payment terms and data protection notice of PAYONE (HTML)
     *
     * @param string $code
     * @return string
     */
    private function getLegalText(string $code): string
    {
        return (string)__(
            'By placing this order, I agree to the <a href="%1" target="_blank" rel="noopener">supplementary payment terms</a>'
            .' and the performance of a risk assessment for the selected payment method.'
            .' I am aware of the <a href="%2" target="_blank" rel="noopener">supplementary data protection notice</a>.',
            $this->escaper->escapeUrl((string)$this->generalConfig->getPaymentValue($code, 'terms_url')),
            $this->escaper->escapeUrl((string)$this->generalConfig->getPaymentValue($code, 'privacy_url'))
        );
    }

    /**
     * Returns the id of the fingerprint session, created once per checkout session
     *
     * @return string
     */
    private function getDeviceSessionId(): string
    {
        $sessionId = (string)$this->checkoutSession->getData(self::SESSION_KEY_DEVICE_SESSION_ID);
        if ($sessionId === '') {
            $sessionId = $this->identityGenerator->generateId();
            $this->checkoutSession->setData(self::SESSION_KEY_DEVICE_SESSION_ID, $sessionId);
        }
        return $sessionId;
    }
}
