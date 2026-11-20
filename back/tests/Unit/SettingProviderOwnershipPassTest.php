<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use SymSvel\Module\Settings\DependencyInjection\SettingProviderOwnershipPass;
use SymSvel\Module\Settings\Service\SettingDefinition;
use SymSvel\Module\Settings\Service\SettingDefinitionProviderInterface;
use SymSvel\Module\Settings\Service\SettingRegistry;

final class OwnershipFixtureProvider implements SettingDefinitionProviderInterface
{
    public function definitions(): iterable
    {
        yield new SettingDefinition('api_key', 'API key');
    }
}

final class SettingProviderOwnershipPassTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/settings-ownership-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/.symsvel', 0775, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->root.'/.symsvel/modules.json');
        @rmdir($this->root.'/.symsvel');
        @rmdir($this->root);
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->root);
        $container->register(SettingRegistry::class, SettingRegistry::class);
        $container->register(OwnershipFixtureProvider::class, OwnershipFixtureProvider::class)
            ->addTag('symsvel.settings.definition_provider');
        return $container;
    }

    private function inventory(string $path): void
    {
        file_put_contents($this->root.'/.symsvel/modules.json', json_encode([
            'schema' => 1,
            'modules' => [['module' => 'brevo', 'path' => $path]],
        ], JSON_THROW_ON_ERROR));
    }

    public function testAssignsProviderToVerifiedModuleSourcePath(): void
    {
        $this->inventory(__DIR__);
        $container = $this->container();
        (new SettingProviderOwnershipPass())->process($container);
        self::assertSame(
            [['settings_provider_id' => OwnershipFixtureProvider::class]],
            $container->getDefinition(OwnershipFixtureProvider::class)->getTag('symsvel.settings.definition_provider'),
        );
        self::assertSame(
            [OwnershipFixtureProvider::class => 'brevo'],
            $container->getDefinition(SettingRegistry::class)->getArgument(1),
        );
    }

    public function testRejectsProviderOutsideVerifiedModule(): void
    {
        $this->inventory($this->root);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('exactly one verified module');
        (new SettingProviderOwnershipPass())->process($this->container());
    }

    public function testMissingInventoryKeepsStandaloneTestsCompatible(): void
    {
        $container = $this->container();
        (new SettingProviderOwnershipPass())->process($container);
        self::assertSame([], $container->getDefinition(SettingRegistry::class)->getArguments());
    }

    public function testRejectsMalformedInventory(): void
    {
        file_put_contents($this->root.'/.symsvel/modules.json', '{"schema":2,"modules":[]}');
        $this->expectException(\LogicException::class);
        (new SettingProviderOwnershipPass())->process($this->container());
    }
}
