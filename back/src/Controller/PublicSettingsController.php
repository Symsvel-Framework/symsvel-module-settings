<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Settings\Service\PublicSettingFilter;
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Service\SettingRegistry;

#[Route('/api/settings/public', name: 'api_settings_public', methods: ['GET'])]
final readonly class PublicSettingsController
{
    public function __construct(
        private SettingRegistry $registry,
        private SettingManager $settings,
        private PublicSettingFilter $filter,
    ) {}

    public function __invoke(): JsonResponse
    {
        $data = $this->filter->collect($this->registry, $this->settings->get(...));
        return new JsonResponse(['data' => $data]);
    }
}
