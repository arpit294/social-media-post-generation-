<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\User;

class LlmClient
{
    public function __construct(
        private OllamaClient $ollama,
        private OpenAiClient $openAi,
        private ClaudeClient $claude
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, ?int $numPredict = null): string
    {
        $user = auth()->user();

        $key = $user instanceof User ? $user->activeApiKey : null;

        if ($key instanceof ApiKey) {
            if ($this->isClaude($key)) {
                return $this->claude->chat($key->api_key, $key->model, $messages, $numPredict);
            }

            return $this->openAi->chat($key->api_key, $key->model, $messages, $numPredict);
        }

        if (config('services.anthropic.key')) {
            return $this->claude->chat(
                (string) config('services.anthropic.key'),
                (string) config('services.anthropic.model', 'claude-3-5-sonnet-20241022'),
                $messages,
                $numPredict
            );
        }

        return $this->ollama->chat($messages, $numPredict);
    }

    public function model(): string
    {
        $user = auth()->user();

        $key = $user instanceof User ? $user->activeApiKey : null;

        if ($key instanceof ApiKey) {
            return $key->model ?: ($this->isClaude($key) ? 'claude-3-5-sonnet-20241022' : 'gpt-4o-mini');
        }

        if (config('services.anthropic.key')) {
            return (string) config('services.anthropic.model', 'claude-3-5-sonnet-20241022');
        }

        return (string) config('ollama.model');
    }

    public function isClaude(ApiKey $key): bool
    {
        $model = strtolower($key->model);
        $name = strtolower($key->name);
        $apiKey = $key->api_key;

        return str_starts_with($apiKey, 'sk-ant-')
            || str_contains($model, 'claude')
            || str_contains($name, 'claude')
            || str_contains($name, 'anthropic');
    }
}
