<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Settings\Controller\PublicSettingsController;

final class PublicSettingsControllerTest extends TestCase
{
    public function testRouteIsReadOnlyAndExplicitlyPublic(): void
    {
        $routes = (new ReflectionClass(PublicSettingsController::class))->getAttributes(Route::class);
        self::assertCount(1, $routes);
        $arguments = $routes[0]->getArguments();
        self::assertSame('/api/settings/public', $arguments[0]);
        self::assertSame(['GET'], $arguments['methods']);
    }
}
