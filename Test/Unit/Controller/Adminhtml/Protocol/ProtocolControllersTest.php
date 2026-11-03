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

namespace Payone\CommercePlatform\Test\Unit\Controller\Adminhtml\Protocol;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use Payone\CommercePlatform\Controller\Adminhtml\Protocol\Api\Index as ApiIndex;
use Payone\CommercePlatform\Controller\Adminhtml\Protocol\Api\View as ApiView;
use Payone\CommercePlatform\Controller\Adminhtml\Protocol\Webhook\Index as WebhookIndex;
use Payone\CommercePlatform\Controller\Adminhtml\Protocol\Webhook\View as WebhookView;
use Payone\CommercePlatform\Test\Unit\Helper\ObjectManagerStubTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Admin pages of the API and webhook protocol: grid (Index) and detail page (View)
 */
class ProtocolControllersTest extends TestCase
{
    use ObjectManagerStubTrait;

    /**
     * @var array
     */
    private array $titleCalls = [];

    private function createPageFactory(string $expectedMenu): PageFactory
    {
        $title = $this->createStub(Title::class);
        $title->method('set')->willReturnCallback(function ($text) {
            $this->titleCalls[] = ['set', (string)$text];
        });
        $title->method('prepend')->willReturnCallback(function ($text) {
            $this->titleCalls[] = ['prepend', (string)$text];
        });
        $config = $this->createStub(Config::class);
        $config->method('getTitle')->willReturn($title);
        $page = $this->createMock(Page::class);
        $page->expects($this->once())->method('setActiveMenu')->with($expectedMenu)->willReturnSelf();
        $page->method('getConfig')->willReturn($config);
        $pageFactory = $this->createStub(PageFactory::class);
        $pageFactory->method('create')->willReturn($page);
        return $pageFactory;
    }

    private function createContext(): Context
    {
        $this->setUpObjectManagerStub();
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn ($name) => $name === 'id' ? '17' : null);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        return $context;
    }

    public static function controllerProvider(): array
    {
        return [
            'API grid' => [ApiIndex::class, 'Payone_CommercePlatform::payone_cp_protocol_api', [['set', 'Protocol - Api']]],
            'API entry' => [
                ApiView::class,
                'Payone_CommercePlatform::payone_cp_protocol_api',
                [['prepend', 'Protocol - API'], ['prepend', '#17']],
            ],
            'webhook grid' => [
                WebhookIndex::class,
                'Payone_CommercePlatform::payone_cp_protocol_webhook',
                [['set', 'Protocol - Webhook']],
            ],
            'webhook entry' => [
                WebhookView::class,
                'Payone_CommercePlatform::payone_cp_protocol_webhook',
                [['prepend', 'Protocol - Webhook'], ['prepend', '#17']],
            ],
        ];
    }

    #[DataProvider('controllerProvider')]
    public function testPageWithMenuAndTitle(string $controllerClass, string $menu, array $expectedTitle): void
    {
        $controller = new $controllerClass($this->createContext(), $this->createPageFactory($menu));

        $controller->execute();

        $this->assertSame($expectedTitle, $this->titleCalls);
        $this->assertSame($menu, $controllerClass::ADMIN_RESOURCE);
    }
}
