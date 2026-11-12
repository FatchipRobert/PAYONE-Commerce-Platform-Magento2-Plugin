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

use Magento\Framework\App\Request\Http;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payone\CommercePlatform\Gateway\Config\GeneralConfig;
use Payone\CommercePlatform\Model\Webhook\SignatureValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SignatureValidatorTest extends TestCase
{
    private const BODY = '{"id":"EV1","type":"payment.captured"}';

    /**
     * @var SignatureValidator
     */
    private SignatureValidator $validator;

    protected function setUp(): void
    {
        $credentials = [
            'admin' => ['', ''],
            'default' => ['KEY_DE', 'SECRET_DE'],
            'en' => ['KEY_EN', 'SECRET_EN'],
            'broken' => ['KEY_NO_SECRET', ''],
        ];
        $stores = [];
        foreach (array_keys($credentials) as $code) {
            $store = $this->createStub(StoreInterface::class);
            $store->method('getCode')->willReturn($code);
            $stores[] = $store;
        }
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);
        $generalConfig = $this->createStub(GeneralConfig::class);
        $generalConfig->method('getApiKey')->willReturnCallback(static fn ($code) => $credentials[$code][0]);
        $generalConfig->method('getApiSecret')->willReturnCallback(static fn ($code) => $credentials[$code][1]);

        $this->validator = new SignatureValidator($generalConfig, $storeManager);
    }

    private function createRequest(?string $keyId, ?string $signature): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('getHeader')->willReturnCallback(static fn ($name) => match ($name) {
            SignatureValidator::HEADER_KEY_ID => $keyId ?? false,
            SignatureValidator::HEADER_SIGNATURE => $signature ?? false,
            default => false,
        });
        return $request;
    }

    public function testCalculateSignatureIsBase64HmacSha256(): void
    {
        $this->assertSame(
            base64_encode(hash_hmac('sha256', self::BODY, 'SECRET_DE', true)),
            $this->validator->calculateSignature(self::BODY, 'SECRET_DE')
        );
    }

    public function testValidSignatureOfAnyStore(): void
    {
        $signature = $this->validator->calculateSignature(self::BODY, 'SECRET_EN');

        $this->assertTrue($this->validator->isValid($this->createRequest(' KEY_EN ', $signature.' '), self::BODY));
    }

    public static function invalidProvider(): array
    {
        $validSignature = base64_encode(hash_hmac('sha256', self::BODY, 'SECRET_DE', true));
        return [
            'no headers' => [null, null, self::BODY],
            'no signature' => ['KEY_DE', null, self::BODY],
            'no key id' => [null, $validSignature, self::BODY],
            'unknown key' => ['KEY_XX', $validSignature, self::BODY],
            'key without secret' => ['KEY_NO_SECRET', base64_encode(hash_hmac('sha256', self::BODY, '', true)), self::BODY],
            'signature of other key' => ['KEY_EN', $validSignature, self::BODY],
            'modified body' => ['KEY_DE', $validSignature, self::BODY.' '],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidSignature(?string $keyId, ?string $signature, string $body): void
    {
        $this->assertFalse($this->validator->isValid($this->createRequest($keyId, $signature), $body));
    }
}
