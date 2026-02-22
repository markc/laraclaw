<?php

namespace App\Services\Agent\Commands;

use App\Contracts\CommandHandler;
use App\DTOs\ClassifiedIntent;
use App\DTOs\IncomingMessage;
use App\Enums\IntentType;
use App\Models\AgentSession;
use App\Services\Agent\ModelRegistry;

class ModelCommand implements CommandHandler
{
    public function __construct(
        protected ModelRegistry $modelRegistry,
    ) {}

    public function name(): string
    {
        return 'model';
    }

    public function aliases(): array
    {
        return [];
    }

    public function description(): string
    {
        return 'Switch the model for the current session: /model <model-id>';
    }

    public function handle(IncomingMessage $message, array $args, ?AgentSession $session): ClassifiedIntent
    {
        $modelIds = collect($this->modelRegistry->getAvailableModelsList())
            ->pluck('id')
            ->all();

        if (empty($args)) {
            $current = $session?->getEffectiveModel() ?? config('agent.default_model');
            $available = implode(', ', $modelIds);

            return new ClassifiedIntent(
                type: IntentType::Command,
                message: $message->content,
                commandName: 'model',
                response: "Current model: {$current}\nAvailable: {$available}",
            );
        }

        $modelId = $args[0];

        if (! in_array($modelId, $modelIds)) {
            return new ClassifiedIntent(
                type: IntentType::Command,
                message: $message->content,
                commandName: 'model',
                commandArgs: $args,
                response: "Unknown model: {$modelId}\nAvailable: ".implode(', ', $modelIds),
            );
        }

        if ($session) {
            $provider = $this->modelRegistry->resolveProvider($modelId);
            $session->update([
                'model' => $modelId,
                'provider' => $provider,
            ]);

            return new ClassifiedIntent(
                type: IntentType::Command,
                message: $message->content,
                commandName: 'model',
                commandArgs: $args,
                response: "Model switched to: {$modelId} (provider: {$provider})",
            );
        }

        return new ClassifiedIntent(
            type: IntentType::Command,
            message: $message->content,
            commandName: 'model',
            commandArgs: $args,
            response: "Model will be set to {$modelId} when session starts.",
            metadata: ['model' => $modelId],
        );
    }
}
