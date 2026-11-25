@extends('layouts.app')

@section('title', 'Chat')
@section('kicker', 'Agent')
@section('heading', 'Chat')
@section('stage_class', 'chat-page')

@section('content')
        <section class="panel chat-frame">
        <div class="chat-head">
            <h2>{{ $conversation->title }}</h2>
            <p class="meta">Uses {{ auth()->user()->name }}'s knowledge base. A reply can take several minutes, and the other pages stay open.</p>
            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif
            @if ($failure)
                <div class="error">{{ $failure }}</div>
            @endif
            @if ($busy && ! $running)
                <p class="meta">A reply is still being written in another chat.</p>
            @endif
            <div id="timer" @if (! $running) hidden @endif>
                <p class="meta">Time so far</p>
                <p id="timer-value" class="timer-value">{{ $running ? $running->clock() : '0:00' }}</p>
                <p class="meta">This clock keeps the time. The other pages still open while the agent writes.</p>
            </div>
        </div>
        <div class="thread">
            @forelse ($messages as $message)
                <article class="bubble {{ $message->role }}">
                    <p class="who">{{ $message->role === 'assistant' ? 'Agent' : $user->name }}</p>
                    <p class="body">{{ $message->content }}</p>
                    @if ($message->role === 'assistant' && filled($message->image_prompt))
                        <div class="image-brief">
                            <p class="who">Image</p>
                            <p class="meta">Paste this into an image generator. Text-free shop-floor artwork, with empty space at the top and bottom.</p>
                            <p class="body">{{ $message->image_prompt }}</p>
                        </div>
                    @endif
                </article>
            @empty
                <p id="chat-idle" class="meta">Enter a shop-floor moment, or type generate post. A question still gets an answer. The caption and the image brief come back together.</p>
            @endforelse
            @if (is_array($generated) && is_array($generated['data'] ?? null) && ! empty($generated['elapsed_seconds']))
                <p class="meta">Latest post · created in {{ sprintf('%d:%02d', intdiv((int) $generated['elapsed_seconds'], 60), ((int) $generated['elapsed_seconds']) % 60) }}</p>
            @endif
        </div>
        <div class="composer">
            <form id="chat-form" method="POST" action="{{ route('chat.session.store', $conversation) }}">
                @csrf
                <label for="message">Prompt</label>
                <div class="send-row">
                    <textarea id="message" class="autosize" name="message" rows="1" placeholder="generate the post">{{ old('message') }}</textarea>
                    <button type="submit" @disabled($busy)>Write post</button>
                </div>
            </form>
        </div>
        </section>
@endsection

@push('scripts')
    <script>
        function startTimer(form, buttonText) {
            form.addEventListener('submit', function () {
                const button = form.querySelector('button[type="submit"]');
                button.disabled = true;
                button.textContent = buttonText;
                const timer = document.getElementById('timer');
                const value = document.getElementById('timer-value');
                const idle = document.getElementById('chat-idle');
                timer.hidden = false;
                if (idle) idle.hidden = true;
                const started = Date.now();
                const tick = function () {
                    const seconds = Math.floor((Date.now() - started) / 1000);
                    value.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
                };
                tick();
                window.setInterval(tick, 1000);
            });
        }
        startTimer(document.getElementById('chat-form'), 'Writing…');
        const thread = document.querySelector('.thread');
        if (thread) thread.scrollTop = thread.scrollHeight;
    </script>
@endpush
@include('partials.running-clock')
