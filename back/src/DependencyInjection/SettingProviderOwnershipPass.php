<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use SymSvel\Module\Settings\Service\SettingRegistry;

/**
 * Associates tagged providers with verified Composer module inventory entries.
 * Executed only during container compilation, never during HTTP requests.
 */
final class SettingProviderOwnershipPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SettingRegistry::class)) {
            return;
        }

        $projectDir = $container->getParameter('kernel.project_dir');
        if (!is_string($projectDir)) {
            throw new \LogicException('Invalid Symfony project directory.');
        }
        $inventory = $projectDir.'/.symsvel/modules.json';
        if (!is_file($inventory)) {
            // Standalone module tests and applications not yet synchronized.
            return;
        }
        if (is_link($inventory)) {
            throw new \LogicException('Settings module inventory must not be a symlink.');
        }

        $data = json_decode((string) file_get_contents($inventory), true, 512, JSON_THROW_ON_ERROR);
        if (($data['schema'] ?? null) !== 1 || !is_array($data['modules'] ?? null)) {
            throw new \LogicException('Invalid verified SymSvel module inventory.');
        }

        $paths = [];
        foreach ($data['modules'] as $entry) {
            $slug = $entry['module'] ?? null;
            $path = $entry['path'] ?? null;
            if (!is_string($slug) || !preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $slug)
                || !is_string($path) || ($resolved = realpath($path)) === false) {
                throw new \LogicException('Invalid module ownership metadata.');
            }
            if (isset($paths[$slug])) {
                throw new \LogicException('Duplicate module in ownership metadata.');
            }
            $paths[$slug] = rtrim($resolved, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        }

        $owners = [];
        foreach ($container->findTaggedServiceIds('symsvel.settings.definition_provider') as $id => $tags) {
            $definition = $container->findDefinition($id);
            $class = $definition->getClass() ?: $id;
            if (!is_string($class) || !class_exists($class)) {
                throw new \LogicException(sprintf('Unable to resolve Settings provider "%s".', $id));
            }
            $reflection = new \ReflectionClass($class);
            $filename = $reflection->getFileName();
            $file = is_string($filename) ? realpath($filename) : false;
            if ($file === false) {
                throw new \LogicException(sprintf('Settings provider "%s" has no source file.', $id));
            }
            $matches = [];
            foreach ($paths as $slug => $path) {
                if (str_starts_with($file, $path)) {
                    $matches[] = $slug;
                }
            }
            if (count($matches) !== 1) {
                throw new \LogicException(sprintf('Settings provider "%s" must belong to exactly one verified module.', $id));
            }
            // The tagged iterator must expose service IDs, not numeric positions.
            // Preserve existing tag attributes while adding the explicit index.
            $definition->clearTag('symsvel.settings.definition_provider');
            foreach ($tags as $attributes) {
                $definition->addTag('symsvel.settings.definition_provider', [...$attributes, 'settings_provider_id' => $id]);
            }
            $owners[$id] = $matches[0];
        }

        if ($owners !== []) {
            $container->getDefinition(SettingRegistry::class)->setArgument(1, $owners);
        }
    }
}
