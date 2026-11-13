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
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Service\SettingOnboarding;
use SymSvel\Module\Settings\Service\SettingRegistry;
use SymSvel\Module\Settings\Service\SettingSecretCipher;

/** Real SettingManager/Repository round-trips through Doctrine and SQLite. */
final class SettingManagerPersistenceTest extends TestCase
{
    private function services(): array
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for Settings integration tests.');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 2).'/src/Entity'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);
        $entityManager = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
            $config,
        );
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Setting::class)]);

        $doctrineRegistry = $this->createMock(ManagerRegistry::class);
        $doctrineRegistry->method('getManagerForClass')->with(Setting::class)->willReturn($entityManager);

        $provider = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('application.name', 'Name', required: true, blocking: true);
                yield new SettingDefinition('brevo.api_key', 'API key', required: true, secret: true);
                yield new SettingDefinition('feature.enabled', 'Enabled', type: 'boolean');
            }
        };

        $registry = new SettingRegistry([$provider]);
        $repository = new SettingRepository($doctrineRegistry);
        $cipher = new SettingSecretCipher('settings-test-secret');
        return [
            $entityManager,
            new SettingManager($registry, $repository, $entityManager, $cipher),
            $repository,
            $cipher,
        ];
    }

    public function testPersistsAndReadsSettingsAfterClearingEntityManager(): void
    {
        [$entityManager, $settings] = $this->services();
        try {
            $settings->set('application.name', '  SymSvel  ');
            $settings->set('feature.enabled', false);
            $settings->flush();
            $entityManager->clear();

            self::assertSame('SymSvel', $settings->get('application.name'));
            self::assertSame('0', $settings->get('feature.enabled'));

            $settings->set('feature.enabled', null);
            $settings->flush();
            $entityManager->clear();
            self::assertNull($settings->get('feature.enabled'));
        } finally {
            $entityManager->getConnection()->close();
        }
    }

    public function testEncryptsSecretsAtRestAndDecryptsAfterReload(): void
    {
        [$entityManager, $settings, $repository, $cipher] = $this->services();
        try {
            $settings->set('brevo.api_key', 'token-123');
            $settings->flush();
            $entityManager->clear();

            $stored = $repository->findOneBy(['key' => 'brevo.api_key']);
            self::assertInstanceOf(Setting::class, $stored);
            self::assertNotSame('token-123', $stored->getValue());
            self::assertSame('token-123', $cipher->decrypt((string) $stored->getValue()));
            self::assertSame('token-123', $settings->get('brevo.api_key'));
        } finally {
            $entityManager->getConnection()->close();
        }
    }

    public function testOnboardingRecalculatesBlockingAndPendingFromDatabase(): void
    {
        [$entityManager, $settings] = $this->services();
        try {
            $onboarding = new SettingOnboarding($settings);
            self::assertSame([
                'complete' => false,
                'missing' => ['application.name'],
                'pending' => ['brevo.api_key'],
            ], $onboarding->status());

            $settings->set('application.name', 'SymSvel');
            $settings->flush();
            $entityManager->clear();

            self::assertSame([
                'complete' => true,
                'missing' => [],
                'pending' => ['brevo.api_key'],
            ], $onboarding->status());

            $settings->set('brevo.api_key', 'token-123');
            $settings->flush();
            $entityManager->clear();

            self::assertSame([
                'complete' => true,
                'missing' => [],
                'pending' => [],
            ], $onboarding->status());
        } finally {
            $entityManager->getConnection()->close();
        }
    }

    public function testRejectsUnknownKeyWithoutWritingToDatabase(): void
    {
        [$entityManager, $settings, $repository] = $this->services();
        try {
            try {
                $settings->set('unknown.key', 'value');
                self::fail('Unknown setting should be rejected.');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringContainsString('unknown.key', $exception->getMessage());
            }
            self::assertSame([], $repository->findAll());
        } finally {
            $entityManager->getConnection()->close();
        }
    }
}
