<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class SettingRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    /**
     * @param iterable<string, SettingDefinitionProviderInterface> $providers
     * @param array<string, string> $owners Verified service ID => module slug.
     */
    public function __construct(
        #[AutowireIterator('symsvel.settings.definition_provider')] iterable $providers,
        array $owners = [],
    ) {
        $qualifier = new SettingKeyQualifier();
        foreach ($providers as $serviceId => $provider) {
            if ($owners !== []) {
                if (!is_string($serviceId) || !isset($owners[$serviceId])) {
                    throw new \\LogicException('Settings provider has no verified module owner.');
                }
                $this->registerOwned($provider, $owners[$serviceId], $qualifier);
                continue;
            }
            foreach ($provider->definitions() as $definition) {
                if (isset($this->definitions[$definition->key])) {
                    throw new \LogicException(sprintf('Setting "%s" is declared twice.', $definition->key));
                }
                $this->definitions[$definition->key] = $definition;
            }
        }
    }

    /**
     * Registers definitions from a provider whose module slug has already been
     * verified by the module loader. The provider's original objects stay intact.
     *
     * @internal The container integration must supply verified module ownership.
     */
    public function registerOwned(
        SettingDefinitionProviderInterface $provider,
        string $module,
        SettingKeyQualifier $qualifier,
    ): void {
        foreach ($provider->definitions() as $definition) {
            $qualified = $definition->withKey($qualifier->qualify($module, $definition->key));
            if (isset($this->definitions[$qualified->key])) {
                throw new \LogicException(sprintf('Setting "%s" is declared twice.', $qualified->key));
            }
            $this->definitions[$qualified->key] = $qualified;
        }
    }

    /** @return list<SettingDefinition> */
    public function all(): array { return array_values($this->definitions); }

    public function get(string $key): ?SettingDefinition
    {
        return $this->definitions[$key] ?? null;
    }
}
