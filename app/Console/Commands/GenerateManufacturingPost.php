<?php

namespace App\Console\Commands;

use App\Services\ManufacturingPostGenerator;
use App\Services\OllamaException;
use Illuminate\Console\Command;

class GenerateManufacturingPost extends Command
{
    protected $signature = 'content:post
        {--platform=LinkedIn : LinkedIn, Instagram, or Facebook}
        {--segment=small : small or medium}
        {--language=English : English or Gujarati-mix}
        {--prompt= : The shop-floor moment, or "generate post"}
        {--topic= : Topic ID such as DSP-02, used when prompt is empty}
        {--real-detail= : Extra detail, used when prompt is empty}
        {--recent-topics= : Topic IDs already used, comma separated}';

    protected $description = 'Write one manufacturing post with the local Ollama model and the Webexpert knowledge base';

    public function handle(ManufacturingPostGenerator $generator): int
    {
        $this->info('Writing a post with '.config('ollama.model').'. This can take several minutes.');

        try {
            $prompt = trim((string) $this->option('prompt'));

            if ($prompt === '') {
                $prompt = trim((string) $this->option('topic')."\n".(string) $this->option('real-detail'));
            }

            $generated = $generator->generate([
                'platform' => (string) $this->option('platform'),
                'segment' => (string) $this->option('segment'),
                'language' => (string) $this->option('language'),
                'prompt' => $prompt !== '' ? $prompt : 'generate post',
                'recent_topic_ids' => (string) $this->option('recent-topics'),
            ]);
        } catch (OllamaException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $post = $generated['data']['post_text'] ?? null;

        if (! is_string($post) || $post === '') {
            $this->warn('The model did not return a post_text field. Raw response:');
            $this->line($generated['raw']);

            return self::FAILURE;
        }

        $this->newLine();
        $this->line($post);

        $image = $generated['data']['image_prompt'] ?? null;

        if (is_string($image) && $image !== '') {
            $this->newLine();
            $this->info('Image');
            $this->line($image);
        }

        return self::SUCCESS;
    }
}
