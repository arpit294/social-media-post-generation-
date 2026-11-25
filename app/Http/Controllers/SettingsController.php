<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApiKeyRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends BaseController
{
    public function edit(): View
    {
        $this->breadcrumbs([
            'Settings' => route('settings.edit'),
        ]);
        $user = auth()->user();

        return view('settings.edit', [
            'user' => $user,
            'keys' => $user->apiKeys()->orderBy('name')->get(),
            'localModel' => (string) config('ollama.model'),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->active_api_key_id = $data['active_api_key_id'] ?? null;

        if (($data['password'] ?? null) !== null && $data['password'] !== '') {
            $user->password = $data['password'];
        }

        $user->save();

        return redirect()
            ->route('settings.edit')
            ->with('status', 'Settings saved.');
    }

    public function storeKey(StoreApiKeyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $request->user()->apiKeys()->create([
            'name' => $data['name'],
            'api_key' => trim($data['api_key']),
            'model' => trim((string) ($data['model'] ?? '')) ?: 'gpt-4o-mini',
        ]);

        return redirect()
            ->route('settings.edit')
            ->with('status', 'API key added. Choose it under Use when you want it to write.');
    }

    public function destroyKey(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->delete();

        return redirect()
            ->route('settings.edit')
            ->with('status', 'API key removed.');
    }
}
