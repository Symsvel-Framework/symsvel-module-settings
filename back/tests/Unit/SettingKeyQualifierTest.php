<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\SettingKeyQualifier;

final class SettingKeyQualifierTest extends TestCase
{
    public function testPrefixesUnqualifiedKey(): void
    {
        self::assertSame('brevo.api_key', (new SettingKeyQualifier())->qualify('brevo', 'api_key'));
    }

    public function testPreservesQualifiedKeyOwnedByModule(): void
    {
        self::assertSame('my-module.api_key', (new SettingKeyQualifier())->qualify('my-module', 'my-module.api_key'));
    }

    public function testRejectsForeignModulePrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SettingKeyQualifier())->qualify('brevo', 'admins.api_key');
    }

    public function testRejectsNestedForeignOrAmbiguousKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SettingKeyQualifier())->qualify('brevo', 'brevo.api.key');
    }

    public function testRejectsInvalidModuleIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SettingKeyQualifier())->qualify('Brevo', 'api_key');
    }

    public function testRejectsWhitespaceInKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SettingKeyQualifier())->qualify('brevo', ' api_key ');
    }

    public function testRejectsEmptyParameterName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SettingKeyQualifier())->qualify('brevo', 'brevo.');
    }

    public function testRejectsQualifiedKeyLongerThanStorageLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SettingKeyQualifier())->qualify('brevo', str_repeat('a', 185));
    }
    public function testAcceptsKeyAtStorageLimit(): void
    {
        $key = str_repeat('a', 184);
        self::assertSame('brevo.'.$key, (new SettingKeyQualifier())->qualify('brevo', $key));
        self::assertSame(190, strlen('brevo.'.$key));
    }

    public function testRejectsForeignPrefixEvenWhenItStartsWithOwnerName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SettingKeyQualifier())->qualify('brevo', 'brevo-extra.api_key');
    }

    public function testRejectsInvalidParameterCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SettingKeyQualifier())->qualify('brevo', 'api key');
    }

}
