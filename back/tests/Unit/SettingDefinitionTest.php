<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Settings\Service\SettingDefinition;

final class SettingDefinitionTest extends TestCase
{
    public function testAcceptsSupportedTypes(): void
    {
        foreach (['string', 'boolean', 'email', 'url', 'choice'] as $type) {
            self::assertSame($type, (new SettingDefinition('example.key', 'Example', type: $type))->type);
        }
    }

    public function testChoiceMetadataIsValidatedAndPreserved(): void
    {
        $original = new SettingDefinition('transport', 'Transport', type: 'choice', choices: [
            'mailpit' => 'Mailpit',
            'smtp4dev' => 'smtp4dev',
        ]);
        self::assertSame($original->choices, $original->withKey('mails.transport')->choices);
    }

    public function testRejectsChoiceValuesOnOtherTypes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('example.name', 'Example', choices: ['mailpit' => 'Mailpit']);
    }

    public function testRejectsInvalidChoiceIdentifiers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('example.name', 'Example', type: 'choice', choices: ['smtp://host' => 'SMTP']);
    }

    public function testImmutableSettingMetadataSurvivesQualification(): void
    {
        $setting = new SettingDefinition('primary_language', 'Primary', editable: false);
        self::assertFalse($setting->withKey('languages.primary_language')->editable);
    }

    public function testDeclaredValuesSurviveQualification(): void
    {
        $definition = new SettingDefinition('mode', 'Mode', type: 'choice', required: true,
            choices: ['keep' => 'Keep', 'delete' => 'Delete'], editable: false,
            defaultValue: 'keep', forcedValue: 'keep');
        $qualified = $definition->withKey('example.mode');
        self::assertSame('keep', $qualified->defaultValue);
        self::assertSame('keep', $qualified->forcedValue);
        self::assertFalse($qualified->editable);
    }

    public function testInvalidDefaultRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('enabled', 'Enabled', type: 'boolean', required: true, defaultValue: 'yes');
    }

    public function testDefaultRequiresNonNullableSetting(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('enabled', 'Enabled', type: 'boolean', defaultValue: '1');
    }

    public function testForcedValueCannotBeEditable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('mode', 'Mode', forcedValue: 'keep');
    }

    public function testSecretCannotDeclareDefault(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('token', 'Token', required: true, secret: true, defaultValue: 'example');
    }

    public function testSecretCannotDeclareForcedValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('token', 'Token', secret: true, editable: false, forcedValue: 'example');
    }

    public function testForcedChoiceMustBeDeclared(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('mode', 'Mode', type: 'choice', choices: ['keep' => 'Keep'],
            editable: false, forcedValue: 'delete');
    }

    public function testRejectsUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('example.key', 'Example', type: 'json');
    }

    public function testRejectsPublicSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('example.key', 'Example', secret: true, public: true);
    }

    public function testRejectsBlockingSettingThatIsNotRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Blocking settings must be required.');
        new SettingDefinition('application.name', 'Name', blocking: true);
    }

    public function testAcceptsRequiredBlockingSetting(): void
    {
        $definition = new SettingDefinition('application.name', 'Name', required: true, blocking: true);
        self::assertTrue($definition->required);
        self::assertTrue($definition->blocking);
    }

    public function testRejectsKeyWithLeadingOrTrailingWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition(' application.name ', 'Name');
    }

    public function testAcceptsCanonicalKey(): void
    {
        self::assertSame('application.name', (new SettingDefinition('application.name', 'Name'))->key);
    }

    public function testRejectsEmptyKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingDefinition('', 'Example');
    }
    public function testQualifiedCopyPreservesMetadataAndOriginalDeclaration(): void
    {
        $original = new SettingDefinition(
            key: 'api_key',
            label: 'API key',
            group: 'integrations',
            type: 'string',
            required: true,
            secret: true,
            blocking: true,
            description: 'External service token',
        );

        $qualified = $original->withKey('brevo.api_key');

        self::assertNotSame($original, $qualified);
        self::assertSame('api_key', $original->key);
        self::assertSame('brevo.api_key', $qualified->key);
        self::assertSame($original->label, $qualified->label);
        self::assertSame($original->group, $qualified->group);
        self::assertSame($original->type, $qualified->type);
        self::assertSame($original->required, $qualified->required);
        self::assertSame($original->secret, $qualified->secret);
        self::assertSame($original->public, $qualified->public);
        self::assertSame($original->blocking, $qualified->blocking);
        self::assertSame($original->description, $qualified->description);
        self::assertSame($original->choices, $qualified->choices);
        self::assertSame($original->editable, $qualified->editable);
    }

    public function testQualifiedCopyStillValidatesKey(): void
    {
        $original = new SettingDefinition('api_key', 'API key');
        $this->expectException(\InvalidArgumentException::class);
        $original->withKey(' invalid ');
    }

}
