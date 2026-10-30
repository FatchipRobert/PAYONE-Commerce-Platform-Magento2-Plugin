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

namespace Payone\CommercePlatform\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Payone\CommercePlatform\Model\Config\EncryptedFile as EncryptedFileValue;

/**
 * Backend model of the Apple Pay certificate / private key upload fields: stores name and content of the uploaded
 * file encrypted in the config value (see Model\Config\EncryptedFile), "Delete File" empties it
 *
 * One virtualType per file type in di.xml (argument fileType). A string value (bin/magento config:set with the
 * file content) is stored as file "config:set".
 */
class EncryptedFile extends Value
{
    public const TYPE_CERTIFICATE = 'certificate';
    public const TYPE_PRIVATE_KEY = 'private_key';

    private const MAX_SIZE = 65536;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param EncryptedFileValue $encryptedFile
     * @param File $fileDriver
     * @param string $fileType
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly EncryptedFileValue $encryptedFile,
        private readonly File $fileDriver,
        private readonly string $fileType = self::TYPE_CERTIFICATE,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Encodes an uploaded file, keeps the current value if nothing was uploaded
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = $this->getValue();

        if (!is_array($value)) {
            $value = (string)$value;
            if ($value !== '' && $this->encryptedFile->decode($value) === null) {
                $this->setValue($this->encryptedFile->encode('config:set', $this->normalize($value)));
            }
            return parent::beforeSave();
        }

        if (!empty($value['tmp_name'])) {
            $this->setValue($this->encryptedFile->encode(
                (string)($value['name'] ?? ''), // PHP already strips the path from upload names
                $this->normalize($this->readUpload($value))
            ));
        } elseif (!empty($value['delete'])) {
            $this->setValue('');
        } else {
            $this->setValue($this->getOldValue()); // the form only sends the file name
        }
        return parent::beforeSave();
    }

    /**
     * Returns the content of the uploaded file
     *
     * @param array $upload
     * @return string
     * @throws LocalizedException
     */
    private function readUpload(array $upload): string
    {
        $size = (int)($upload['size'] ?? 0);
        if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $size === 0 || $size > self::MAX_SIZE) {
            throw new LocalizedException(__('The file %1 could not be uploaded.', $upload['name'] ?? ''));
        }
        return $this->fileDriver->fileGetContents((string)$upload['tmp_name']);
    }

    /**
     * Validates the file content and returns it in PEM format
     *
     * @param string $content
     * @return string
     * @throws LocalizedException
     */
    private function normalize(string $content): string
    {
        if ($this->fileType === self::TYPE_PRIVATE_KEY) {
            if (!str_contains($content, '-----BEGIN') || !str_contains($content, 'PRIVATE KEY-----')) {
                throw new LocalizedException(__('The private key file must be in PEM format (-----BEGIN PRIVATE KEY-----).'));
            }
            return trim($content)."\n";
        }

        // Apple's download merchant_id.cer is DER encoded
        if (!str_contains($content, '-----BEGIN')) {
            $content = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($content), 64, "\n")
                ."-----END CERTIFICATE-----\n";
        }
        try {
            $isValid = openssl_x509_read($content) !== false;
        } catch (\Exception $e) {
            $isValid = false; // developer mode turns the openssl warning into an exception
        }
        if (!$isValid) {
            throw new LocalizedException(__('The certificate file is not a valid certificate (PEM or DER).'));
        }
        return trim($content)."\n";
    }
}
