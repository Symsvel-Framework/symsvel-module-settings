<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Acls\Access\BoAccessGate;
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Service\SettingPresentation;
use SymSvel\Module\Settings\Service\SettingRegistry;

/** BO edits values only. Module definitions and secrets remain owned by Settings. */
final readonly class BoSettingsController
{
    public function __construct(
        private BoAccessGate $access,
        private SettingManager $settings,
        private SettingRegistry $registry,
        private SettingPresentation $presentation,
    ) {
    }

    #[Route('/apibo/settings', name: 'symsvel_settings_bo_list', methods: ['GET'], format: 'json')]
    public function list(Request $request): JsonResponse
    {
        $decision = $this->access->check($request, 'settings', 'list');
        if (!$decision->allowed()) {
            return self::failure($decision->status);
        }
        return self::reply(['data' => $this->presentation->all(
            $this->registry, fn (string $key): ?string => $this->settings->get($key),
        )]);
    }

    #[Route('/apibo/settings/{key}', name: 'symsvel_settings_bo_update', requirements: ['key' => '[a-z][a-z0-9_-]*\.[a-z][a-z0-9_]*'], methods: ['PUT'], format: 'json')]
    public function update(Request $request, string $key): JsonResponse
    {
        $decision = $this->access->check($request, 'settings', 'update');
        if (!$decision->allowed()) {
            return self::failure($decision->status);
        }
        $definition = $this->registry->get($key);
        if ($definition === null) {
            return self::failure(404);
        }
        if (!$definition->editable) {
            return self::failure(409);
        }
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')) {
            return self::failure(415);
        }
        $raw = $request->getContent();
        if (strlen($raw) > 8192) {
            return self::failure(400);
        }
        try {
            $payload = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::failure(400);
        }
        if (!is_array($payload) || array_is_list($payload)
            || array_keys($payload) !== ['value']
            || (!is_scalar($payload['value']) && $payload['value'] !== null)) {
            return self::failure(400);
        }
        try {
            $this->settings->set($key, $payload['value']);
            $this->settings->flush();
        } catch (\InvalidArgumentException) {
            return self::failure(400);
        }
        return self::reply(['status' => 'updated']);
    }

    private static function failure(int $status): JsonResponse
    {
        return self::reply(['error' => ['code' => match ($status) {
            400 => 'invalid_request', 401 => 'unauthorized', 403 => 'forbidden',
            404 => 'not_found', 409 => 'read_only_setting', 415 => 'unsupported_media_type',
            default => 'unavailable',
        }]], $status);
    }

    private static function reply(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }
}
