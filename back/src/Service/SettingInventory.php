<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

/** Canonical, value-free projection of registered module settings. */
final class SettingInventory
{
    /**
     * @return array{schema: int, modules: array<string, array<string, array<string, mixed>>>}
     */
    public function fromRegistry(SettingRegistry $registry): array
    {
        $modules = [];
        foreach ($registry->all() as $definition) {
            $parts = explode('.', $definition->key, 2);
            if (count($parts) !== 2 || !preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $parts[0])
                || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $parts[1])) {
                throw new \LogicException(sprintf('Setting "%s" is not qualified by a valid module.', $definition->key));
            }
            [$module, $key] = $parts;
            if (isset($modules[$module][$key])) {
                throw new \LogicException('Duplicate setting in inventory.');
            }
            $modules[$module][$key] = [
                'label' => $definition->label,
                'group' => $definition->group,
                'type' => $definition->type,
                'required' => $definition->required,
                'secret' => $definition->secret,
                'public' => $definition->public,
                'blocking' => $definition->blocking,
                'description' => $definition->description,
            ];
        }
        ksort($modules, SORT_STRING);
        foreach ($modules as &$parameters) {
            ksort($parameters, SORT_STRING);
        }
        unset($parameters);
        return ['schema' => 1, 'modules' => $modules];
    }
}
