<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

/**
 * Security boundary for the anonymous settings endpoint.
 */
final class PublicSettingFilter
{
    /**
     * @param callable(string): ?string $read
     * @return array<string, string>
     */
    public function collect(SettingRegistry $registry, callable $read): array
    {
        $data = [];
        foreach ($registry->all() as $definition) {
            if (!$definition->public || $definition->secret) {
                continue;
            }
            $value = $read($definition->key);
            if ($value !== null && trim($value) !== '') {
                $data[$definition->key] = $value;
            }
        }
        return $data;
    }
}
