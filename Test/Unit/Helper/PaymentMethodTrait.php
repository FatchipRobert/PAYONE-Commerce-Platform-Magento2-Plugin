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

use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Psr\Log\LoggerInterface;

/**
 * Real payment method classes (Model\Method) with doubles for the dependencies of Magento's Adapter
 *
 * Usage: new Creditcard(...$this->methodArguments(['automatic_capture' => '1']), generalConfig: ..., returnUrl: ...)
 */
trait PaymentMethodTrait
{
    /**
     * Constructor arguments of PayoneMethod, the own dependencies of a method follow as named arguments
     *
     * @param array $configData config values of the method (payment/<code>/...): field => value
     * @param ManagerInterface|null $eventManager
     * @param CommandPoolInterface|null $commandPool
     * @return array
     */
    protected function methodArguments(
        array $configData = [],
        ?ManagerInterface $eventManager = null,
        ?CommandPoolInterface $commandPool = null
    ): array {
        $valueHandler = $this->createStub(ValueHandlerInterface::class);
        $valueHandler->method('handle')->willReturnCallback(
            static fn (array $subject) => $configData[$subject['field']] ?? null
        );
        $valueHandlerPool = $this->createStub(ValueHandlerPoolInterface::class);
        $valueHandlerPool->method('get')->willReturn($valueHandler);

        return [
            $eventManager ?? $this->createStub(ManagerInterface::class),
            $valueHandlerPool,
            $this->createStub(PaymentDataObjectFactory::class),
            'Magento\Payment\Block\Form',
            'Magento\Payment\Block\Info',
            $commandPool ?? $this->createStub(CommandPoolInterface::class),
            $this->createStub(LoggerInterface::class),
        ];
    }
}
