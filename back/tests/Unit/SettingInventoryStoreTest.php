<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\SettingInventoryStore;

final class SettingInventoryStoreTest extends TestCase
{
    public function testReadsMissingAndWritesIdempotently(): void
    {
        $backend = sys_get_temp_dir().'/symsvel-settings-inventory-'.bin2hex(random_bytes(8));
        mkdir($backend);
        try {
            $store = new SettingInventoryStore();
            self::assertSame(['schema' => 1, 'modules' => []], $store->read($backend));
            $inventory = ['schema' => 1, 'modules' => ['mails' => ['sender_email' => ['type' => 'email']]]];
            $path = $store->write($backend, $inventory);
            self::assertSame($inventory, $store->read($backend));
            $contents = file_get_contents($path);
            self::assertSame($path, $store->write($backend, $inventory));
            self::assertSame($contents, file_get_contents($path));
        } finally {
            @unlink($backend.'/.symsvel/modules-params.json');
            @rmdir($backend.'/.symsvel');
            @rmdir($backend);
        }
    }

    public function testRefusesSymlinkDestination(): void
    {
        $backend = sys_get_temp_dir().'/symsvel-settings-inventory-'.bin2hex(random_bytes(8));
        mkdir($backend);
        mkdir($backend.'/.symsvel');
        $target = $backend.'/target.json';
        file_put_contents($target, '{}');
        symlink($target, $backend.'/.symsvel/modules-params.json');
        try {
            $this->expectException(\RuntimeException::class);
            (new SettingInventoryStore())->write($backend, ['schema' => 1, 'modules' => []]);
        } finally {
            unlink($backend.'/.symsvel/modules-params.json');
            unlink($target);
            rmdir($backend.'/.symsvel');
            rmdir($backend);
        }
    }
}
