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

namespace Payone\CommercePlatform\Test\Unit\Gateway\Http;

use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Gateway\Http\Signer;
use PHPUnit\Framework\TestCase;

class SignerTest extends TestCase
{
    /**
     * @var Signer
     */
    private Signer $signer;

    protected function setUp(): void
    {
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getApiKey')->willReturnCallback(static fn ($store) => 'key-'.$store);
        $generalConfig->method('getApiSecret')->willReturnCallback(static fn ($store) => 'secret-'.$store);
        $this->signer = new Signer($generalConfig);
    }

    public function testGetDateIsRfc1123InGmt(): void
    {
        $date = $this->signer->getDate();

        $this->assertMatchesRegularExpression('/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/', $date);
        $this->assertEqualsWithDelta(time(), strtotime($date), 5);
    }

    public function testGetSignData(): void
    {
        $this->assertSame(
            "POST\napplication/json; charset=utf-8\nTue, 03 Nov 2026 10:00:00 GMT\n/v1/P1/commerce-cases\n",
            $this->signer->getSignData(
                'POST',
                'application/json; charset=utf-8',
                'Tue, 03 Nov 2026 10:00:00 GMT',
                '/v1/P1/commerce-cases'
            )
        );
    }

    public function testAuthorizationHeaderIsGcsV1HmacOfStore(): void
    {
        $date = 'Tue, 03 Nov 2026 10:00:00 GMT';
        $expectedSignature = base64_encode(hash_hmac('sha256', "GET\n\n".$date."\n/v1/P1/jwt\n", 'secret-2', true));

        $this->assertSame(
            'GCS v1HMAC:key-2:'.$expectedSignature,
            $this->signer->getAuthorizationHeader('GET', '', $date, '/v1/P1/jwt', 2)
        );
    }
}
