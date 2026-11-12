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

namespace Payone\CommercePlatform\Gateway\Http;

use Payone\CommercePlatform\Gateway\Config\GeneralConfig;

/**
 * Creates the GCS v1HMAC authorization header of API requests
 */
class Signer
{
    private const DATE_FORMAT_RFC1123 = 'D, d M Y H:i:s T';

    /**
     * @param GeneralConfig $generalConfig
     */
    public function __construct(
        private readonly GeneralConfig $generalConfig,
    ) {
    }

    /**
     * Returns the current date for the Date header - the same value has to be used for the signature
     *
     * @return string
     */
    public function getDate(): string
    {
        return gmdate(self::DATE_FORMAT_RFC1123);
    }

    /**
     * Returns the string to sign
     *
     * @param string $httpMethod
     * @param string $contentType empty for requests without body
     * @param string $date value of the Date header
     * @param string $path endpoint path with all placeholders replaced, e.g. /v1/<merchantId>/commerce-cases
     * @return string
     */
    public function getSignData(string $httpMethod, string $contentType, string $date, string $path): string
    {
        return $httpMethod."\n".$contentType."\n".$date."\n".$path."\n";
    }

    /**
     * Returns the value of the Authorization header with the API credentials of the store
     *
     * @param string $httpMethod
     * @param string $contentType empty for requests without body
     * @param string $date value of the Date header
     * @param string $path endpoint path with all placeholders replaced
     * @param int|string|null $store
     * @return string
     */
    public function getAuthorizationHeader(
        string $httpMethod,
        string $contentType,
        string $date,
        string $path,
        int|string|null $store
    ): string {
        $signature = hash_hmac(
            'sha256',
            $this->getSignData($httpMethod, $contentType, $date, $path),
            $this->generalConfig->getApiSecret($store),
            true
        );

        return 'GCS v1HMAC:'.$this->generalConfig->getApiKey($store).':'.base64_encode($signature);
    }
}
