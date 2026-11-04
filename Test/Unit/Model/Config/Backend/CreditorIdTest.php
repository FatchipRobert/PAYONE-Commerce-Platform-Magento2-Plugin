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

namespace Payone\CommercePlatform\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Payone\CommercePlatform\Model\Config\Backend\CreditorId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CreditorIdTest extends TestCase
{
    private function createBackend(string $value): CreditorId
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $backend = new CreditorId(
            $context,
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class)
        );
        $backend->setValue($value);
        return $backend;
    }

    public function testValueIsNormalized(): void
    {
        $backend = $this->createBackend(' de98 zzz0 9999 9999 99 ');

        $backend->beforeSave();

        $this->assertSame('DE98ZZZ09999999999', $backend->getValue());
    }

    public static function invalidValues(): array
    {
        return [
            'empty' => [''],
            'customer number' => ['42'],
            'no check digits' => ['DEZZZ09999999999'],
            'invalid character' => ['DE98ZZZ0999_999999'],
            'too long' => ['DE98'.str_repeat('9', 32)],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValueIsRejected(string $value): void
    {
        $this->expectException(LocalizedException::class);

        $this->createBackend($value)->beforeSave();
    }
}
