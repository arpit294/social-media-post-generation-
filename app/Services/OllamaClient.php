<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OllamaClient
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, ?int $numPredict = null): string
    {
        $url = rtrim((string) config('ollama.url'), '/').'/api/chat';

        $payload = [
            'model' => config('ollama.model'),
            'stream' => false,
            'think' => (bool) config('ollama.think'),
            'messages' => $messages,
            'options' => [
                'temperature' => 0.4,
                'num_ctx' => (int) config('ollama.num_ctx'),
                'num_predict' => $numPredict ?? (int) config('ollama.num_predict'),
            ],
        ];

        try {
            $response = Http::timeout((int) config('ollama.timeout'))
                ->connectTimeout(10)
                ->asJson()
                ->acceptJson()
                ->post($url, $payload);
        } catch (ConnectionException) {
            throw new OllamaException(
                'Ollama is not running at '.config('ollama.url').'. Start Ollama, then try again.'
            );
        }

        if ($response->failed()) {
            throw new OllamaException(
                'Ollama returned HTTP '.$response->status().'. '.$response->body()
            );
        }

        $content = $response->json('message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new OllamaException('The model returned an empty post. Try again.');
        }

        return $content;
    }
}
