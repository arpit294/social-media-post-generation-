<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ClaudeClient
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(string $apiKey, string $model, array $messages, ?int $numPredict = null): string
    {
        $key = trim($apiKey);

        if ($key === '') {
            throw new OllamaException('Add a Claude API key in Settings or .env, or switch back to the local model.');
        }

        $systemParts = [];
        $anthropicMessages = [];

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = (string) ($msg['content'] ?? '');

            if ($role === 'system') {
                if (trim($content) !== '') {
                    $systemParts[] = $content;
                }
            } else {
                $anthropicRole = $role === 'assistant' ? 'assistant' : 'user';
                $lastIndex = count($anthropicMessages) - 1;

                if ($lastIndex >= 0 && $anthropicMessages[$lastIndex]['role'] === $anthropicRole) {
                    $anthropicMessages[$lastIndex]['content'] .= "\n\n".$content;
                } else {
                    $anthropicMessages[] = [
                        'role' => $anthropicRole,
                        'content' => $content,
                    ];
                }
            }
        }

        if (! empty($anthropicMessages) && $anthropicMessages[0]['role'] !== 'user') {
            array_unshift($anthropicMessages, [
                'role' => 'user',
                'content' => 'Please generate the response according to instructions.',
            ]);
        }

        if (empty($anthropicMessages)) {
            $anthropicMessages[] = [
                'role' => 'user',
                'content' => 'Generate the post according to instructions.',
            ];
        }

        $systemPrompt = implode("\n\n", $systemParts);
        $chosenModel = $model !== '' ? $model : 'claude-3-5-sonnet-20241022';

        $payload = [
            'model' => $chosenModel,
            'max_tokens' => $numPredict ?? 2048,
            'messages' => $anthropicMessages,
            'temperature' => 0.5,
        ];

        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        try {
            $response = Http::timeout((int) config('ollama.timeout', 600))
                ->connectTimeout(10)
                ->asJson()
                ->acceptJson()
                ->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => '2023-06-01',
                ])
                ->post('https://api.anthropic.com/v1/messages', $payload);
        } catch (ConnectionException) {
            throw new OllamaException('Claude could not be reached. Check your connection or API key, or switch back to the local model.');
        }

        if ($response->failed()) {
            throw new OllamaException('Claude returned HTTP '.$response->status().'. '.$this->safeBody($response->body(), $key));
        }

        $text = '';
        $contentBlocks = $response->json('content');
        if (is_array($contentBlocks)) {
            foreach ($contentBlocks as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $text .= $block['text'];
                }
            }
        }

        if (trim($text) === '') {
            throw new OllamaException('Claude returned an empty reply. Try again.');
        }

        return $text;
    }

    private function safeBody(string $body, string $key): string
    {
        return substr(str_replace($key, '[key]', $body), 0, 300);
    }
}
