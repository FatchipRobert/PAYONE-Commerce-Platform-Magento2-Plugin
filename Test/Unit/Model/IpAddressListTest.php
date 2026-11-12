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

namespace Payone\CommercePlatform\Test\Unit\Model;

use Payone\CommercePlatform\Model\IpAddressList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IpAddressListTest extends TestCase
{
    public static function listProvider(): array
    {
        $list = ['185.60.20.0/24', '213.178.72.196', '213.178.73.*', '10.0.0.0/8', '0.0.0.0/0x'];
        return [
            'exact ip' => [$list, '213.178.72.196', true],
            'other ip' => [$list, '213.178.72.197', false],
            'in cidr /24' => [$list, '185.60.20.255', true],
            'outside cidr /24' => [$list, '185.60.21.0', false],
            'in cidr /8' => [$list, '10.200.1.1', true],
            'wildcard' => [$list, '213.178.73.12', true],
            'wildcard does not match more digits' => [$list, '213.178.73.1234', false],
            'wildcard does not match other prefix' => [$list, '213.178.74.12', false],
            'cidr /32' => [['198.51.100.7/32'], '198.51.100.7', true],
            'cidr /0 contains everything' => [['0.0.0.0/0'], '198.51.100.7', true],
            'invalid prefix length' => [['198.51.100.0/33'], '198.51.100.7', false],
            'invalid subnet' => [['198.51.100/24'], '198.51.100.7', false],
            'missing prefix length' => [['198.51.100.0/'], '198.51.100.7', false],
            'invalid ip' => [['198.51.100.0/24'], 'unknown', false],
            'ipv6' => [['198.51.100.0/24'], '2001:db8::1', false],
            'empty list' => [[], '198.51.100.7', false],
        ];
    }

    #[DataProvider('listProvider')]
    public function testContains(array $list, string $ip, bool $expected): void
    {
        $this->assertSame($expected, (new IpAddressList())->contains($list, $ip));
    }
}
