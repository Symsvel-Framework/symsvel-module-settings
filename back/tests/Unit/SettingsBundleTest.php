<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use SymSvel\Module\Settings\Service\SettingKeyQualifier;
use SymSvel\Module\Settings\SettingsBundle;

final class SettingsBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        self::assertInstanceOf(SettingsBundle::class, new SettingsBundle());
    }
    public function testRegistersKeyQualifierForAutowiring(): void
    {
        $builder = new ContainerBuilder();
        $bundle = new SettingsBundle();
        $configurator = (new \ReflectionClass(ContainerConfigurator::class))->newInstanceWithoutConstructor();

        $bundle->loadExtension([], $configurator, $builder);

        self::assertTrue($builder->hasDefinition(SettingKeyQualifier::class));
        self::assertTrue($builder->getDefinition(SettingKeyQualifier::class)->isAutowired());
    }
}
