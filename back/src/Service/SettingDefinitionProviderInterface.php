<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('symsvel.settings.definition_provider')]
interface SettingDefinitionProviderInterface
{
    /** @return iterable<SettingDefinition> */
    public function definitions(): iterable;
}
