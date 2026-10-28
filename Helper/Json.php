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

namespace Payone\CommercePlatform\Helper;

class Json extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Decodes a JSON string to an associative array
     *
     * @param string $json
     * @return array|null
     */
    public function jsonDecode($json)
    {
        if (!is_string($json)) {
            return null;
        }

        $trimmed = trim($json);
        if (empty($trimmed)) {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Recursively searches the data for the given key and returns the first value found
     *
     * @param array|null $data
     * @param string $searchField
     * @return string|null
     */
    public function findValueByKey($data, $searchField)
    {
        if (!is_array($data)) {
            return null;
        }

        foreach ($data as $key => $value) {
            if ($key === $searchField) {
                return $value;
            }

            if (is_array($value)) {
                $result = $this->findValueByKey($value, $searchField);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }
}
