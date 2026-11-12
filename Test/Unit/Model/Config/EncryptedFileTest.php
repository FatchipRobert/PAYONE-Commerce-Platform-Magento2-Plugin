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

namespace Payone\CommercePlatform\Test\Unit\Model\Config;

use Payone\CommercePlatform\Model\Config\EncryptedFile;
use Payone\CommercePlatform\Test\Unit\Helper\EncryptorStubTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EncryptedFileTest extends TestCase
{
    use EncryptorStubTrait;

    public function testEncodeAndDecode(): void
    {
        $encryptedFile = new EncryptedFile($this->createEncryptor());

        $value = $encryptedFile->encode('merchant_id.pem', "-----BEGIN CERTIFICATE-----\nABC\n");

        $this->assertStringStartsWith('enc:', $value);
        $this->assertSame(
            ['name' => 'merchant_id.pem', 'content' => "-----BEGIN CERTIFICATE-----\nABC\n"],
            $encryptedFile->decode($value)
        );
    }

    public static function invalidValueProvider(): array
    {
        return [
            'empty' => [''],
            'not encrypted (plain file content)' => ['-----BEGIN CERTIFICATE-----'],
            'encrypted, but no json' => ['enc:'.base64_encode('abc')],
            'json without content' => ['enc:'.base64_encode('{"name":"x"}')],
            'json without name' => ['enc:'.base64_encode('{"content":"x"}')],
        ];
    }

    #[DataProvider('invalidValueProvider')]
    public function testDecodeInvalidValues(string $value): void
    {
        $this->assertNull((new EncryptedFile($this->createEncryptor()))->decode($value));
    }
}
