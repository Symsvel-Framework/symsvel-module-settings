<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\SettingSecretCipher;

final class SettingSecretCipherTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $cipher = new SettingSecretCipher('test-app-secret');
        $encrypted = $cipher->encrypt('sensitive-value');
        self::assertNotSame('sensitive-value', $encrypted);
        self::assertSame('sensitive-value', $cipher->decrypt($encrypted));
        self::assertNotSame($encrypted, $cipher->encrypt('sensitive-value'));
    }

    public function testRejectsWrongKey(): void
    {
        $encrypted = (new SettingSecretCipher('first-key'))->encrypt('sensitive-value');
        $this->expectException(\RuntimeException::class);
        (new SettingSecretCipher('second-key'))->decrypt($encrypted);
    }

    public function testRejectsInvalidCiphertext(): void
    {
        $this->expectException(\RuntimeException::class);
        (new SettingSecretCipher('test-app-secret'))->decrypt('not-base64***');
    }

    public function testRejectsEmptyKernelSecret(): void
    {
        $this->expectException(\LogicException::class);
        new SettingSecretCipher('');
    }
}
