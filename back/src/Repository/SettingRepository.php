<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SymSvel\Module\Settings\Entity\Setting;

/** @extends ServiceEntityRepository<Setting> */
final class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /** @return array<string, Setting> */
    public function indexed(): array
    {
        $result = [];
        foreach ($this->findAll() as $setting) {
            $result[$setting->getKey()] = $setting;
        }
        return $result;
    }
}
