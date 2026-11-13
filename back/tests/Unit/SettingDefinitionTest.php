<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\SettingDefinition;

final class SettingDefinitionTest extends TestCase
{
    public function testAcceptsSupportedTypes(): void
    {
        foreach (['string', 'boolean', 'email', 'url'] as $type) {
            self::assertSame($type, (new SettingDefinition('example.key', 'Example', type: $type))->type);
        }
    }

    public function testRejectsUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('example.key', 'Example', type: 'json');
    }

    public function testRejectsPublicSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('example.key', 'Example', secret: true, public: true);
    }

    public function testRejectsBlockingSettingThatIsNotRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Blocking settings must be required.');
        new SettingDefinition('application.name', 'Name', blocking: true);
    }

    public function testAcceptsRequiredBlockingSetting(): void
    {
        $definition = new SettingDefinition('application.name', 'Name', required: true, blocking: true);
        self::assertTrue($definition->required);
        self::assertTrue($definition->blocking);
    }

    public function testRejectsKeyWithLeadingOrTrailingWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition(' application.name ', 'Name');
    }

    public function testAcceptsCanonicalKey(): void
    {
        self::assertSame('application.name', (new SettingDefinition('application.name', 'Name'))->key);
    }

    public function testRejectsEmptyKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('', 'Example');
    }
}
