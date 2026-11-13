<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use SymSvel\Module\Settings\DependencyInjection\SettingProviderOwnershipPass;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use SymSvel\Module\Settings\Controller\PublicSettingsController;
use SymSvel\Module\Settings\Repository\SettingRepository;
use SymSvel\Module\Settings\Service\PublicSettingFilter;
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Service\SettingInventory;
use SymSvel\Module\Settings\Service\SettingInventoryDiff;
use SymSvel\Module\Settings\Service\SettingInventoryStore;
use SymSvel\Module\Settings\Service\SettingKeyQualifier;
use SymSvel\Module\Settings\Service\SettingOnboarding;
use SymSvel\Module\Settings\Service\SettingPresentation;
use SymSvel\Module\Settings\Service\SettingRegistry;
use SymSvel\Module\Settings\Service\SettingSecretCipher;

final class SettingsBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new SettingProviderOwnershipPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        foreach ([SettingRepository::class, SettingRegistry::class, SettingKeyQualifier::class, SettingManager::class, SettingInventory::class, SettingInventoryDiff::class, SettingInventoryStore::class, SettingSecretCipher::class, SettingOnboarding::class, SettingPresentation::class, PublicSettingFilter::class, PublicSettingsController::class] as $service) {
            $builder->register($service, $service)->setAutowired(true)->setAutoconfigured(true);
        }
        $builder->getDefinition(PublicSettingsController::class)->addTag('controller.service_arguments');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->extension('doctrine', [
            'orm' => [
                'mappings' => [
                    'SymSvelSettings' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'SymSvel\\Module\\Settings\\Entity',
                        'alias' => 'SymSvelSettings',
                    ],
                ],
            ],
        ]);
    }
}
