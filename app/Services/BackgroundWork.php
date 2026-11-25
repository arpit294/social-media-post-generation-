<?php

namespace App\Services;

use App\Models\Generation;
use App\Models\User;
use Throwable;

class BackgroundWork
{
    public function runningFor(User $user): ?Generation
    {
        $generation = Generation::query()
            ->where('user_id', $user->id)
            ->where('status', 'running')
            ->latest('id')
            ->first();

        if ($generation === null) {
            return null;
        }

        $generation->failIfStale();

        return $generation->status === 'running' ? $generation : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function start(User $user, string $kind, array $payload): Generation
    {
        if ($this->runningFor($user) !== null) {
            throw new GenerationBusyException('A post is already being written. Other pages stay available while it finishes.');
        }

        $generation = Generation::query()->create([
            'user_id' => $user->id,
            'kind' => $kind,
            'status' => 'running',
            'payload' => $payload,
            'started_at' => now(),
        ]);

        if (app()->runningUnitTests()) {
            $this->execute($generation->refresh());

            return $generation->refresh();
        }

        $this->spawn($generation->id);

        return $generation;
    }

    public function execute(Generation $generation): void
    {
        $user = $generation->user;

        if ($user === null) {
            $this->fail($generation, 'The signed-in user for this write is missing.');

            return;
        }

        auth()->setUser($user);
        set_time_limit((int) config('ollama.timeout') + 30);
        $started = hrtime(true);

        try {
            $result = match ($generation->kind) {
                'post' => app(ManufacturingPostGenerator::class)->generate($generation->payload),
                'chat' => ['reply' => app(AgentChat::class)->reply(
                    $user,
                    (string) ($generation->payload['message'] ?? ''),
                    isset($generation->payload['conversation_id']) ? (int) $generation->payload['conversation_id'] : null,
                )->content],
                'chat_post' => app(AgentChat::class)->generatePost($user, $generation->payload),
                default => throw new OllamaException('This write could not be started.'),
            };

            if (is_array($result)) {
                $result['elapsed_seconds'] = (int) round((hrtime(true) - $started) / 1_000_000_000);
            }

            $generation->update([
                'status' => 'done',
                'result' => $result,
                'finished_at' => now(),
            ]);
        } catch (OllamaException $exception) {
            $this->fail($generation, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->fail($generation, 'The writer stopped before it finished.');

            if (app()->runningUnitTests()) {
                throw $exception;
            }

            report($exception);
        }
    }

    private function spawn(int $id): void
    {
        $php = str_replace("'", "''", PHP_BINARY);
        $artisan = str_replace("'", "''", base_path('artisan'));
        $work = str_replace("'", "''", base_path());
        $script = "Start-Process -FilePath '$php' -ArgumentList @('$artisan','generation:run','$id') -WorkingDirectory '$work' -WindowStyle Hidden";
        $handle = popen('powershell -NoProfile -Command '.escapeshellarg($script), 'r');

        if ($handle === false) {
            $this->fail(Generation::query()->findOrFail($id), 'The writer could not start.');

            return;
        }

        pclose($handle);
    }

    private function fail(Generation $generation, string $message): void
    {
        $generation->update([
            'status' => 'failed',
            'error' => $message,
            'finished_at' => now(),
        ]);
    }
}
