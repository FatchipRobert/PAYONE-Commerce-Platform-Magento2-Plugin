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

namespace Payone\CommercePlatform\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Payone\CommercePlatform\Model\Config\Backend\EncryptedFile;
use Payone\CommercePlatform\Model\Config\EncryptedFile as EncryptedFileValue;
use Payone\CommercePlatform\Test\Unit\Helper\EncryptorStubTrait;
use PHPUnit\Framework\TestCase;

class EncryptedFileTest extends TestCase
{
    use EncryptorStubTrait;

    /**
     * @var ?array
     */
    private static ?array $pem = null;
    /**
     * @var EncryptedFileValue
     */
    private EncryptedFileValue $encryptedFileValue;
    /**
     * @var array
     */
    private array $uploads = [];

    /**
     * Self-signed certificate and its key, created once (EC key: fast)
     *
     * @return array{certificate: string, key: string}
     */
    private static function getPem(): array
    {
        if (self::$pem === null) {
            $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
            $csr = openssl_csr_new(['commonName' => 'merchant.com.example'], $key);
            openssl_x509_export(openssl_csr_sign($csr, null, $key, 1), $certificate);
            openssl_pkey_export($key, $privateKey);
            self::$pem = ['certificate' => $certificate, 'key' => $privateKey];
        }
        return self::$pem;
    }

    private function createBackend(string $fileType, mixed $value, ?string $oldValue = null): EncryptedFile
    {
        $this->encryptedFileValue = new EncryptedFileValue($this->createEncryptor());
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn($oldValue);
        $fileDriver = $this->createStub(File::class);
        $fileDriver->method('fileGetContents')->willReturnCallback(fn (string $path) => $this->uploads[$path]);

        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $backend = new EncryptedFile(
            $context,
            $this->createStub(Registry::class),
            $config,
            $this->createStub(TypeListInterface::class),
            $this->encryptedFileValue,
            $fileDriver,
            $fileType
        );
        $backend->setValue($value);
        return $backend;
    }

    private function upload(string $name, string $content, int $error = UPLOAD_ERR_OK): array
    {
        $tmpName = '/tmp/php'.count($this->uploads);
        $this->uploads[$tmpName] = $content;
        return ['name' => $name, 'tmp_name' => $tmpName, 'size' => strlen($content), 'error' => $error];
    }

    private function saveAndDecode(EncryptedFile $backend): ?array
    {
        $backend->beforeSave();
        return $this->encryptedFileValue->decode((string)$backend->getValue());
    }

    public function testUploadedPemCertificateIsStoredEncrypted(): void
    {
        $certificate = self::getPem()['certificate'];
        $backend = $this->createBackend(EncryptedFile::TYPE_CERTIFICATE, $this->upload('merchant_id.pem', "\n".$certificate."\n\n"));

        $this->assertSame(['name' => 'merchant_id.pem', 'content' => trim($certificate)."\n"], $this->saveAndDecode($backend));
    }

    public function testDerCertificateIsConvertedToPem(): void
    {
        $certificate = self::getPem()['certificate'];
        $der = base64_decode(preg_replace('/-----[A-Z ]+-----|\s/', '', $certificate));
        $backend = $this->createBackend(EncryptedFile::TYPE_CERTIFICATE, $this->upload('merchant_id.cer', $der));

        $content = $this->saveAndDecode($backend)['content'];

        $this->assertStringStartsWith("-----BEGIN CERTIFICATE-----\n", $content);
        $this->assertSame(
            openssl_x509_fingerprint($certificate),
            openssl_x509_fingerprint($content)
        );
    }

    public function testInvalidCertificateIsRejected(): void
    {
        $backend = $this->createBackend(EncryptedFile::TYPE_CERTIFICATE, $this->upload('x.pem', "-----BEGIN CERTIFICATE-----\nabc\n"));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The certificate file is not a valid certificate (PEM or DER).');
        $backend->beforeSave();
    }

    public function testPrivateKeyIsStoredEncrypted(): void
    {
        $key = self::getPem()['key'];
        $backend = $this->createBackend(EncryptedFile::TYPE_PRIVATE_KEY, $this->upload('key.pem', $key));

        $this->assertSame(['name' => 'key.pem', 'content' => trim($key)."\n"], $this->saveAndDecode($backend));
    }

    public function testPrivateKeyMustBePem(): void
    {
        $backend = $this->createBackend(EncryptedFile::TYPE_PRIVATE_KEY, $this->upload('key.der', 'binary'));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The private key file must be in PEM format');
        $backend->beforeSave();
    }

    public static function failedUploadProvider(): array
    {
        return [
            'upload error' => [UPLOAD_ERR_PARTIAL, 'content'],
            'empty file' => [UPLOAD_ERR_OK, ''],
            'too large' => [UPLOAD_ERR_OK, str_repeat('x', 65537)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('failedUploadProvider')]
    public function testFailedUploadIsRejected(int $error, string $content): void
    {
        $backend = $this->createBackend(EncryptedFile::TYPE_PRIVATE_KEY, $this->upload('key.pem', $content, $error));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The file key.pem could not be uploaded.');
        $backend->beforeSave();
    }

    public function testDeleteCheckboxRemovesTheFile(): void
    {
        $backend = $this->createBackend(EncryptedFile::TYPE_CERTIFICATE, ['value' => 'old.pem', 'delete' => '1'], 'enc:old');
        $backend->beforeSave();

        $this->assertSame('', $backend->getValue());
    }

    public function testFormWithoutUploadKeepsTheStoredFile(): void
    {
        $backend = $this->createBackend(EncryptedFile::TYPE_CERTIFICATE, ['value' => 'old.pem'], 'enc:old');
        $backend->beforeSave();

        $this->assertSame('enc:old', $backend->getValue());
    }

    public function testPlainValueFromConfigSetIsEncrypted(): void
    {
        $key = self::getPem()['key'];
        $backend = $this->createBackend(EncryptedFile::TYPE_PRIVATE_KEY, $key);

        $this->assertSame(['name' => 'config:set', 'content' => trim($key)."\n"], $this->saveAndDecode($backend));
    }

    public function testAlreadyEncryptedOrEmptyStringValueIsKept(): void
    {
        $encrypted = (new EncryptedFileValue($this->createEncryptor()))->encode('key.pem', 'x');
        foreach ([$encrypted, '', null] as $value) {
            $backend = $this->createBackend(EncryptedFile::TYPE_PRIVATE_KEY, $value);
            $backend->beforeSave();

            $this->assertSame($value, $backend->getValue());
        }
    }
}
