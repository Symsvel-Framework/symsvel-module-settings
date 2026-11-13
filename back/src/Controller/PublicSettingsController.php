<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Service\SettingRegistry;

#[Route('/api/settings/public', name: 'api_settings_public', methods: ['GET'])]
final readonly class PublicSettingsController
{
    public function __construct(
        private SettingRegistry $registry,
        private SettingManager $settings,
    ) {}

    public function __invoke(): JsonResponse
    {
        $data = [];
        foreach ($this->registry->all() as $definition) {
            // Never retrieve secrets, even if a provider is misconfigured.
            if (!$definition->public || $definition->secret) {
                continue;
            }
            $value = $this->settings->get($definition->key);
            if ($value !== null && trim($value) !== '') {
                $data[$definition->key] = $value;
            }
        }
        return new JsonResponse(['data' => $data]);
    }
}
