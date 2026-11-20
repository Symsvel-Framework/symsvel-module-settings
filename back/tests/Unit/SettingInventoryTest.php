<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\SettingDefinition;
use SymSvel\Module\Settings\Service\SettingDefinitionProviderInterface;
use SymSvel\Module\Settings\Service\SettingInventory;
use SymSvel\Module\Settings\Service\SettingRegistry;

final class SettingInventoryTest extends TestCase
{
    public function testProjectsDefinitionsWithoutPersistedValuesOrSecrets(): void
    {
        $provider = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('mails.sender_email', 'Sender', 'mail', 'email');
                yield new SettingDefinition('mails.transport', 'Transport', 'mail', 'string', secret: true);
            }
        };
        $inventory = (new SettingInventory())->fromRegistry(new SettingRegistry([$provider]));
        self::assertSame(1, $inventory['schema']);
        self::assertSame(['sender_email', 'transport'], array_keys($inventory['modules']['mails']));
        self::assertTrue($inventory['modules']['mails']['transport']['secret']);
        self::assertArrayNotHasKey('value', $inventory['modules']['mails']['transport']);
    }

    public function testRejectsUnqualifiedKeys(): void
    {
        $provider = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('sender_email', 'Sender');
            }
        };
        $this->expectException(\LogicException::class);
        (new SettingInventory())->fromRegistry(new SettingRegistry([$provider]));
    }
}
