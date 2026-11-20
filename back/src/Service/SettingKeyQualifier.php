<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

/**
 * Qualifies a setting key using a module slug already verified by the module loader.
 * Does not discover modules or infer ownership from PHP namespaces.
 */
final class SettingKeyQualifier
{
    public function qualify(string $module, string $key): string
    {
        if (!preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $module)) {
            throw new \InvalidArgumentException('Invalid setting module identifier.');
        }
        if ($key === '' || $key !== trim($key)) {
            throw new \InvalidArgumentException('Invalid setting key.');
        }

        $prefix = $module.'.';
        if (str_contains($key, '.')) {
            if (!str_starts_with($key, $prefix)) {
                throw new \InvalidArgumentException('Setting key belongs to another module.');
            }
            $qualified = $key;
        } else {
            $qualified = $prefix.$key;
        }

        if (!preg_match('/^[a-z][a-z0-9_]*(?:[a-z0-9_-]*[a-z0-9_])?$/D', substr($qualified, strlen($prefix)))
            || strlen($qualified) > 190) {
            throw new \InvalidArgumentException('Invalid qualified setting key.');
        }

        return $qualified;
    }
}
