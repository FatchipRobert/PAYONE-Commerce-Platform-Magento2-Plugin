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

namespace Payone\CommercePlatform\Model\Config;

use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Config value of an uploaded file (Apple Pay certificate / private key): file name and content, stored encrypted
 * in core_config_data instead of on the file system - works with several web servers and keeps the key encrypted
 */
class EncryptedFile
{
    /**
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly EncryptorInterface $encryptor,
    ) {
    }

    /**
     * Returns the config value for a file
     *
     * @param string $name
     * @param string $content
     * @return string
     */
    public function encode(string $name, string $content): string
    {
        return $this->encryptor->encrypt((string)json_encode(['name' => $name, 'content' => $content]));
    }

    /**
     * Returns name and content of the file, null if the value is empty or no encoded file
     *
     * @param string $value
     * @return array{name: string, content: string}|null
     */
    public function decode(string $value): ?array
    {
        if ($value === '') {
            return null;
        }
        try {
            $file = json_decode($this->encryptor->decrypt($value), true);
        } catch (\Exception $e) {
            return null; // not encrypted (e.g. the plain file content)
        }
        if (!is_array($file) || !isset($file['name'], $file['content'])) {
            return null;
        }
        return ['name' => (string)$file['name'], 'content' => (string)$file['content']];
    }
}
