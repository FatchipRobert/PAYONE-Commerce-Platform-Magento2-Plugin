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

namespace Payone\CommercePlatform\Test\Unit\Model\Source;

use Payone\CommercePlatform\Model\Source\ApplePayNetworks;
use Payone\CommercePlatform\Model\Source\ButtonTextCase;
use Payone\CommercePlatform\Model\Source\CreditcardTypes;
use Payone\CommercePlatform\Model\Source\Environment;
use PHPUnit\Framework\TestCase;

/**
 * The option values are stored in the config and read by the API calls / checkout JS
 */
class SourceModelsTest extends TestCase
{
    private static function values(array $options): array
    {
        return array_column($options, 'value');
    }

    public function testEnvironmentLiveValueMatchesGeneralConfig(): void
    {
        $options = (new Environment())->toOptionArray();

        $this->assertSame(['live', 'test'], self::values($options));
        $this->assertSame(\Payone\CommercePlatform\Gateway\Config\GeneralConfig::ENVIRONMENT_LIVE, $options[0]['value']);
    }

    public function testApplePayNetworksUseApplePayJsNames(): void
    {
        $this->assertSame(['visa', 'masterCard', 'girocard'], self::values((new ApplePayNetworks())->toOptionArray()));
    }

    public function testButtonTextCase(): void
    {
        $this->assertSame(['capitalize', 'uppercase'], self::values((new ButtonTextCase())->toOptionArray()));
    }

    public function testCreditcardTypes(): void
    {
        $source = new CreditcardTypes();

        $this->assertSame(
            [
                ['value' => 'visa', 'label' => 'Visa'],
                ['value' => 'mastercard', 'label' => 'Mastercard'],
                ['value' => 'americanexpress', 'label' => 'American Express'],
                ['value' => 'dinersclub', 'label' => 'Diners Club'],
            ],
            $source->toOptionArray()
        );
        $this->assertSame(4, $source->getCreditcardTypes()['americanexpress']['cvc_length']);
    }
}
