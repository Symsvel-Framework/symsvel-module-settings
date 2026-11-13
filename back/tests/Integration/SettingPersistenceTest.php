<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Entity\Setting;

/**
 * Real Doctrine ORM round-trip against an isolated in-memory SQLite database.
 * Multi-database migration compilation remains the responsibility of Tooling CTs.
 */
final class SettingPersistenceTest extends TestCase
{
    private function entityManager(): EntityManager
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for the SQLite integration test.');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 2).'/src/Entity'],
            isDevMode: true,
        );
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $manager = new EntityManager($connection, $config);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(Setting::class)]);

        return $manager;
    }

    public function testPersistsReadsUpdatesAndClearsSetting(): void
    {
        $manager = $this->entityManager();
        try {
            $setting = new Setting('application.name');
            $setting->setValue('SymSvel');
            $manager->persist($setting);
            $manager->flush();
            self::assertNotNull($setting->getId());

            $id = $setting->getId();
            $manager->clear();
            $loaded = $manager->find(Setting::class, $id);
            self::assertInstanceOf(Setting::class, $loaded);
            self::assertSame('application.name', $loaded->getKey());
            self::assertSame('SymSvel', $loaded->getValue());

            $loaded->setValue(null);
            $manager->flush();
            $manager->clear();
            self::assertNull($manager->find(Setting::class, $id)?->getValue());
        } finally {
            $manager->getConnection()->close();
        }
    }

    public function testDatabaseRejectsDuplicateKeys(): void
    {
        $manager = $this->entityManager();
        try {
            $manager->persist(new Setting('same.key'));
            $manager->persist(new Setting('same.key'));
            $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
            $manager->flush();
        } finally {
            $manager->getConnection()->close();
        }
    }
}
