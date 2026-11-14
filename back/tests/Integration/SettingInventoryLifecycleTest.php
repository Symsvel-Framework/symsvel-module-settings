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
        } finally {
            $manager->getConnection()->close();
            @unlink($backend.'/.symsvel/modules-params.json');
            @rmdir($backend.'/.symsvel');
            @rmdir($backend);
        }
    }
}
