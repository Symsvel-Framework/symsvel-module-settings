<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

/**
 * Safe metadata projection for future authenticated BO consumers.
 */
final class SettingPresentation
{
    /**
     * @param callable(string): ?string $read
     * @return list<array{key: string, label: string, group: string, type: string, required: bool, secret: bool, public: bool, blocking: bool, description: ?string, choices: array<string, string>, editable: bool, configured: bool, value: ?string}>
     */
    public function all(SettingRegistry $registry, callable $read): array
    {
        $result = [];
        foreach ($registry->all() as $definition) {
            $value = $read($definition->key);
            $configured = $value !== null && trim($value) !== '';
            $result[] = [
                'key' => $definition->key,
                'label' => $definition->label,
                'group' => $definition->group,
                'type' => $definition->type,
                'required' => $definition->required,
                'secret' => $definition->secret,
                'public' => $definition->public,
                'blocking' => $definition->blocking,
                'description' => $definition->description,
                'choices' => $definition->choices,
                'editable' => $definition->editable,
                'configured' => $configured,
                'value' => $definition->secret ? null : $value,
            ];
        }
        return $result;
    }
}
