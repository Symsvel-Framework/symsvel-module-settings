<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\SettingDefinition;
use SymSvel\Module\Settings\Service\SettingDefinitionProviderInterface;
use SymSvel\Module\Settings\Service\SettingRegistry;

final class SettingRegistryTest extends TestCase
{
    public function testCollectsDefinitions(): void
    {
        $provider = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('brevo.api_key', 'API key', secret: true);
            }
        };
        $registry = new SettingRegistry([$provider]);
        self::assertCount(1, $registry->all());
        self::assertTrue($registry->get('brevo.api_key')?->secret);
        self::assertNull($registry->get('unknown'));
    }

    public function testRejectsDuplicateKeys(): void
    {
        $provider = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('same.key', 'First');
                yield new SettingDefinition('same.key', 'Second');
            }
        };
        $this->expectException(\LogicException::class);
        new SettingRegistry([$provider]);
    }
}
