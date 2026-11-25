<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateKnowledgeBaseRequest;
use App\Models\KnowledgeBase;
use App\Services\ManufacturingPostGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KnowledgeBaseController extends BaseController
{
    public function edit(ManufacturingPostGenerator $posts): View
    {
        $this->breadcrumbs([
            'Knowledge base' => route('knowledge.edit'),
        ]);
        $base = KnowledgeBase::query()->firstOrNew([
            'user_id' => $requestUser = auth()->id(),
        ]);

        if (! $base->exists) {
            $base->user_id = $requestUser;
            $base->body = $posts->defaultInstructions();
        }

        return view('knowledge.edit', [
            'body' => old('body', $base->body),
            'saved' => $base->exists,
        ]);
    }

    public function update(UpdateKnowledgeBaseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        KnowledgeBase::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['body' => $data['body']],
        );

        return redirect()
            ->route('knowledge.edit')
            ->with('status', 'Knowledge base saved. The agent will use this text.');
    }
}
