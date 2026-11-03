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

namespace Payone\CommercePlatform\Test\Unit\Helper;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;

/**
 * Magento core classes (blocks, backend actions) fetch optional dependencies via ObjectManager::getInstance()
 */
trait ObjectManagerStubTrait
{
    /**
     * Installs an object manager that returns stubs of the requested classes
     *
     * @param array $instances class name => instance, for dependencies the test needs to configure
     * @return void
     */
    protected function setUpObjectManagerStub(array $instances = []): void
    {
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $factory = function (string $className) use ($instances) {
            return $instances[$className] ?? $this->createStub($className);
        };
        $objectManager->method('get')->willReturnCallback($factory);
        $objectManager->method('create')->willReturnCallback($factory);
        ObjectManager::setInstance($objectManager);
    }
}
