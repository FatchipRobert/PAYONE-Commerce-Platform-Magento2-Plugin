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

namespace Payone\CommercePlatform\Test\Unit\Model\ApplePay;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Payone\CommercePlatform\Gateway\Config\ApplePayConfig;
use Payone\CommercePlatform\Model\ApplePay\MerchantSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MerchantSessionTest extends TestCase
{
    private const VALIDATION_URL = 'https://apple-pay-gateway-cert.apple.com/paymentservices/startSession';

    /**
     * @var Curl&MockObject
     */
    private MockObject $curl;
    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;
    /**
     * @var bool
     */
    private bool $configurationComplete = true;
    /**
     * @var string
     */
    private string $keyPassword = '';
    /**
     * @var array
     */
    private array $options = [];

    private function createMerchantSession(): MerchantSession
    {
        $this->curl = $this->createMock(Curl::class);
        $this->curl->method('setOption')->willReturnCallback(function ($option, $value) {
            $this->options[$option] = $value;
        });
        $curlFactory = $this->createStub(CurlFactory::class);
        $curlFactory->method('create')->willReturn($this->curl);

        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(
            static fn ($type, $secure) => $type === UrlInterface::URL_TYPE_WEB && $secure ? 'https://shop.example.com/de/' : ''
        );
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $config = $this->createStub(ApplePayConfig::class);
        $config->method('isConfigurationComplete')->willReturnCallback(fn () => $this->configurationComplete);
        $config->method('getCertificate')->willReturn('CERT');
        $config->method('getPrivateKey')->willReturn('KEY');
        $config->method('getPrivateKeyPassword')->willReturnCallback(fn () => $this->keyPassword);
        $config->method('getMerchantIdentifier')->willReturn('merchant.com.example');
        $config->method('getDisplayName')->willReturn('My Shop');

        $this->logger = $this->createMock(LoggerInterface::class);
        return new MerchantSession($curlFactory, $storeManager, $config, $this->logger);
    }

    public function testCreatesSessionWithMerchantCertificate(): void
    {
        $merchantSession = $this->createMerchantSession();
        $this->keyPassword = 'secret';
        $session = '{"merchantSessionIdentifier":"SSH1","signature":"abc"}';
        $this->curl->expects($this->once())->method('post')->with(
            self::VALIDATION_URL,
            '{"merchantIdentifier":"merchant.com.example","displayName":"My Shop","initiative":"web",'
            .'"initiativeContext":"shop.example.com"}'
        );
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn($session);
        $this->logger->expects($this->never())->method('error');

        $this->assertSame($session, $merchantSession->create(self::VALIDATION_URL, 1));
        $this->assertSame('CERT', $this->options[CURLOPT_SSLCERT_BLOB]);
        $this->assertSame('KEY', $this->options[CURLOPT_SSLKEY_BLOB]);
        $this->assertSame('PEM', $this->options[CURLOPT_SSLKEYTYPE]);
        $this->assertSame('secret', $this->options[CURLOPT_KEYPASSWD]);
    }

    public function testKeyWithoutPasswordAndChinaGateway(): void
    {
        $merchantSession = $this->createMerchantSession();
        $this->curl->expects($this->once())->method('post');
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"merchantSessionIdentifier":"SSH1"}');
        $this->logger->expects($this->never())->method('error');

        $merchantSession->create('https://cn-apple-pay-gateway.apple.com/paymentservices/paymentSession', 1);

        $this->assertArrayNotHasKey(CURLOPT_KEYPASSWD, $this->options);
    }

    public static function forbiddenUrlProvider(): array
    {
        return [
            'other host' => ['https://evil.example.com/paymentservices/startSession'],
            'apple subdomain trick' => ['https://apple-pay-gateway.apple.com.evil.com/x'],
            'http' => ['http://apple-pay-gateway.apple.com/x'],
            'other port' => ['https://apple-pay-gateway.apple.com:8443/x'],
            'user info' => ['https://user@apple-pay-gateway.apple.com/x'],
            'no url' => ['not a url'],
        ];
    }

    #[DataProvider('forbiddenUrlProvider')]
    public function testOnlyApplePayGatewayUrlsAreAllowed(string $url): void
    {
        $merchantSession = $this->createMerchantSession();
        $this->curl->expects($this->never())->method('post');
        $this->logger->expects($this->never())->method('error');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid Apple Pay validation URL.');
        $merchantSession->create($url, 1);
    }

    public function testIncompleteConfiguration(): void
    {
        $merchantSession = $this->createMerchantSession();
        $this->configurationComplete = false;
        $this->curl->expects($this->never())->method('post');
        $this->logger->expects($this->never())->method('error');

        $this->expectExceptionMessage('Apple Pay is not configured completely.');
        $merchantSession->create(self::VALIDATION_URL, 1);
    }

    public function testConnectionErrorIsLogged(): void
    {
        $merchantSession = $this->createMerchantSession();
        $this->curl->expects($this->once())->method('post')->willThrowException(new \Exception('SSL certificate problem'));
        $this->logger->expects($this->once())->method('error')
            ->with('PAYONE CP Apple Pay merchant validation failed: SSL certificate problem');

        $this->expectExceptionMessage('Apple Pay is currently not available.');
        $merchantSession->create(self::VALIDATION_URL, 1);
    }

    public static function invalidResponseProvider(): array
    {
        return [
            'HTTP error' => [401, '{"statusMessage":"Unauthorized"}'],
            'no json' => [200, 'abc'],
            'no session' => [200, '{"statusCode":"400"}'],
        ];
    }

    #[DataProvider('invalidResponseProvider')]
    public function testInvalidResponseIsLogged(int $status, string $body): void
    {
        $merchantSession = $this->createMerchantSession();
        $this->curl->expects($this->once())->method('post');
        $this->curl->method('getStatus')->willReturn($status);
        $this->curl->method('getBody')->willReturn($body);
        $this->logger->expects($this->once())->method('error')
            ->with(sprintf('PAYONE CP Apple Pay merchant validation failed: HTTP %d %s', $status, $body));

        $this->expectExceptionMessage('Apple Pay is currently not available.');
        $merchantSession->create(self::VALIDATION_URL, 1);
    }
}
