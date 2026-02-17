<?php

namespace App\Services\Agent;

class ModelRegistry
{
    /**
     * Get available models grouped by provider.
     * Only includes providers with configured API keys.
     */
    public function getAvailableModels(): array
    {
        $allModels = config('agent.models', []);
        $providerKeys = config('agent.provider_keys', []);
        $available = [];

        foreach ($allModels as $provider => $models) {
            $envKey = $providerKeys[$provider] ?? null;

            if ($envKey && ! empty(env($envKey))) {
                $available[$provider] = collect($models)->map(fn ($name, $id) => [
                    'id' => $id,
                    'name' => $name,
                    'provider' => $provider,
                ])->values()->all();
            }
        }

        return $available;
    }

    /**
     * Get a flat list of all available models.
     */
    public function getAvailableModelsList(): array
    {
        $grouped = $this->getAvailableModels();
        $flat = [];

        foreach ($grouped as $models) {
            foreach ($models as $model) {
                $flat[] = $model;
            }
        }

        return $flat;
    }

    /**
     * Resolve provider for a given model ID.
     */
    public function resolveProvider(string $modelId): ?string
    {
        foreach (config('agent.models', []) as $provider => $models) {
            if (array_key_exists($modelId, $models)) {
                return $provider;
            }
        }

        return null;
    }
}
