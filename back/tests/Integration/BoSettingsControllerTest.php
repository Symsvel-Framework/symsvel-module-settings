<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use SymSvel\Module\Acls\Access\BoAccessDecision;
use SymSvel\Module\Acls\Access\BoAccessGate;
use SymSvel\Module\Settings\Controller\BoSettingsController;
use SymSvel\Module\Settings\Entity\Setting;
use SymSvel\Module\Settings\Repository\SettingRepository;
use SymSvel\Module\Settings\Service\SettingDefinition;
use SymSvel\Module\Settings\Service\SettingDefinitionProviderInterface;
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Service\SettingPresentation;
use SymSvel\Module\Settings\Service\SettingRegistry;
use SymSvel\Module\Settings\Service\SettingSecretCipher;

final class BoSettingsControllerTest extends TestCase
{
    /** @return array{BoSettingsController, EntityManager} */
    private function fixture(int $status = 200): array
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 2).'/src/Entity'], isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Setting::class)]);
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->with(Setting::class)->willReturn($em);
        $provider = new class implements SettingDefinitionProviderInterface {
            public function definitions(): iterable
            {
                yield new SettingDefinition('mails.sender_email', 'Sender', group: 'mail', type: 'email');
                yield new SettingDefinition('mails.api_key', 'API key', group: 'mail', secret: true);
            }
        };
        $registry = new SettingRegistry([$provider]);
        $settings = new SettingManager(
            $registry, new SettingRepository($managerRegistry), $em,
            new SettingSecretCipher('settings-test-secret-for-bo'),
        );
        $gate = new class($status) implements BoAccessGate {
            public function __construct(private int $status) {}
            public function check(Request $request, string $resource, string $action): BoAccessDecision
            {
                if ($resource !== 'settings' || !in_array($action, ['list', 'update'], true)) {
                    throw new \LogicException('Unexpected permission');
                }
                return $this->status === 200
                    ? new BoAccessDecision(200, 1, 'ROLE_DEV')
                    : new BoAccessDecision($this->status);
            }
        };
        return [new BoSettingsController($gate, $settings, $registry, new SettingPresentation()), $em];
    }

    public function testUpdatesValuesAndNeverExposesSecret(): void
    {
        [$controller, $em] = $this->fixture();
        try {
            $email = self::jsonRequest('/apibo/settings/mails.sender_email', ['value' => 'sender@example.test']);
            self::assertSame(200, $controller->update($email, 'mails.sender_email')->getStatusCode());
            $secret = self::jsonRequest('/apibo/settings/mails.api_key', ['value' => 'super-secret-token']);
            self::assertSame(200, $controller->update($secret, 'mails.api_key')->getStatusCode());
            $em->clear();
            $response = $controller->list(Request::create('/apibo/settings'));
            self::assertSame(200, $response->getStatusCode());
            self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
            $body = (string) $response->getContent();
            self::assertStringContainsString('sender@example.test', $body);
            self::assertStringNotContainsString('super-secret-token', $body);
            $data = json_decode($body, true, 8, JSON_THROW_ON_ERROR)['data'];
            self::assertTrue($data[1]['configured']);
            self::assertNull($data[1]['value']);
        } finally {
            $em->getConnection()->close();
        }
    }

    public function testUnauthorizedRequestsCannotReadOrModify(): void
    {
        [$controller, $em] = $this->fixture(403);
        try {
            self::assertSame(403, $controller->list(Request::create('/apibo/settings'))->getStatusCode());
            self::assertSame(403, $controller->update(
                self::jsonRequest('/apibo/settings/mails.sender_email', ['value' => 'changed@example.test']),
                'mails.sender_email',
            )->getStatusCode());
            self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM symsvel_settings'));
        } finally {
            $em->getConnection()->close();
        }
    }

    public function testUnknownKeyAndMalformedValuesAreRejected(): void
    {
        [$controller, $em] = $this->fixture();
        try {
            self::assertSame(404, $controller->update(self::jsonRequest('/apibo/settings/unknown.key', ['value' => 'x']), 'unknown.key')->getStatusCode());
            self::assertSame(400, $controller->update(self::jsonRequest('/apibo/settings/mails.sender_email', ['value' => ['bad']]), 'mails.sender_email')->getStatusCode());
            self::assertSame(400, $controller->update(self::jsonRequest('/apibo/settings/mails.sender_email', ['value' => 'not-an-email']), 'mails.sender_email')->getStatusCode());
        } finally {
            $em->getConnection()->close();
        }
    }

    /** @param array<string, mixed> $payload */
    private static function jsonRequest(string $path, array $payload): Request
    {
        return Request::create($path, 'PUT', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
