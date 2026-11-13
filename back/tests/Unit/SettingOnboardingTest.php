<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\SettingDefinition;
use SymSvel\Module\Settings\Service\SettingOnboarding;

final class SettingOnboardingTest extends TestCase
{
    public function testOnlyBlockingMissingSettingsPreventCompletion(): void
    {
        $blocking = [new SettingDefinition('application.name', 'Name', required: true, blocking: true)];
        $pending = [new SettingDefinition('mail.sender', 'Sender', required: true)];

        self::assertSame([
            'complete' => false,
            'missing' => ['application.name'],
            'pending' => ['mail.sender'],
        ], SettingOnboarding::summarize($blocking, $pending));

        self::assertSame([
            'complete' => true,
            'missing' => [],
            'pending' => ['mail.sender'],
        ], SettingOnboarding::summarize([], $pending));
    }

    public function testNoStoredCompletionFlagIsRequired(): void
    {
        self::assertSame([
            'complete' => true,
            'missing' => [],
            'pending' => [],
        ], SettingOnboarding::summarize([], []));
    }
}
