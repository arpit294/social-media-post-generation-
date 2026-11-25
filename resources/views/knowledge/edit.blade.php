@extends('layouts.app')

@section('title', 'Knowledge base')
@section('kicker', 'Instructions')
@section('heading', 'Knowledge base')

@section('content')
    <section class="panel">
        @if ($saved)
            <p class="lede">Saved for {{ auth()->user()->name }}. Chat and posts use this text.</p>
        @else
            <p class="lede">This is the starting brief. Save it to keep {{ auth()->user()->name }}'s copy. Chat and posts use it after you save.</p>
        @endif
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('knowledge.update') }}">
            @csrf
            @method('PUT')
            <label for="body">Knowledge base</label>
            <textarea id="body" class="editor" name="body">{{ $body }}</textarea>
            <div class="savebar">
                <p class="meta">The agent follows this text on every reply and every post.</p>
                <button type="submit">Save knowledge base</button>
            </div>
        </form>
    </section>
@endsection
