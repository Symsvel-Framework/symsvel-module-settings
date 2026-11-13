<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\SettingDefinition;
use SymSvel\Module\Settings\Service\SettingDefinitionProviderInterface;
use SymSvel\Module\Settings\Service\SettingRegistry;
use SymSvel\Module\Settings\Service\SettingKeyQualifier;

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

    public function testCollectsMultipleProvidersInDeclarationOrder(): void
    {
        $first = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('application.name', 'Name', required: true, blocking: true);
                yield new SettingDefinition('application.url', 'URL', type: 'url');
            }
        };
        $second = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('mail.sender', 'Sender', type: 'email');
            }
        };

        $registry = new SettingRegistry([$first, $second]);
        self::assertSame(
            ['application.name', 'application.url', 'mail.sender'],
            array_map(static fn (SettingDefinition $definition): string => $definition->key, $registry->all()),
        );
        self::assertTrue($registry->get('application.name')?->blocking);
        self::assertSame('email', $registry->get('mail.sender')?->type);
    }

    public function testRejectsDuplicateKeysAcrossProviders(): void
    {
        $first = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('application.name', 'First');
            }
        };
        $second = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('application.name', 'Second');
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Setting "application.name" is declared twice.');
        new SettingRegistry([$first, $second]);
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
    public function testRegistersShortKeyUsingVerifiedOwner(): void
    {
        $definition = new SettingDefinition('api_key', 'API key', secret: true);
        $provider = new class($definition) implements SettingDefinitionProviderInterface {
            public function __construct(private readonly SettingDefinition $definition) {}
            public function definitions(): iterable { yield $this->definition; }
        };
        $registry = new SettingRegistry([]);
        $registry->registerOwned($provider, 'brevo', new SettingKeyQualifier());

        self::assertSame('api_key', $definition->key);
        self::assertTrue($registry->get('brevo.api_key')?->secret);
        self::assertNull($registry->get('api_key'));
    }

    public function testRejectsCollisionAfterQualification(): void
    {
        $provider = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('api_key', 'First');
                yield new SettingDefinition('brevo.api_key', 'Second');
            }
        };
        $registry = new SettingRegistry([]);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Setting "brevo.api_key" is declared twice.');
        $registry->registerOwned($provider, 'brevo', new SettingKeyQualifier());
    }

    public function testRejectsForeignNamespaceWhenRegisteringOwnedProvider(): void
    {
        $provider = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable { yield new SettingDefinition('admins.api_key', 'Foreign'); }
        };
        $registry = new SettingRegistry([]);
        $this->expectException(\InvalidArgumentException::class);
        $registry->registerOwned($provider, 'brevo', new SettingKeyQualifier());
    }

}
