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

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Backend model of the reference prefix: checks length and characters
 *
 * PAYONE allows 20 characters for the merchant references of order, capture and refund:
 * type prefix (2) + reference prefix (max. 6) + increment id (9, 10 in further store views) + invoice counter
 * of the 2nd and following captures ("-2") = 20.
 * The characters are those of the SEPA mandate reference (uniqueMandateReference), which contains the prefix too.
 */
class RefPrefix extends Value
{
    public const MAX_LENGTH = 6;

    private const PATTERN = '/^[A-Za-z0-9+\-.()]*$/';

    /**
     * Removes surrounding spaces and validates the prefix (empty = no prefix)
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string)$this->getValue());
        if (strlen($value) > self::MAX_LENGTH || !preg_match(self::PATTERN, $value)) {
            throw new LocalizedException(__(
                'The reference prefix "%1" is invalid: up to %2 characters, allowed are letters, digits and + - . ( )',
                $value,
                self::MAX_LENGTH
            ));
        }
        $this->setValue($value);
        return parent::beforeSave();
    }
}
