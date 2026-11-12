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
use Payone\CommercePlatform\Model\Config\Backend\RefPrefix;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RefPrefixTest extends TestCase
{
    private function createBackend(string $value): RefPrefix
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $backend = new RefPrefix(
            $context,
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class)
        );
        $backend->setValue($value);
        return $backend;
    }

    public static function validValues(): array
    {
        return [
            'empty' => ['', ''],
            'spaces around' => [' ab- ', 'ab-'],
            'all allowed characters' => ['aZ9+.(', 'aZ9+.('],
            'maximum length' => ['abcdef', 'abcdef'],
        ];
    }

    #[DataProvider('validValues')]
    public function testValidValueIsSaved(string $value, string $expected): void
    {
        $backend = $this->createBackend($value);

        $backend->beforeSave();

        $this->assertSame($expected, $backend->getValue());
    }

    public static function invalidValues(): array
    {
        return [
            'too long' => ['abcdefg'],
            'underscore' => ['ab_'],
            'space inside' => ['a b'],
            'umlaut' => ['ä'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValueIsRejected(string $value): void
    {
        $this->expectException(LocalizedException::class);

        $this->createBackend($value)->beforeSave();
    }
}
