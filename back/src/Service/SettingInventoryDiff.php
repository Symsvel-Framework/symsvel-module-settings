<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

/**
 * Pure comparison of the installed parameter dictionary and desired definitions.
 * No database or filesystem writes occur here.
 */
final class SettingInventoryDiff
{
    /**
     * @param array<string, array<string, array<string, mixed>>> $previous
     * @param array<string, array<string, array<string, mixed>>> $desired
     * @return array{added: list<string>, removed: list<string>, changed: list<string>}
     */
    public function compare(array $previous, array $desired): array
    {
        $old = $this->flatten($previous);
        $new = $this->flatten($desired);
        $added = array_keys(array_diff_key($new, $old));
        $removed = array_keys(array_diff_key($old, $new));
        $changed = [];
        foreach (array_intersect_key($new, $old) as $key => $metadata) {
            if ($metadata !== $old[$key]) {
                $changed[] = $key;
            }
        }
        sort($added, SORT_STRING);
        sort($removed, SORT_STRING);
        sort($changed, SORT_STRING);
        return ['added' => $added, 'removed' => $removed, 'changed' => $changed];
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $modules
     * @return array<string, array<string, mixed>>
     */
    private function flatten(array $modules): array
    {
        $result = [];
        foreach ($modules as $module => $parameters) {
            if (!is_string($module) || !preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $module) || !is_array($parameters)) {
                throw new \InvalidArgumentException('Invalid parameter module inventory.');
            }
            foreach ($parameters as $key => $metadata) {
                if (!is_string($key) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $key) || !is_array($metadata)) {
                    throw new \InvalidArgumentException('Invalid parameter inventory entry.');
                }
                $qualified = $module.'.'.$key;
                if (strlen($qualified) > 190 || isset($result[$qualified])) {
                    throw new \InvalidArgumentException('Duplicate or oversized parameter key.');
                }
                ksort($metadata, SORT_STRING);
                $result[$qualified] = $metadata;
            }
        }
        return $result;
    }
}
