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
        /** @var array<string, string> value => display label; module-owned, never writable by BO. */
        public array $choices = [],
    ) {
        if ($key === '' || $key !== trim($key) || strlen($key) > 190) {
            throw new \InvalidArgumentException('Invalid setting definition key.');
        }
        if (!in_array($type, ['string', 'boolean', 'email', 'url', 'choice'], true)) {
            throw new \InvalidArgumentException('Unsupported setting type.');
        }
        if (($type !== 'choice' && $choices !== []) || count($choices) > 100) {
            throw new \InvalidArgumentException('Only choice settings can declare choices.');
        }
        foreach ($choices as $value => $choiceLabel) {
            if (!is_string($value) || !preg_match('/^[a-z][a-z0-9_-]{0,99}$/D', $value)
                || !is_string($choiceLabel) || trim($choiceLabel) === '' || strlen($choiceLabel) > 150) {
                throw new \InvalidArgumentException('Invalid setting choice.');
            }
        }
        if ($blocking && !$required) {
            throw new \InvalidArgumentException('Blocking settings must be required.');
        }
        if ($secret && $public) {
            throw new \InvalidArgumentException('Secret settings cannot be public.');
        }
    }
    /**
     * Returns a qualified copy without mutating the provider's declaration.
     */
    public function withKey(string $key): self
    {
        return new self(
            key: $key,
            label: $this->label,
            group: $this->group,
            type: $this->type,
            required: $this->required,
            secret: $this->secret,
            public: $this->public,
            blocking: $this->blocking,
            description: $this->description,
            choices: $this->choices,
        );
    }
}
