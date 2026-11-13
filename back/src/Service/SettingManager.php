<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

use Doctrine\ORM\EntityManagerInterface;
use SymSvel\Module\Settings\Entity\Setting;
use SymSvel\Module\Settings\Repository\SettingRepository;

final readonly class SettingManager
{
    public function __construct(
        private SettingRegistry $registry,
        private SettingRepository $repository,
        private EntityManagerInterface $entityManager,
        private SettingSecretCipher $cipher,
    ) {}

    public function get(string $key): ?string
    {
        $definition = $this->registry->get($key);
        if ($definition === null) {
            throw new \InvalidArgumentException(sprintf('Unknown setting "%s".', $key));
        }
        $value = $this->repository->findOneBy(['key' => $key])?->getValue();
        return $value !== null && $definition->secret ? $this->cipher->decrypt($value) : $value;
    }

    public function set(string $key, mixed $value): void
    {
        $definition = $this->registry->get($key);
        if ($definition === null) {
            throw new \InvalidArgumentException(sprintf('Unknown setting "%s".', $key));
        }
        $normalized = $this->normalize($definition, $value);
        $setting = $this->repository->findOneBy(['key' => $key]) ?? new Setting($key);
        $setting->setValue($normalized !== null && $definition->secret ? $this->cipher->encrypt($normalized) : $normalized);
        $this->entityManager->persist($setting);
    }

    /** @return list<SettingDefinition> */
    public function missingRequired(): array
    {
        $stored = $this->repository->indexed();
        return array_values(array_filter(
            $this->registry->all(),
            static fn (SettingDefinition $definition): bool => $definition->required
                && trim((string) (($stored[$definition->key] ?? null)?->getValue() ?? '')) === '',
        ));
    }

    /** @return list<SettingDefinition> */
    public function missingBlocking(): array
    {
        return array_values(array_filter($this->missingRequired(), static fn (SettingDefinition $definition): bool => $definition->blocking));
    }

    /** @return list<SettingDefinition> */
    public function missingNonBlocking(): array
    {
        return array_values(array_filter($this->missingRequired(), static fn (SettingDefinition $definition): bool => !$definition->blocking));
    }

    public function isComplete(): bool { return $this->missingBlocking() === []; }
    public function flush(): void { $this->entityManager->flush(); }

    private function normalize(SettingDefinition $definition, mixed $value): ?string
    {
        if ($value !== null && !is_scalar($value)) {
            throw new \InvalidArgumentException('Invalid setting value.');
        }
        $value = $value === null ? '' : trim((string) $value);
        if ($definition->required && $value === '') {
            throw new \InvalidArgumentException(sprintf('%s is required.', $definition->label));
        }
        if ($definition->type === 'boolean' && !in_array($value, ['0', '1'], true)) {
            throw new \InvalidArgumentException('Boolean settings accept only 0 or 1.');
        }
        if ($definition->type === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(sprintf('%s must be a valid email.', $definition->label));
        }
        if ($definition->type === 'url' && $value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException(sprintf('%s must be a valid URL.', $definition->label));
        }
        return $value === '' ? null : $value;
    }
}
