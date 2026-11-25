<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\Generation;
use App\Services\AgentChat;
use App\Services\BackgroundWork;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->keepSqliteResponsive();

        View::composer('layouts.app', function ($view): void {
            $running = null;
            $user = auth()->user();

            $history = collect();
            $posts = collect();
            $openChatId = null;

            if ($user !== null) {
                $running = app(BackgroundWork::class)->runningFor($user);
                $history = app(AgentChat::class)->history($user);
                $posts = Generation::finishedFor($user->id)->take(5);
                $open = request()->route('conversation');

                if (request()->routeIs('chat.show')) {
                    $openChatId = $open instanceof Conversation
                        ? $open->id
                        : $history->first()?->id;
                }
            }

            $view->with('runningGeneration', $running);
            $view->with('sidebarChats', $history);
            $view->with('sidebarPosts', $posts);
            $view->with('openChatId', $openChatId);
        });
    }

    private function keepSqliteResponsive(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || $database === ':memory:' || $database === '') {
            return;
        }

        DB::statement('PRAGMA journal_mode=WAL');
        DB::statement('PRAGMA busy_timeout=5000');
    }
}
