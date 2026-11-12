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

use Magento\Framework\App\Request\Http;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\IpAddressList;
use Payone\CommercePlatform\Model\RemoteIp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RemoteIpTest extends TestCase
{
    private function getIp(array $trustedProxies, ?string $remoteAddr, ?string $forwardedFor): string
    {
        $request = $this->createStub(Http::class);
        $request->method('getServer')->willReturnCallback(static fn (string $name) => match ($name) {
            'REMOTE_ADDR' => $remoteAddr,
            'HTTP_X_FORWARDED_FOR' => $forwardedFor,
            default => null,
        });
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getTrustedProxies')->willReturn($trustedProxies);

        return (new RemoteIp($request, $generalConfig, new IpAddressList()))->get();
    }

    public static function ipProvider(): array
    {
        return [
            'no trusted proxies: X-Forwarded-For is not used' => [[], '203.0.113.5', '198.51.100.1', '203.0.113.5'],
            'request from trusted proxy' => [['10.0.0.1'], '10.0.0.1', '203.0.113.5', '203.0.113.5'],
            'chain of trusted proxies' => [
                ['127.0.0.1', '10.0.0.0/8'],
                '127.0.0.1',
                '203.0.113.5, 10.1.2.3, 127.0.0.1',
                '203.0.113.5',
            ],
            'entries before the first other IP are not used' => [
                ['10.0.0.1'],
                '10.0.0.1',
                '185.60.20.1, 203.0.113.5',
                '203.0.113.5',
            ],
            'request not from a trusted proxy' => [['10.0.0.1'], '203.0.113.5', '185.60.20.1', '203.0.113.5'],
            'trusted proxy without X-Forwarded-For' => [['10.0.0.1'], '10.0.0.1', null, '10.0.0.1'],
            'only trusted proxies in the list' => [['10.0.0.0/8'], '10.0.0.1', '10.0.0.2', '10.0.0.2'],
            'no REMOTE_ADDR' => [[], null, null, ''],
        ];
    }

    #[DataProvider('ipProvider')]
    public function testGet(array $trustedProxies, ?string $remoteAddr, ?string $forwardedFor, string $expected): void
    {
        $this->assertSame($expected, $this->getIp($trustedProxies, $remoteAddr, $forwardedFor));
    }
}
