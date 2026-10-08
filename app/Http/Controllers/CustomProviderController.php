<?php

namespace App\Http\Controllers;

use App\Ai\CustomModels;
use App\Ai\CustomProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The endpoints declared in WHALE_CUSTOM_PROVIDERS and the models kept for them.
 *
 * Discovery, import and sync are separate endpoints rather than one endpoint
 * with a mode flag, because they answer different questions and have different
 * failure modes: discovering cannot fail, syncing can leave the catalogue
 * untouched when an endpoint is unreachable, and importing never touches the
 * network at all.
 */
class CustomProviderController extends Controller
{
    public function __construct(
        private readonly CustomProviders $customProviders,
        private readonly CustomModels $customModels,
    ) {}

    /**
     * Every declared custom provider, with the models kept for each.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'providers' => array_map(fn (array $provider): array => [
                'name' => $provider['name'],
                'label' => $provider['label'],
                // The URL is the provider's own configuration and is safe to
                // show; the key never leaves the server.
                'url' => $provider['url'],
                'has_key' => $provider['key'] !== null,
                'models' => $this->customModels->for($provider['name']),
            ], $this->customProviders->all()),
        ]);
    }

    /**
     * What a provider is serving right now, without storing anything.
     */
    public function discover(string $provider): JsonResponse
    {
        $this->guardDeclared($provider);

        $discovered = $this->customModels->discover($provider);

        if ($discovered === null) {
            return response()->json([
                'reachable' => false,
                'models' => [],
                'message' => sprintf('Could not reach %s. Check the URL and the key.', $provider),
            ]);
        }

        return response()->json([
            'reachable' => true,
            'models' => $discovered,
        ]);
    }

    /**
     * Keep specific models for a provider.
     */
    public function import(Request $request, string $provider): JsonResponse
    {
        $this->guardDeclared($provider);

        $validated = $request->validate([
            'models' => ['required', 'array', 'min:1', 'max:200'],
            'models.*' => ['required', 'string', 'max:255'],
        ]);

        $imported = $this->customModels->import($provider, $validated['models']);

        return response()->json([
            'imported' => $imported,
            'models' => $this->customModels->for($provider),
        ]);
    }

    /**
     * Re-ask the endpoint and bring the stored models back into line with it.
     */
    public function sync(string $provider): JsonResponse
    {
        $this->guardDeclared($provider);

        $result = $this->customModels->sync($provider);

        return response()->json([
            ...$result,
            'models' => $this->customModels->for($provider),
        ]);
    }

    /**
     * Refuse a provider that was never declared.
     *
     * Without this the route would happily accept any name, discover against
     * whatever URL the caller liked, and report the result as configuration.
     *
     * @throws ValidationException
     */
    private function guardDeclared(string $provider): void
    {
        if (! $this->customProviders->isCustom($provider)) {
            throw ValidationException::withMessages([
                'provider' => sprintf(
                    'The "%s" provider is not declared. Add it to WHALE_CUSTOM_PROVIDERS in your .env file.',
                    $provider,
                ),
            ]);
        }
    }
}
