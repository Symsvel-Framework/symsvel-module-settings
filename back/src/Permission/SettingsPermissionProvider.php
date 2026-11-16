<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Permission;

use SymSvel\Module\Acls\Permission\PermissionDefinition;
use SymSvel\Module\Acls\Permission\PermissionProviderInterface;

/** Exact BO actions implemented by the module's authorized endpoints. */
final class SettingsPermissionProvider implements PermissionProviderInterface
{
    public function permissions(): iterable
    {
        yield new PermissionDefinition('settings', 'list', 'Consulter les paramètres');
        yield new PermissionDefinition('settings', 'update', 'Modifier les paramètres');
    }
}
