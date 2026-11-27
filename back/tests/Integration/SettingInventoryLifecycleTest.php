<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Entity\Setting;
use SymSvel\Module\Settings\Repository\SettingRepository;
use SymSvel\Module\Settings\Service\SettingDefinition;
use SymSvel\Module\Settings\Service\SettingDefinitionProviderInterface;
use SymSvel\Module\Settings\Service\SettingInventory;
use SymSvel\Module\Settings\Service\SettingInventoryDiff;
use SymSvel\Module\Settings\Service\SettingInventoryStore;
use SymSvel\Module\Settings\Service\SettingInventorySynchronizer;
use SymSvel\Module\Settings\Service\SettingRegistry;
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Service\SettingSecretCipher;

/** Exercises inventory publication and real ORM persistence through the lifecycle. */
final class SettingInventoryLifecycleTest extends TestCase
{
    public function testInstallUpdateRemoveAndReinstallPreserveOnlyDeclaredSettings(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for Settings integration tests.');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 2).'/src/Entity'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);
        $manager = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
            $config,
        );
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(Setting::class)]);
        $doctrineRegistry = $this->createMock(ManagerRegistry::class);
        $doctrineRegistry->method('getManagerForClass')->with(Setting::class)->willReturn($manager);
        $repository = new SettingRepository($doctrineRegistry);
        $store = new SettingInventoryStore();
        $backend = sys_get_temp_dir().'/symsvel-settings-lifecycle-'.bin2hex(random_bytes(8));
        mkdir($backend, 0700, true);

        $sync = static function (array $definitions) use ($manager, $repository, $store, $backend): array {
            $provider = new class($definitions) implements SettingDefinitionProviderInterface {
                public function __construct(private readonly array $definitions) {}
                public function definitions(): iterable { yield from $this->definitions; }
            };
            return (new SettingInventorySynchronizer(
                new SettingRegistry([$provider]),
                $repository,
                $manager,
                new SettingInventory(),
                $store,
                new SettingInventoryDiff(),
            ))->sync($backend);
        };
        $email = new SettingDefinition('mails.sender_email', 'Sender email', 'mail', 'email');
        $name = new SettingDefinition('mails.sender_name', 'Sender name', 'mail');
        $transport = new SettingDefinition('mails.transport', 'Transport', 'mail');
        $other = new SettingDefinition('other.enabled', 'Other', type: 'boolean');

        try {
            self::assertSame(['added' => ['mails.sender_email', 'mails.sender_name', 'mails.transport', 'other.enabled'], 'removed' => [], 'changed' => []], $sync([$email, $name, $transport, $other]));
            $manager->clear();
            foreach (['mails.sender_email', 'mails.sender_name', 'mails.transport'] as $key) {
                self::assertNull($repository->findOneBy(['key' => $key])?->getValue());
                self::assertNotNull($repository->findOneBy(['key' => $key]));
            }
            $row = $repository->findOneBy(['key' => 'mails.sender_email']);
            $row->setValue('sender@example.test');
            $manager->flush();
            $manager->clear();

            self::assertSame(['added' => [], 'removed' => [], 'changed' => []], $sync([$email, $name, $transport, $other]));
            self::assertSame('sender@example.test', $repository->findOneBy(['key' => 'mails.sender_email'])?->getValue());
            self::assertSame(['added' => [], 'removed' => ['mails.sender_name'], 'changed' => []], $sync([$email, $transport, $other]));
            $manager->clear();
            self::assertNull($repository->findOneBy(['key' => 'mails.sender_name']));
            self::assertSame('sender@example.test', $repository->findOneBy(['key' => 'mails.sender_email'])?->getValue());

            self::assertSame(['added' => [], 'removed' => ['mails.sender_email', 'mails.transport'], 'changed' => []], $sync([$other]));
            $manager->clear();
            self::assertNull($repository->findOneBy(['key' => 'mails.sender_email']));
            self::assertNotNull($repository->findOneBy(['key' => 'other.enabled']));
            self::assertSame(['added' => ['mails.sender_email', 'mails.sender_name', 'mails.transport'], 'removed' => [], 'changed' => []], $sync([$email, $name, $transport, $other]));
            $manager->clear();
            self::assertNull($repository->findOneBy(['key' => 'mails.sender_email'])?->getValue());
            self::assertSame(['added' => [], 'removed' => [], 'changed' => []], $sync([$email, $name, $transport, $other]));
            $inventory = $store->read($backend);
            self::assertSame(['mails', 'other'], array_keys($inventory['modules']));
            self::assertStringNotContainsString('sender@example.test', (string) file_get_contents($backend.'/.symsvel/modules-params.json'));

            // A module can opt into a default and impose a value during a later synchronization.
            $choices = ['keep' => 'Keep', 'delete' => 'Delete'];
            $mode = new SettingDefinition('example.mode', 'Mode', type: 'choice', required: true,
                choices: $choices, defaultValue: 'keep');
            $locked = new SettingDefinition('example.mode', 'Mode', type: 'choice', required: true,
                choices: ['keep' => 'Keep'], editable: false, defaultValue: 'keep', forcedValue: 'keep');
            $provider = static fn (SettingDefinition $definition): SettingDefinitionProviderInterface => new class($definition) implements SettingDefinitionProviderInterface {
                public function __construct(private readonly SettingDefinition $definition) {}
                public function definitions(): iterable { yield $this->definition; }
            };
            $settingsFor = static fn (SettingDefinition $definition): SettingManager => new SettingManager(
                new SettingRegistry([$provider($definition)]), $repository, $manager,
                new SettingSecretCipher('settings-test-secret-for-managed-values'),
            );
            $ordinary = [$email, $name, $transport, $other];
            $settings = $settingsFor($mode);
            self::assertSame('keep', $settings->get('example.mode'));
            self::assertSame([], $settings->missingRequired());
            $sync([...$ordinary, $mode]);
            $manager->clear();
            self::assertSame('keep', $repository->findOneBy(['key' => 'example.mode'])?->getValue());
            $settings->set('example.mode', 'delete');
            $settings->flush();
            $manager->clear();
            $sync([...$ordinary, $mode]);
            self::assertSame('delete', $settings->get('example.mode'));

            $lockedSettings = $settingsFor($locked);
            // Runtime is already protected even before the lifecycle synchronization.
            self::assertSame('keep', $lockedSettings->get('example.mode'));
            try {
                $lockedSettings->set('example.mode', 'delete');
                self::fail('A forced value must reject direct service writes.');
            } catch (\InvalidArgumentException) {
                self::assertSame('delete', $repository->findOneBy(['key' => 'example.mode'])?->getValue());
            }
            $sync([...$ordinary, $locked]);
            $manager->clear();
            self::assertSame('keep', $repository->findOneBy(['key' => 'example.mode'])?->getValue());
            $metadata = $store->read($backend)['modules']['example']['mode'];
            self::assertTrue($metadata['forced']);
            self::assertTrue($metadata['has_default']);
            self::assertArrayNotHasKey('forced_value', $metadata);
            self::assertArrayNotHasKey('default_value', $metadata);
            self::assertSame(['added' => [], 'removed' => [], 'changed' => []], $sync([...$ordinary, $locked]));

            // Removing the constraint never restores the former delete value.
            $sync([...$ordinary, $mode]);
            $manager->clear();
            self::assertSame('keep', $settingsFor($mode)->get('example.mode'));
            self::assertArrayNotHasKey('forced', $store->read($backend)['modules']['example']['mode']);

        } finally {
            $manager->getConnection()->close();
            @unlink($backend.'/.symsvel/modules-params.json');
            @rmdir($backend.'/.symsvel');
            @rmdir($backend);
        }
    }
}
