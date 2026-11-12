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

namespace Payone\CommercePlatform\Test\Unit\Block;

use Magento\Backend\Block\Template\Context as BackendTemplateContext;
use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Context as WidgetContext;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Payment\Model\Info;
use Payone\CommercePlatform\Block\Adminhtml\Config\Form\Field\EncryptedFile;
use Payone\CommercePlatform\Block\Adminhtml\Protocol\Api\View as ApiView;
use Payone\CommercePlatform\Block\Adminhtml\Protocol\Webhook\View as WebhookView;
use Payone\CommercePlatform\Block\Info\PaymentInstructions;
use Payone\CommercePlatform\Model\ApiLog;
use Payone\CommercePlatform\Model\ApiLogFactory;
use Payone\CommercePlatform\Model\Config\EncryptedFile as EncryptedFileValue;
use Payone\CommercePlatform\Model\WebhookMessage;
use Payone\CommercePlatform\Model\WebhookMessageFactory;
use Payone\CommercePlatform\Test\Unit\Helper\EncryptorStubTrait;
use Payone\CommercePlatform\Test\Unit\Helper\ObjectManagerStubTrait;
use PHPUnit\Framework\TestCase;

class BlocksTest extends TestCase
{
    use EncryptorStubTrait;
    use MockCreationTrait;
    use ObjectManagerStubTrait;

    protected function setUp(): void
    {
        $this->setUpObjectManagerStub();
    }

    public function testPaymentInstructionsShowsBankDetailsAndFormattedDueDate(): void
    {
        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->expects($this->once())->method('formatDateTime')->willReturnCallback(
            static fn (\DateTime $date, $dateType, $timeType, $locale, $timezone) => $date->format('d.m.Y').'|'.$dateType.'|'.$timeType.'|'.$timezone
        );
        $context = $this->createStub(TemplateContext::class);
        $context->method('getLocaleDate')->willReturn($localeDate);
        $block = new PaymentInstructions($context);

        $info = $this->createPartialStub(Info::class);
        $info->setAdditionalInformation([
            PaymentInstructions::KEY_PAYEE_NAME => 'PAYONE GmbH',
            PaymentInstructions::KEY_PAYEE_IBAN => 'DE00123',
            PaymentInstructions::KEY_REFERENCE_NUMBER => 'REF1',
            PaymentInstructions::KEY_DUE_DATE => '20261203',
        ]);
        $block->setInfo($info);

        $this->assertSame([
            'Account holder' => 'PAYONE GmbH',
            'IBAN' => 'DE00123',
            'Payment reference' => 'REF1',
            'Due date' => '03.12.2026|'.\IntlDateFormatter::MEDIUM.'|'.\IntlDateFormatter::NONE.'|UTC',
        ], $block->getSpecificInformation());
    }

    public function testPaymentInstructionsWithUnparsableDueDateAndWithoutInstructions(): void
    {
        $context = $this->createStub(TemplateContext::class);
        $block = new PaymentInstructions($context);
        $info = $this->createPartialStub(Info::class);
        $info->setAdditionalInformation(PaymentInstructions::KEY_DUE_DATE, '2026-12-03');
        $block->setInfo($info);
        $this->assertSame(['Due date' => '2026-12-03'], $block->getSpecificInformation());

        $block = new PaymentInstructions($context);
        $block->setInfo($this->createPartialStub(Info::class));
        $this->assertSame([], $block->getSpecificInformation());
    }

    public function testEncryptedFileFieldShowsOnlyTheFileName(): void
    {
        $encryptedFile = new EncryptedFileValue($this->createEncryptor());
        $block = new EncryptedFile($this->createStub(BackendTemplateContext::class), $encryptedFile);
        $element = $this->createPartialMockWithReflection(
            AbstractElement::class,
            ['getValue', 'setValue', 'getElementHtml']
        );
        $value = $encryptedFile->encode('merchant_id.pem', 'SECRET CONTENT');
        $element->method('getValue')->willReturn($value);
        $element->expects($this->once())->method('setValue')->with('merchant_id.pem');
        $element->method('getElementHtml')->willReturn('<input type="file"/>');

        $html = (new \ReflectionMethod($block, '_getElementHtml'))->invoke($block, $element);

        $this->assertSame('<input type="file"/>', $html);
    }

    public function testEncryptedFileFieldWithoutStoredFile(): void
    {
        $block = new EncryptedFile($this->createStub(BackendTemplateContext::class), new EncryptedFileValue($this->createEncryptor()));
        $element = $this->createPartialMockWithReflection(
            AbstractElement::class,
            ['getValue', 'setValue', 'getElementHtml']
        );
        $element->method('getValue')->willReturn(null);
        $element->expects($this->once())->method('setValue')->with('');

        (new \ReflectionMethod($block, '_getElementHtml'))->invoke($block, $element);
    }

    private function createWidgetContext(ButtonList $buttonList, string $id): WidgetContext
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn ($name) => $name === 'id' ? $id : null);
        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(static fn ($route) => 'https://admin.test/'.$route);
        $context = $this->createStub(WidgetContext::class);
        $context->method('getButtonList')->willReturn($buttonList);
        $context->method('getRequest')->willReturn($request);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);
        return $context;
    }

    public function testApiLogViewLoadsEntryOnceAndHasBackButton(): void
    {
        $buttonList = $this->createMock(ButtonList::class);
        $buttonList->expects($this->once())->method('add')->with('back', $this->callback(
            static fn (array $button) => $button['onclick'] === "setLocation('https://admin.test/payone_cp/protocol_api/')"
        ));
        $apiLog = $this->createMock(ApiLog::class);
        $apiLog->expects($this->once())->method('load')->with('17')->willReturnSelf();
        $factory = $this->createStub(ApiLogFactory::class);
        $factory->method('create')->willReturn($apiLog);

        $block = new ApiView($this->createWidgetContext($buttonList, '17'), $factory);

        $this->assertSame($apiLog, $block->getApiLogEntry());
        $this->assertSame($apiLog, $block->getApiLogEntry());
    }

    public function testWebhookViewLoadsEntryOnceAndHasBackButton(): void
    {
        $buttonList = $this->createMock(ButtonList::class);
        $buttonList->expects($this->once())->method('add')->with('back', $this->callback(
            static fn (array $button) => $button['onclick'] === "setLocation('https://admin.test/payone_cp/protocol_webhook/')"
        ));
        $message = $this->createMock(WebhookMessage::class);
        $message->expects($this->once())->method('load')->with('5')->willReturnSelf();
        $factory = $this->createStub(WebhookMessageFactory::class);
        $factory->method('create')->willReturn($message);

        $block = new WebhookView($this->createWidgetContext($buttonList, '5'), $factory);

        $this->assertSame($message, $block->getWebhookLogEntry());
        $this->assertSame($message, $block->getWebhookLogEntry());
    }

    private function createPartialStub(string $className): object
    {
        return static::getStubBuilder($className)->disableOriginalConstructor()->onlyMethods([])->getStub();
    }
}
