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

namespace Payone\CommercePlatform\Test\Unit\Model\Ui;

use Magento\Checkout\Model\Session;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\Method\PayoneMethod;
use Payone\CommercePlatform\Model\Payone;
use Payone\CommercePlatform\Model\Ui\ConfigProvider;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    public function testSharedConfigAndConfigOfTheAvailableMethods(): void
    {
        $available = [Payone::METHOD_PAYPAL, Payone::METHOD_DIRECT_DEBIT, Payone::METHOD_APPLEPAY];
        $instructions = [
            Payone::METHOD_PAYPAL => "Line 1\n<b>Line 2</b>",
            Payone::METHOD_CREDITCARD => 'not available',
        ];
        $frontendConfigs = [
            Payone::METHOD_DIRECT_DEBIT => ['requestBic' => true],
            Payone::METHOD_CREDITCARD => ['not available'],
        ];
        $paymentHelper = $this->createStub(PaymentHelper::class);
        $paymentHelper->method('getMethodInstance')->willReturnCallback(
            function (string $code) use ($available, $instructions, $frontendConfigs) {
                // a method class of another module (e.g. replaced by a preference) only gets the instructions
                $method = $this->createStub($code === Payone::METHOD_APPLEPAY ? MethodInterface::class : PayoneMethod::class);
                $method->method('isAvailable')->willReturn(in_array($code, $available, true));
                $method->method('getConfigData')->willReturnCallback(
                    static fn ($field) => $field === 'instructions' ? ($instructions[$code] ?? null) : null
                );
                if ($method instanceof PayoneMethod) {
                    $method->method('getFrontendConfig')->willReturn($frontendConfigs[$code] ?? []);
                }
                return $method;
            }
        );
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn ($value) => htmlspecialchars((string)$value));
        $session = $this->createMock(Session::class);
        $session->expects($this->once())->method('getData')->with('payone_cp_checkout_error_message', true)
            ->willReturn('The payment has been cancelled.');
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getEnvironment')->willReturn('test');
        $generalConfig->method('getLocale')->willReturn('de_DE');

        $provider = new ConfigProvider($paymentHelper, $escaper, $session, $generalConfig);

        $this->assertSame(['payment' => [
            'payone_cp' => [
                'mode' => 'test',
                'fullLocale' => 'de_DE',
                'checkoutErrorMessage' => 'The payment has been cancelled.',
                Payone::METHOD_DIRECT_DEBIT => ['requestBic' => true],
            ],
            'instructions' => [
                Payone::METHOD_DIRECT_DEBIT => '',
                Payone::METHOD_PAYPAL => "Line 1<br />\n&lt;b&gt;Line 2&lt;/b&gt;",
                Payone::METHOD_APPLEPAY => '',
            ],
        ]], $provider->getConfig());
    }
}
