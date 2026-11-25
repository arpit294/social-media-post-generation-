<?php

namespace App\Console\Commands;

use App\Models\Generation;
use App\Services\BackgroundWork;
use Illuminate\Console\Command;

class RunGeneration extends Command
{
    protected $signature = 'generation:run {generation}';

    protected $description = 'Finish one post or chat reply without blocking the pages';

    public function handle(BackgroundWork $work): int
    {
        $generation = Generation::query()->find($this->argument('generation'));

        if ($generation === null || $generation->status !== 'running') {
            return self::SUCCESS;
        }

        $work->execute($generation);

        return self::SUCCESS;
    }
}
