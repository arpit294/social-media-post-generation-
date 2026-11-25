<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\User;

class LlmClient
{
    public function __construct(private OllamaClient $ollama, private OpenAiClient $openAi) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, ?int $numPredict = null): string
    {
        $user = auth()->user();

        $key = $user instanceof User ? $user->activeApiKey : null;

        if ($key instanceof ApiKey) {
            return $this->openAi->chat($key->api_key, $key->model, $messages, $numPredict);
        }

        return $this->ollama->chat($messages, $numPredict);
    }

    public function model(): string
    {
        $user = auth()->user();

        $key = $user instanceof User ? $user->activeApiKey : null;

        if ($key instanceof ApiKey) {
            return $key->model ?: 'gpt-4o-mini';
        }

        return (string) config('ollama.model');
    }
}
