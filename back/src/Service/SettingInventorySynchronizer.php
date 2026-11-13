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
        $previous = $this->store->read($backend);
        $desired = $this->inventory->fromRegistry($this->registry);
        $changes = $this->diff->compare($previous['modules'], $desired['modules']);
        $stored = $this->repository->indexed();

        // An existing row may predate the inventory. Never overwrite its value.
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
        $connection->beginTransaction();
        try {
            foreach ($desiredKeys as $key => $_) {
                if (!isset($stored[$key])) {
                    $this->entityManager->persist(new Setting($key));
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
            $connection->commit();
        } catch (\Throwable $failure) {
            $connection->rollBack();
            throw $failure;
        }
        return $changes;
    }
}
