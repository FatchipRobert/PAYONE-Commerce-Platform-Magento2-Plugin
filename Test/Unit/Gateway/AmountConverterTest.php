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

namespace Payone\CommercePlatform\Test\Unit\Gateway;

use Payone\CommercePlatform\Gateway\AmountConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountConverterTest extends TestCase
{
    public static function amountProvider(): array
    {
        return [
            'integer amount' => [10.0, 1000],
            'cents' => [70.21, 7021],
            'float imprecision (0.1 + 0.7) * 100 = 79.999...' => [0.1 + 0.7, 80],
            'rounds half up' => [0.125, 13],
            'zero' => [0.0, 0],
            'negative' => [-5.5, -550],
        ];
    }

    #[DataProvider('amountProvider')]
    public function testToMinorUnits(float $amount, int $expected): void
    {
        $this->assertSame($expected, (new AmountConverter())->toMinorUnits($amount));
    }
}
