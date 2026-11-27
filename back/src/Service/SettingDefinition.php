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
        public bool $editable = true,
        public ?string $defaultValue = null,
        public ?string $forcedValue = null,
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
        if ($secret && ($defaultValue !== null || $forcedValue !== null)) {
            throw new \InvalidArgumentException('Secret settings cannot declare values.');
        }
        if ($defaultValue !== null && !$required) {
            throw new \InvalidArgumentException('Default values require a required setting.');
        }
        if ($forcedValue !== null && $editable) {
            throw new \InvalidArgumentException('Forced settings must be read-only.');
        }
        foreach ([$defaultValue, $forcedValue] as $declaredValue) {
            if ($declaredValue !== null && !$this->isValidDeclaredValue($declaredValue)) {
                throw new \InvalidArgumentException('Invalid declared setting value.');
            }
        }
    }
    private function isValidDeclaredValue(string $value): bool
    {
        if ($value === '' || trim($value) !== $value) {
            return false;
        }

        return match ($this->type) {
            'boolean' => in_array($value, ['0', '1'], true),
            'choice' => array_key_exists($value, $this->choices),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            default => true,
        };
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
            editable: $this->editable,
            defaultValue: $this->defaultValue,
            forcedValue: $this->forcedValue,
        );
    }
}
