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
 * Backend model of the SEPA creditor identifier: removes spaces, upper case, checks the format PAYONE accepts
 */
class CreditorId extends Value
{
    /**
     * Pattern of mandate.creditorId in the PAYONE API (error message of the API)
     */
    private const PATTERN = '/^[A-Z]{2}[0-9]{2}[a-zA-Z0-9+?\/\-:().,\']{1,31}$/';

    /**
     * Normalizes and validates the creditor identifier
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = strtoupper((string)preg_replace('/\s+/', '', (string)$this->getValue()));
        if (!preg_match(self::PATTERN, $value)) {
            throw new LocalizedException(
                __('The creditor identifier "%1" is invalid (format e.g. DE98ZZZ09999999999).', $value)
            );
        }
        $this->setValue($value);
        return parent::beforeSave();
    }
}
