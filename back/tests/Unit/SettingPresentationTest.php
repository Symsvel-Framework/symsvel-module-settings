<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\SettingDefinition;
use SymSvel\Module\Settings\Service\SettingDefinitionProviderInterface;
use SymSvel\Module\Settings\Service\SettingPresentation;
use SymSvel\Module\Settings\Service\SettingRegistry;

final class SettingPresentationTest extends TestCase
{
    public function testSecretIsNeverIncludedInAdministrationPayload(): void
    {
        $provider = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('mail.api_key', 'API key', group: 'mail', required: true, secret: true, blocking: true);
                yield new SettingDefinition('application.name', 'Name', public: true);
                yield new SettingDefinition('mail.empty', 'Empty', secret: true);
                yield new SettingDefinition('mails.transport', 'Transport', type: 'choice',
                    choices: ['mailpit' => 'Mailpit', 'smtp4dev' => 'smtp4dev']);
            }
        };
        $values = ['mail.api_key' => 'sensitive-value', 'application.name' => 'SymSvel', 'mail.empty' => null];

        $data = (new SettingPresentation())->all(
            new SettingRegistry([$provider]),
            static fn (string $key): ?string => $values[$key],
        );

        self::assertCount(4, $data);
        self::assertSame('mail.api_key', $data[0]['key']);
        self::assertTrue($data[0]['configured']);
        self::assertTrue($data[0]['secret']);
        self::assertTrue($data[0]['blocking']);
        self::assertNull($data[0]['value']);
        self::assertSame('SymSvel', $data[1]['value']);
        self::assertFalse($data[2]['configured']);
        self::assertNull($data[2]['value']);
        self::assertSame(['mailpit' => 'Mailpit', 'smtp4dev' => 'smtp4dev'], $data[3]['choices']);
        self::assertStringNotContainsString('sensitive-value', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function testEmptyRegistryProducesEmptyPayload(): void
    {
        self::assertSame([], (new SettingPresentation())->all(
            new SettingRegistry([]),
            static fn (string $key): ?string => throw new \LogicException('Unexpected read: '.$key),
        ));
    }
}
