<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class SettingRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    /** @param iterable<SettingDefinitionProviderInterface> $providers */
    public function __construct(#[AutowireIterator('symsvel.settings.definition_provider')] iterable $providers)
    {
        foreach ($providers as $provider) {
            foreach ($provider->definitions() as $definition) {
                if (isset($this->definitions[$definition->key])) {
                    throw new \LogicException(sprintf('Setting "%s" is declared twice.', $definition->key));
                }
                $this->definitions[$definition->key] = $definition;
            }
        }
    }

    /** @return list<SettingDefinition> */
    public function all(): array { return array_values($this->definitions); }

    public function get(string $key): ?SettingDefinition
    {
        return $this->definitions[$key] ?? null;
    }
}
