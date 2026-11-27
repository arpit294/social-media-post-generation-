@extends('layouts.app')

@section('title', 'Settings')
@section('kicker', 'Account')
@section('heading', 'Settings')

@section('content')
    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PUT')
        <div class="settings-grid">
            <section class="panel">
                <h2>Your details</h2>
                <p class="meta">Name, email, and password for this account.</p>
                <label for="name">Name</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                <label for="password">New password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" placeholder="Leave blank to keep the current password">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
            </section>
            <section class="panel">
                <h2>Which key to use</h2>
                <p class="meta">Local model runs on this computer. After you add a key, choose it here.</p>
                <label for="active_api_key_id">Use</label>
                <select id="active_api_key_id" name="active_api_key_id">
                    <option value="" @selected(old('active_api_key_id', $user->active_api_key_id) === null || old('active_api_key_id', $user->active_api_key_id) === '')>Local model ({{ $localModel }})</option>
                    @foreach ($keys as $key)
                        <option value="{{ $key->id }}" @selected((string) old('active_api_key_id', $user->active_api_key_id) === (string) $key->id)>{{ $key->name }} · {{ $key->model }}</option>
                    @endforeach
                </select>
            </section>
        </div>
        <div class="savebar">
            <p class="meta">Chat and posts follow the key selected above.</p>
            <button type="submit">Save settings</button>
        </div>
    </form>
    <section class="panel" style="margin-top: 16px;">
        <h2>Add an API key</h2>
        <p class="meta">Give the key a name so you can tell them apart, then pick that name above.</p>
        <form method="POST" action="{{ route('settings.keys.store') }}">
            @csrf
            <div class="add-key">
                <div>
                    <label for="key_name">Name</label>
                    <input id="key_name" name="name" value="{{ old('name') }}" placeholder="Claude 3.5 Sonnet" required>
                </div>
                <div>
                    <label for="api_key">API key</label>
                    <input id="api_key" name="api_key" type="password" autocomplete="off" placeholder="sk-ant-..." required>
                </div>
                <div>
                    <label for="model">Model</label>
                    <input id="model" name="model" value="{{ old('model', 'claude-3-5-sonnet-20241022') }}" placeholder="claude-3-5-sonnet-20241022">
                </div>
                <button type="submit">Add API key</button>
            </div>
        </form>
        @if ($keys->isNotEmpty())
            <div class="key-list">
                @foreach ($keys as $key)
                    <form method="POST" action="{{ route('settings.keys.destroy', $key) }}">
                        @csrf
                        @method('DELETE')
                        <span>{{ $key->name }} · {{ $key->model }}</span>
                        <button class="ghost" type="submit">Remove</button>
                    </form>
                @endforeach
            </div>
        @endif
    </section>
@endsection
