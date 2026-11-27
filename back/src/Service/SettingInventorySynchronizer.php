<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

use Doctrine\ORM\EntityManagerInterface;
use SymSvel\Module\Settings\Entity\Setting;
use SymSvel\Module\Settings\Repository\SettingRepository;

/** Reconciles persisted rows with verified module definitions. */
final class SettingInventorySynchronizer
{
    public function __construct(
        private readonly SettingRegistry $registry,
        private readonly SettingRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingInventory $inventory,
        private readonly SettingInventoryStore $store,
        private readonly SettingInventoryDiff $diff,
    ) {}

    /** @return array{added: list<string>, removed: list<string>, changed: list<string>} */
    public function sync(string $backend): array
    {
        $existed = $this->store->exists($backend);
        $previous = $this->store->read($backend);
        $desired = $this->inventory->fromRegistry($this->registry);
        $changes = $this->diff->compare($previous['modules'], $desired['modules']);
        $stored = $this->repository->indexed();

        // Preserve existing values, except explicit module-owned enforced values.
        $desiredKeys = [];
        foreach ($desired['modules'] as $module => $parameters) {
            foreach ($parameters as $key => $metadata) {
                $qualified = $module.'.'.$key;
                $desiredKeys[$qualified] = $metadata;
            }
        }
        foreach ($changes['changed'] as $key) {
            $parts = explode('.', $key, 2);
            $old = $previous['modules'][$parts[0]][$parts[1]];
            $new = $desiredKeys[$key];
            if (($old['type'] ?? null) !== ($new['type'] ?? null)
                || ($old['secret'] ?? null) !== ($new['secret'] ?? null)) {
                if (isset($stored[$key]) && $stored[$key]->getValue() !== null) {
                    throw new \DomainException('Incompatible parameter metadata change with existing value: '.$key);
                }
            }
        }
        $connection = $this->entityManager->getConnection();
        $published = false;
        $connection->beginTransaction();
        try {
            foreach ($desiredKeys as $key => $_) {
                $definition = $this->registry->get($key);
                if ($definition === null) {
                    throw new \LogicException('Missing registered setting definition: '.$key);
                }
                $setting = $stored[$key] ?? new Setting($key);
                if ($definition->forcedValue !== null) {
                    $setting->setValue($definition->forcedValue);
                } elseif ($setting->getValue() === null && $definition->defaultValue !== null) {
                    $setting->setValue($definition->defaultValue);
                }
                if (!isset($stored[$key])) {
                    $this->entityManager->persist($setting);
                }
            }
            foreach ($changes['removed'] as $key) {
                if (isset($stored[$key])) {
                    $this->entityManager->remove($stored[$key]);
                }
            }
            $this->entityManager->flush();
            // Publish before commit so a failed write can roll back database changes.
            // The enclosing Composer transaction provides recovery for post-publication failures.
            $this->store->write($backend, $desired);
            $published = true;
            $connection->commit();
        } catch (\Throwable $failure) {
            $recoveryErrors = [];
            if ($connection->isTransactionActive()) {
                try {
                    $connection->rollBack();
                } catch (\Throwable $rollbackFailure) {
                    $recoveryErrors[] = 'Database rollback failed: '.$rollbackFailure->getMessage();
                }
            }
            if ($published) {
                try {
                    $this->store->restore($backend, $previous, $existed);
                } catch (\Throwable $restoreFailure) {
                    $recoveryErrors[] = 'Inventory restore failed: '.$restoreFailure->getMessage();
                }
            }
            if ($recoveryErrors !== []) {
                throw new \RuntimeException('Settings synchronization recovery incomplete: '.implode('; ', $recoveryErrors), 0, $failure);
            }
            throw $failure;
        }
        return $changes;
    }
}
