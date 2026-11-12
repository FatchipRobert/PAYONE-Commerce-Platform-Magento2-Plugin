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

namespace Payone\CommercePlatform\Test\Unit\Model\Webhook;

use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\IpAddressList;
use Payone\CommercePlatform\Model\Webhook\IpValidator;
use PHPUnit\Framework\TestCase;

/**
 * The matching itself is tested in IpAddressListTest
 */
class IpValidatorTest extends TestCase
{
    private function isValid(array $whitelist, string $ip): bool
    {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getValidIps')->willReturn($whitelist);
        return (new IpValidator($generalConfig, new IpAddressList()))->isValid($ip);
    }

    public function testEmptyWhitelistAllowsEveryIp(): void
    {
        $this->assertTrue($this->isValid([], '198.51.100.1'));
    }

    public function testIpMustBeInTheWhitelist(): void
    {
        $this->assertTrue($this->isValid(['185.60.20.0/24'], '185.60.20.1'));
        $this->assertFalse($this->isValid(['185.60.20.0/24'], '185.60.21.1'));
    }
}
