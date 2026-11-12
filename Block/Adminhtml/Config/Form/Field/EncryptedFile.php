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

namespace Payone\CommercePlatform\Block\Adminhtml\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Payone\CommercePlatform\Model\Config\EncryptedFile as EncryptedFileValue;

/**
 * Upload field of an encrypted file (Model\Config\Backend\EncryptedFile): shows only the file name next to
 * "Delete File" instead of the encrypted config value
 */
class EncryptedFile extends Field
{
    /**
     * @param Context $context
     * @param EncryptedFileValue $encryptedFile
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly EncryptedFileValue $encryptedFile,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Replaces the value by the file name
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $file = $this->encryptedFile->decode((string)$element->getValue());
        $element->setValue($file['name'] ?? '');
        return parent::_getElementHtml($element);
    }
}
