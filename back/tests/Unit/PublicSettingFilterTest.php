<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\PublicSettingFilter;
use SymSvel\Module\Settings\Service\SettingDefinition;
use SymSvel\Module\Settings\Service\SettingDefinitionProviderInterface;
use SymSvel\Module\Settings\Service\SettingRegistry;

final class PublicSettingFilterTest extends TestCase
{
    public function testExposesOnlyNonEmptyExplicitlyPublicValuesWithoutReadingSecrets(): void
    {
        $provider = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('application.name', 'Name', public: true);
                yield new SettingDefinition('application.empty', 'Empty', public: true);
                yield new SettingDefinition('application.blank', 'Blank', public: true);
                yield new SettingDefinition('application.private', 'Private');
                yield new SettingDefinition('brevo.api_key', 'API key', secret: true);
            }
        };
        $readKeys = [];
        $values = [
            'application.name' => 'SymSvel',
            'application.empty' => null,
            'application.blank' => '   ',
            'application.private' => 'private',
            'brevo.api_key' => 'secret',
        ];

        $data = (new PublicSettingFilter())->collect(
            new SettingRegistry([$provider]),
            static function (string $key) use (&$readKeys, $values): ?string {
                $readKeys[] = $key;
                return $values[$key];
            },
        );

        self::assertSame(['application.name' => 'SymSvel'], $data);
        self::assertSame(['application.name', 'application.empty', 'application.blank'], $readKeys);
    }

    public function testEmptyRegistryNeverReadsDatabase(): void
    {
        $read = static function (string $key): ?string {
            throw new \LogicException('Unexpected read: '.$key);
        };
        self::assertSame([], (new PublicSettingFilter())->collect(new SettingRegistry([]), $read));
    }
}
