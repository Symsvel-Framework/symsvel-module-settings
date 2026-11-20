<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Entity\Setting;

final class SettingTest extends TestCase
{
    public function testStoresKeyAndNullableValue(): void
    {
        $setting = new Setting('application.name');
        self::assertSame('application.name', $setting->getKey());
        self::assertNull($setting->getValue());
        $setting->setValue('SymSvel');
        self::assertSame('SymSvel', $setting->getValue());
    }

    public function testRejectsEmptyKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Setting(' ');
    }
}
