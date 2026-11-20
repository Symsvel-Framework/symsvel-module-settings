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

    public function testTrimsTextAndAllowsOptionalClearing(): void
    {
        $definition = new SettingDefinition('application.name', 'Name');
        self::assertSame('SymSvel', $this->normalize($definition, '  SymSvel  '));
        self::assertNull($this->normalize($definition, '   '));
    }

    public function testRequiredTextRejectsWhitespaceOnly(): void
    {
        $definition = new SettingDefinition('application.name', 'Name', required: true);
        $this->expectException(\InvalidArgumentException::class);
        $this->normalize($definition, '   ');
    }

    public function testEmailValidation(): void
    {
        $definition = new SettingDefinition('mail.sender', 'Sender', type: 'email');
        self::assertSame('hello@example.org', $this->normalize($definition, ' hello@example.org '));
        self::assertNull($this->normalize($definition, ''));
        $this->expectException(\InvalidArgumentException::class);
        $this->normalize($definition, 'not-an-email');
    }

    public function testUrlValidation(): void
    {
        $definition = new SettingDefinition('application.url', 'URL', type: 'url');
        self::assertSame('https://example.org', $this->normalize($definition, ' https://example.org '));
        self::assertNull($this->normalize($definition, null));
        $this->expectException(\InvalidArgumentException::class);
        $this->normalize($definition, 'not-a-url');
    }

    public function testRejectsNonScalarValues(): void
    {
        $definition = new SettingDefinition('application.name', 'Name');
        $this->expectException(\InvalidArgumentException::class);
        $this->normalize($definition, ['unexpected']);
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
