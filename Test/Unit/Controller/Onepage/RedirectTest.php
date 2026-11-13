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

namespace Payone\CommercePlatform\Test\Unit\Controller\Onepage;

use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Payone\CommercePlatform\Controller\Onepage\Redirect as RedirectController;
use PHPUnit\Framework\TestCase;

class RedirectTest extends TestCase
{
    use MockCreationTrait;

    private function execute(?string $redirectUrl): Redirect
    {
        $session = $this->createPartialMockWithReflection(Session::class, ['getPayoneCpRedirectUrl']);
        $session->expects($this->once())->method('getPayoneCpRedirectUrl')->willReturn($redirectUrl);
        $redirect = $this->createMock(Redirect::class);
        if ($redirectUrl) {
            $redirect->expects($this->once())->method('setUrl')->with($redirectUrl)->willReturnSelf();
        } else {
            $redirect->expects($this->once())->method('setPath')->with('checkout/onepage/success')->willReturnSelf();
        }
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $this->assertSame($redirect, (new RedirectController($session, $redirectFactory))->execute());
        return $redirect;
    }

    public function testRedirectsToPayone(): void
    {
        $this->execute('https://payone.test/3ds');
    }

    public function testWithoutRedirectUrlToSuccessPage(): void
    {
        $this->execute(null);
    }
}
