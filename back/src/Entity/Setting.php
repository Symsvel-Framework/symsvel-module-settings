<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Entity;

use Doctrine\ORM\Mapping as ORM;
use SymSvel\Module\Settings\Repository\SettingRepository;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ORM\Table(name: 'symsvel_settings')]
#[ORM\UniqueConstraint(name: 'uniq_symsvel_settings_key', columns: ['setting_key'])]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'setting_key', length: 190)]
    private string $key;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    public function __construct(string $key)
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 190) {
            throw new \InvalidArgumentException('Invalid setting key.');
        }
        $this->key = $key;
    }

    public function getId(): ?int { return $this->id; }
    public function getKey(): string { return $this->key; }
    public function getValue(): ?string { return $this->value; }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}
