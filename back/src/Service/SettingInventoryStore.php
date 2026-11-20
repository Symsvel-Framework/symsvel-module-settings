<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

/**
 * Filesystem persistence for the last successfully synchronized definitions.
 * The caller is responsible for coordinating database commit and publication.
 */
final class SettingInventoryStore
{
    /** @return array{schema: int, modules: array<string, mixed>} */
    public function read(string $backend): array
    {
        $path = $this->path($backend);
        if (is_link($path)) {
            throw new \RuntimeException('Symbolic parameter inventory is forbidden.');
        }
        if (!is_file($path)) {
            return ['schema' => 1, 'modules' => []];
        }
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['schema'] ?? null) !== 1 || !is_array($data['modules'] ?? null)
            || array_is_list($data['modules']) && $data['modules'] !== []) {
            throw new \RuntimeException('Invalid parameter inventory schema.');
        }
        return $data;
    }

    /** @param array{schema: int, modules: array<string, mixed>} $inventory */
    public function write(string $backend, array $inventory): string
    {
        if (($inventory['schema'] ?? null) !== 1 || !is_array($inventory['modules'] ?? null)) {
            throw new \InvalidArgumentException('Invalid parameter inventory.');
        }
        // Validate the same keys and metadata structure used by the diff engine.
        (new SettingInventoryDiff())->compare([], $inventory['modules']);
        $path = $this->path($backend);
        $directory = dirname($path);
        if (is_link($directory) || (!is_dir($directory) && !mkdir($directory, 0775) && !is_dir($directory))) {
            throw new \RuntimeException('Invalid parameter inventory directory.');
        }
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new \RuntimeException('Invalid parameter inventory destination.');
        }
        $json = json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        if (is_file($path) && file_get_contents($path) === $json) {
            return $path;
        }
        $temporary = tempnam($directory, '.modules-params-');
        if ($temporary === false) {
            throw new \RuntimeException('Unable to create temporary parameter inventory.');
        }
        try {
            if (file_put_contents($temporary, $json) === false || !rename($temporary, $path)) {
                throw new \RuntimeException('Unable to publish parameter inventory.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
        return $path;
    }

    /** Restore the previous published state, including absence of the file. */
    public function restore(string $backend, array $previous, bool $existed = true): void
    {
        if ($existed) {
            $this->write($backend, $previous);
            return;
        }
        $path = $this->path($backend);
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new \RuntimeException('Invalid parameter inventory destination.');
        }
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('Unable to remove newly created parameter inventory.');
        }
    }

    public function exists(string $backend): bool
    {
        $path = $this->path($backend);
        if (is_link($path)) {
            throw new \RuntimeException('Symbolic parameter inventory is forbidden.');
        }
        return is_file($path);
    }

    private function path(string $backend): string
    {
        $backend = realpath($backend);
        if ($backend === false || !is_dir($backend)) {
            throw new \InvalidArgumentException('Backend directory not found.');
        }
        return $backend.'/.symsvel/modules-params.json';
    }
}
