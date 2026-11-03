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

namespace Payone\CommercePlatform\Test\Unit\Helper;

use Magento\Framework\Encryption\EncryptorInterface;

trait EncryptorStubTrait
{
    /**
     * Encryptor double: "enc:" + base64, decrypting anything else fails like Magento's encryptor does
     *
     * @return EncryptorInterface
     */
    protected function createEncryptor(): EncryptorInterface
    {
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(static fn (string $data) => 'enc:'.base64_encode($data));
        $encryptor->method('decrypt')->willReturnCallback(static function (string $data) {
            if (!str_starts_with($data, 'enc:')) {
                throw new \Exception('Not supported cipher version');
            }
            return (string)base64_decode(substr($data, 4));
        });
        return $encryptor;
    }
}
