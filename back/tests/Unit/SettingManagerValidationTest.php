<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SymSvel\Module\Settings\Service\SettingDefinition;
use SymSvel\Module\Settings\Service\SettingManager;

final class SettingManagerValidationTest extends TestCase
{
    /**
     * Test the pure normalization logic without a database or unneeded Doctrine mocks.
     */
    private function normalize(SettingDefinition $definition, mixed $value): ?string
    {
        $reflection = new ReflectionClass(SettingManager::class);
        $manager = $reflection->newInstanceWithoutConstructor();
        return $reflection->getMethod('normalize')->invoke($manager, $definition, $value);
    }

    public function testOptionalBooleanCanBeCleared(): void
    {
        $definition = new SettingDefinition('feature.enabled', 'Enabled', type: 'boolean');
        self::assertNull($this->normalize($definition, null));
        self::assertNull($this->normalize($definition, ''));
        self::assertSame('0', $this->normalize($definition, false));
        self::assertSame('1', $this->normalize($definition, true));
        self::assertSame('0', $this->normalize($definition, '0'));
        self::assertSame('1', $this->normalize($definition, '1'));
    }

    public function testRequiredBooleanCannotBeCleared(): void
    {
        $definition = new SettingDefinition('feature.enabled', 'Enabled', type: 'boolean', required: true);
        $this->expectException(\InvalidArgumentException::class);
        $this->normalize($definition, null);
    }

    public function testBooleanRejectsUnknownValue(): void
    {
        $definition = new SettingDefinition('feature.enabled', 'Enabled', type: 'boolean');
        $this->expectException(\InvalidArgumentException::class);
        $this->normalize($definition, 'yes');
    }
}
