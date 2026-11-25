<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiClient
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(string $apiKey, string $model, array $messages, ?int $numPredict = null): string
    {
        $key = trim($apiKey);

        if ($key === '') {
            throw new OllamaException('Add an API key in Settings, or switch back to the local model.');
        }

        $payload = [
            'model' => $model !== '' ? $model : 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.4,
            'max_tokens' => $numPredict ?? (int) config('ollama.num_predict'),
        ];

        try {
            $response = Http::timeout((int) config('ollama.timeout'))
                ->connectTimeout(10)
                ->asJson()
                ->acceptJson()
                ->withToken($key)
                ->post('https://api.openai.com/v1/chat/completions', $payload);
        } catch (ConnectionException) {
            throw new OllamaException('OpenAI could not be reached. Check the connection, or switch back to the local model.');
        }

        if ($response->failed()) {
            throw new OllamaException('OpenAI returned HTTP '.$response->status().'. '.$this->safeBody($response->body(), $key));
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new OllamaException('OpenAI returned an empty reply. Try again.');
        }

        return $content;
    }

    private function safeBody(string $body, string $key): string
    {
        return substr(str_replace($key, '[key]', $body), 0, 300);
    }
}
