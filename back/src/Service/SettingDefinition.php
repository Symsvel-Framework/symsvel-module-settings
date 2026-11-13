<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

final readonly class SettingDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $group = 'application',
        public string $type = 'string',
        public bool $required = false,
        public bool $secret = false,
        public bool $public = false,
        public bool $blocking = false,
        public ?string $description = null,
    ) {
        if ($key === '' || strlen($key) > 190) {
            throw new \InvalidArgumentException('Invalid setting definition key.');
        }
        if (!in_array($type, ['string', 'boolean', 'email', 'url'], true)) {
            throw new \InvalidArgumentException('Unsupported setting type.');
        }
        if ($blocking && !$required) {
            throw new \InvalidArgumentException('Blocking settings must be required.');
        }
        if ($secret && $public) {
            throw new \InvalidArgumentException('Secret settings cannot be public.');
        }
    }
}
