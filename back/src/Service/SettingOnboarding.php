<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

/**
 * Read-only view of onboarding requirements, computed from live definitions.
 */
final readonly class SettingOnboarding
{
    public function __construct(private SettingManager $settings)
    {
    }

    /** @return array{complete: bool, missing: list<string>, pending: list<string>} */
    public function status(): array
    {
        $blocking = $this->settings->missingBlocking();
        $nonBlocking = $this->settings->missingNonBlocking();

        return [
            'complete' => $blocking === [],
            'missing' => array_map(static fn (SettingDefinition $definition): string => $definition->key, $blocking),
            'pending' => array_map(static fn (SettingDefinition $definition): string => $definition->key, $nonBlocking),
        ];
    }
}
