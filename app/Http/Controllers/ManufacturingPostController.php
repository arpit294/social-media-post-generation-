<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateManufacturingPostRequest;
use App\Models\Generation;
use App\Services\BackgroundWork;
use App\Services\GenerationBusyException;
use App\Services\LlmClient;
use App\Services\ReplyText;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ManufacturingPostController extends BaseController
{
    public function create(BackgroundWork $work, LlmClient $writer): View
    {
        $this->breadcrumbs([
            'Dashboard' => route('dashboard'),
        ]);
        $user = auth()->user();
        $running = $work->runningFor($user);
        $latest = Generation::query()
            ->where('user_id', $user->id)
            ->where('kind', 'post')
            ->where('status', '!=', 'running')
            ->latest('id')
            ->first();
        $generated = $latest?->status === 'done' ? $latest->result : session('generated_post');

        if (is_array($generated) && is_string(data_get($generated, 'data.post_text'))) {
            $generated['data']['post_text'] = ReplyText::forChat($generated['data']['post_text']);
        }

        return view('posts.create', [
            'model' => $writer->model(),
            'generated' => $generated,
            'failure' => $latest?->status === 'failed' ? $latest->error : null,
            'running' => $running?->kind === 'post' ? $running : null,
        ]);
    }

    public function store(
        GenerateManufacturingPostRequest $request,
        BackgroundWork $work,
    ): RedirectResponse {
        try {
            $payload = $request->validated();
            $payload['segment'] = 'small';
            $payload['prompt'] = trim($payload['prompt']);
            $work->start($request->user(), 'post', $payload);
        } catch (GenerationBusyException $exception) {
            return redirect()
                ->route('dashboard')
                ->withInput()
                ->withErrors(['generation' => $exception->getMessage()]);
        }

        return redirect()->route('dashboard');
    }

    public function index(): View
    {
        $this->breadcrumbs([
            'Posts' => route('posts.index'),
        ]);

        return view('posts.index', [
            'posts' => Generation::finishedFor(auth()->id()),
        ]);
    }

    public function show(Generation $generation): View
    {
        abort_unless($generation->status === 'done' && $generation->caption() !== null, 404);

        return view('posts.show', [
            'generation' => $generation,
        ]);
    }
}
