<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\SettingInventoryDiff;

final class SettingInventoryDiffTest extends TestCase
{
    public function testDetectsAddRemoveAndMetadataChangeWithoutTouchingOtherModules(): void
    {
        $previous = [
            'mails' => [
                'sender_email' => ['type' => 'string'],
                'obsolete' => ['type' => 'string'],
            ],
            'brevo' => ['api_key' => ['type' => 'string']],
        ];
        $desired = [
            'mails' => [
                'sender_email' => ['type' => 'email'],
                'sender_name' => ['type' => 'string'],
            ],
            'brevo' => ['api_key' => ['type' => 'string']],
        ];
        self::assertSame([
            'added' => ['mails.sender_name'],
            'removed' => ['mails.obsolete'],
            'changed' => ['mails.sender_email'],
        ], (new SettingInventoryDiff())->compare($previous, $desired));
        self::assertSame(['added' => [], 'removed' => [], 'changed' => []], (new SettingInventoryDiff())->compare($desired, $desired));
    }

    public function testRejectsInvalidInventoryKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SettingInventoryDiff())->compare([], ['mails' => ['../unsafe' => []]]);
    }
}
